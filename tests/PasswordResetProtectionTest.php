<?php
return [
    'protected reset quotas tokens concurrency mail failures and atomic rollback' => function ($root) {
        if (getenv('RUN_SECURITY_DB_TESTS') !== '1') { test_skip('Requires isolated security database.'); }
        test_assert(in_array(getenv('SECURITY_TEST_DB_NAME'), ['dts_security_test_phase3','dts_security_test_phase4','dts_security_test_phase5'], true), 'Dedicated security fixture schema required.');
        require_once $root . '/app/init.php';
        $pdo = new PDO('mysql:host=' . getenv('SECURITY_TEST_DB_HOST') . ';port=' . getenv('SECURITY_TEST_DB_PORT') . ';dbname=' . getenv('SECURITY_TEST_DB_NAME'),
            getenv('SECURITY_TEST_DB_USER'), getenv('SECURITY_TEST_DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $tables = ['password_reset_outbox', 'password_reset_admissions', 'password_reset_limits', 'password_reset_tokens', 'users'];
        foreach ($tables as $table) { $pdo->exec("DROP TABLE IF EXISTS $table"); }
        try {
            $pdo->exec("CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(254) UNIQUE, status VARCHAR(16), password VARCHAR(255), password_changed_at DATETIME NULL, must_change_password TINYINT DEFAULT 1, session_version INT DEFAULT 4) ENGINE=InnoDB");
            $pdo->exec("CREATE TABLE password_reset_tokens (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, token_hash CHAR(64) UNIQUE, expires_at DATETIME, used_at DATETIME NULL, INDEX user_idx(user_id)) ENGINE=InnoDB");
            $migration = require $root . '/database/migrations/20261010_0019_password_reset_protection.php';
            $migration($pdo);
            $pdo->exec("INSERT INTO users (id,email,status,password) VALUES (1,'employee@example.test','active','original'),(2,'other@example.test','active','original'),(3,'inactive@example.test','inactive','original'),(4,'concurrent@example.test','active','original')");
            $service = new PasswordResetProtection($pdo, 'fixture-key');
            $token = static function () use ($pdo) { return decryptSensitiveValue($pdo->query('SELECT token_encrypted FROM password_reset_outbox ORDER BY id DESC LIMIT 1')->fetchColumn()); };
            test_assert_same('processed', $service->request(' EMPLOYEE@example.test ', 'encryptSensitiveValue'));
            $first = $token();
            test_assert_same(64, strlen($first));
            test_assert_same('limited', $service->request('employee@example.test', 'encryptSensitiveValue'));
            test_assert_same(1, (int) $pdo->query('SELECT COUNT(*) FROM password_reset_tokens WHERE used_at IS NULL')->fetchColumn());
            test_assert_same('processed', $service->request('other@example.test', 'encryptSensitiveValue'), 'Other shared-IP employees are independent.');
            $before = (int) $pdo->query('SELECT COUNT(*) FROM password_reset_outbox')->fetchColumn();
            test_assert_same('processed', $service->request('missing@example.test', 'encryptSensitiveValue'));
            test_assert_same('processed', $service->request('inactive@example.test', 'encryptSensitiveValue'));
            test_assert_same($before, (int) $pdo->query('SELECT COUNT(*) FROM password_reset_outbox')->fetchColumn());
            test_assert_same('limited', $service->request('missing@example.test', 'encryptSensitiveValue'));
            for ($i = 0; $i < 2; $i++) {
                $pdo->exec('UPDATE password_reset_limits SET next_allowed=0');
                test_assert_same('processed', $service->request('employee@example.test', 'encryptSensitiveValue'));
            }
            $latest = $token();
            $pdo->exec('UPDATE password_reset_limits SET next_allowed=0');
            test_assert_same('limited', $service->request('employee@example.test', 'encryptSensitiveValue'), 'Three-per-rolling-hour quota survives cooldown expiry.');
            test_assert_same(null, $service->complete($first, 'Updated-Password-42!'), 'A permitted replacement invalidates earlier links.');
            test_assert_same(1, $service->complete($latest, 'Updated-Password-42!'));
            test_assert_same(null, $service->complete($latest, 'Updated-Password-43!'));
            $row = $pdo->query('SELECT * FROM users WHERE id=1')->fetch(PDO::FETCH_ASSOC);
            test_assert(password_verify('Updated-Password-42!', $row['password']));
            test_assert_same(5, (int) $row['session_version']);
            test_assert_same(0, (int) $row['must_change_password']);
            $encryptFails = static function () { throw new RuntimeException('fixture encryption failure'); };
            $count = (int) $pdo->query('SELECT COUNT(*) FROM password_reset_admissions')->fetchColumn();
            try { $service->request('fresh@example.test', $encryptFails); test_assert(false); } catch (RuntimeException $e) { test_assert_same('Reset protection unavailable.', $e->getMessage()); }
            test_assert_same($count, (int) $pdo->query('SELECT COUNT(*) FROM password_reset_admissions')->fetchColumn());

            $workers = static function ($mode, $n) use ($root) {
                $running = [];
                for ($i=0; $i<$n; $i++) {
                    $pipes=[];
                    $p=proc_open([PHP_BINARY,$root.'/tests/support/password_reset_worker.php',$mode], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
                    fclose($pipes[0]); $running[]=[$p,$pipes];
                }
                $results=[];
                foreach ($running as [$p,$pipes]) {
                    $results[]=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]);
                    fclose($pipes[1]); fclose($pipes[2]); test_assert_same(0,proc_close($p),$err);
                }
                return array_count_values($results);
            };
            $results=$workers('request',8);
            test_assert_same(1,$results['processed']??0);
            test_assert_same(7,$results['limited']??0);
            $concurrentToken=$token();
            putenv('RESET_TEST_TOKEN='.$concurrentToken);
            $results=$workers('complete',8);
            putenv('RESET_TEST_TOKEN');
            test_assert_same(1,$results['completed']??0);
            test_assert_same(7,$results['invalid']??0);
            test_assert_same(5,(int)$pdo->query('SELECT session_version FROM users WHERE id=4')->fetchColumn());
            // All stale jobs are discarded without calling the transport.
            $sent=0;
            while (($status=$service->deliverOne('decryptSensitiveValue',static function () use (&$sent) { $sent++; return true; }))!=='empty') {}
            test_assert_same(1,$sent,'Only the still-valid other employee token may be sent.');
            $pdo->exec('UPDATE password_reset_limits SET next_allowed=0');
            test_assert_same('processed',$service->request('other@example.test','encryptSensitiveValue'));
            $results=$workers('deliver',4);
            test_assert_same(1,$results['accepted']??0);
            test_assert_same(3,$results['empty']??0);
            $pdo->exec('UPDATE password_reset_limits SET next_allowed=0');
            test_assert_same('processed',$service->request('other@example.test','encryptSensitiveValue'));
            test_assert_same('failed',$service->deliverOne('decryptSensitiveValue',static function () { throw new RuntimeException('provider outage'); }));
            test_assert_same('empty',$service->deliverOne('decryptSensitiveValue',static function () { test_assert(false,'No automatic resend'); }));
            test_assert_same(0,(int)$pdo->query("SELECT COUNT(*) FROM password_reset_outbox WHERE status!='queued' AND token_encrypted!=''")->fetchColumn());

            // Force post-password token failure: both changes must roll back.
            $pdo->exec('DELETE FROM password_reset_admissions'); $pdo->exec('UPDATE password_reset_limits SET next_allowed=0');
            $service->request('employee@example.test','encryptSensitiveValue'); $rollbackToken=$token();
            $pdo->exec("CREATE TRIGGER reset_failure BEFORE UPDATE ON password_reset_tokens FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture failure'");
            try { $service->complete($rollbackToken,'Would-Not-Be-Saved-42!'); test_assert(false); } catch (RuntimeException $e) { test_assert_same('Reset completion unavailable.',$e->getMessage()); }
            test_assert_same(5,(int)$pdo->query('SELECT session_version FROM users WHERE id=1')->fetchColumn());
            test_assert(password_verify('Updated-Password-42!',$pdo->query('SELECT password FROM users WHERE id=1')->fetchColumn()));
            $pdo->exec('DROP TRIGGER reset_failure');
            $pdo->exec('UPDATE password_reset_tokens SET expires_at=DATE_SUB(NOW(),INTERVAL 1 SECOND)');
            test_assert_same(null,$service->complete($rollbackToken,'Expired-Password-42!'));
            $pdo->exec('UPDATE password_reset_outbox SET expires_at=UNIX_TIMESTAMP()-1');
            $service->prune();
            test_assert_same(0,(int)$pdo->query("SELECT COUNT(*) FROM password_reset_outbox WHERE token_encrypted!=''")->fetchColumn());
            $pdo->exec('ALTER TABLE password_reset_tokens ENGINE=MyISAM');
            try { new PasswordResetProtection($pdo,'fixture-key'); test_assert(false); }
            catch (RuntimeException $e) { test_assert_same('Reset protection requires transactional users and token tables.',$e->getMessage()); }
            $pdo->exec('ALTER TABLE password_reset_tokens ENGINE=InnoDB');
            $pdo->exec('ALTER TABLE password_reset_tokens ENGINE=MyISAM');
            try { new PasswordResetProtection($pdo,'fixture-key'); test_assert(false); }
            catch (RuntimeException $e) { test_assert_same('Reset protection requires transactional users and token tables.',$e->getMessage()); }
            $pdo->exec('ALTER TABLE password_reset_tokens ENGINE=InnoDB');
            $global=hash_hmac('sha256','global','fixture-key');
            $q=$pdo->prepare('INSERT INTO password_reset_admissions (bucket_hash,admitted_at) VALUES (?,UNIX_TIMESTAMP())');
            for($i=0;$i<1000;$i++) { $q->execute([$global]); }
            test_assert_same('limited',$service->request('global-test@example.test','encryptSensitiveValue'));
            // Exercise the actual HTTP controller without sending mail or using production data.
            if (!extension_loaded('curl')) { throw new RuntimeException('HTTP validation requires curl.'); }
            $pdo->exec("CREATE TABLE security_audit_log (id INT AUTO_INCREMENT PRIMARY KEY,event_type VARCHAR(64),actor_user_id INT NULL,target_user_id INT NULL,ip_address VARCHAR(45),user_agent VARCHAR(500),metadata LONGTEXT,created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
            $pdo->exec('DELETE FROM password_reset_admissions');
            $pdo->exec('UPDATE password_reset_limits SET next_allowed=0');
            $httpSessionDir = str_replace('\\', '/', $root) . '/storage/logs/dts_reset_http_' . bin2hex(random_bytes(6));
            mkdir($httpSessionDir);
            $environment = getenv();
            $environment['APP_ENV']='development';
            $environment['ADMIN_SECURITY_MODE']='off';
            $environment['APP_URL']='http://127.0.0.1:3318';
            $environment['DB_HOST']=getenv('SECURITY_TEST_DB_HOST').';port='.getenv('SECURITY_TEST_DB_PORT');
            $environment['DB_NAME']=getenv('SECURITY_TEST_DB_NAME');
            $environment['DB_USER']=getenv('SECURITY_TEST_DB_USER');
            $environment['DB_PASS']=getenv('SECURITY_TEST_DB_PASS') ?: '';
            $environment['APP_KEY']='fixture-http-key';
            $environment['PASSWORD_RESET_GUARD_MODE']='enforce';
            $environment['PASSWORD_RESET_ENABLED']='true';
            $environment['SECURITY_MONITOR_MODE']='off';
            $environment['SMTP_HOST']='';
            $environment['SMTP_USER']='';
            $environment['SMTP_PASS']='';
            $pipes=[];
            $server=proc_open([PHP_BINARY,'-d','session.save_path='.$httpSessionDir,'-S','127.0.0.1:3318','-t',$root.'/public'],
                [0=>['pipe','r'],1=>['file',$httpSessionDir.'/server.log','a'],2=>['file',$httpSessionDir.'/server.log','a']],$pipes,$root,$environment);
            fclose($pipes[0]);
            $curl=curl_init('http://127.0.0.1:3318/index.php?url=auth/forgotPassword');
            curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>3]);
            try {
                $form=false;
                for($i=0;$i<30;$i++) { $form=curl_exec($curl); if($form!==false) { break; } usleep(100000); }
                test_assert(is_string($form),'Isolated HTTP server did not start.');
                test_assert(preg_match('/name="csrf_token" value="([^"]+)"/',$form,$match)===1,'CSRF form must render.');
                $post=static function ($email) use ($curl,$match) {
                    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['csrf_token'=>$match[1],'email'=>$email])]);
                    $body=curl_exec($curl);
                    test_assert_same(200,(int)curl_getinfo($curl,CURLINFO_HTTP_CODE));
                    return $body;
                };
                $normal=$post('employee@example.test');
                test_assert_same($normal,$post('employee@example.test'),'Limited and accepted replies must match.');
                test_assert_same($normal,$post('http-missing@example.test'),'No account existence disclosure in body.');
                test_assert_same($normal,$post('inactive@example.test'));
                test_assert(strpos($normal,'resetPassword/')===false,'No development token link in guarded reply.');
                $pdo->exec('DROP TABLE password_reset_admissions');
                test_assert_same($normal,$post('http-outage@example.test'),'Counter outage must preserve generic reply.');
            } finally {
                curl_close($curl);
                proc_terminate($server); proc_close($server);
                foreach (glob($httpSessionDir.'/*') as $file) { unlink($file); }
                rmdir($httpSessionDir);
                $pdo->exec('DROP TABLE security_audit_log');
            }
            try { $service->request('db-outage@example.test','encryptSensitiveValue'); test_assert(false); } catch (RuntimeException $e) { test_assert_same('Reset protection unavailable.',$e->getMessage()); }
        } finally {
            foreach ($tables as $table) { $pdo->exec("DROP TABLE IF EXISTS $table"); }
        }
    },
];
