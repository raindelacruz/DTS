<?php
return [
    'registration identifier policy preserves existing formats and rejects malformed input' => function ($root) {
        require_once $root.'/app/lib/RegistrationProtection.php';
        test_assert(RegistrationProtection::validId('Legacy/ID-004'));
        test_assert(!RegistrationProtection::validId("EMP\x00"));
        test_assert(!RegistrationProtection::validId(str_repeat('a',51)));
        $old=getenv('REGISTRATION_ID_PATTERN');
        try {
            putenv('REGISTRATION_ID_PATTERN=/EMP-[0-9]{4}/');
            test_assert(RegistrationProtection::validId('EMP-0042'));
            test_assert(!RegistrationProtection::validId('xEMP-0042'));
            putenv('REGISTRATION_ID_PATTERN=invalid');
            $caught=false;
            try { RegistrationProtection::validId('EMP-0042'); } catch(RuntimeException $e) { $caught=true; }
            test_assert($caught,'Invalid ID policy must fail explicitly.');
        } finally { $old===false ? putenv('REGISTRATION_ID_PATTERN') : putenv('REGISTRATION_ID_PATTERN='.$old); }
    },
    'registration requires explicit eligibility configuration before enforcement' => function ($root) {
        require_once $root.'/app/lib/RegistrationProtection.php';
        $mode=getenv('REGISTRATION_GUARD_MODE'); $model=getenv('REGISTRATION_ELIGIBILITY_MODEL');
        try {
            putenv('REGISTRATION_GUARD_MODE=enforce'); putenv('REGISTRATION_ELIGIBILITY_MODEL');
            $caught=false;
            try { RegistrationProtection::enabled(); } catch(RuntimeException $e) { $caught=true; }
            test_assert($caught,'Missing eligibility policy must prevent enforcement.');
            putenv('REGISTRATION_ELIGIBILITY_MODEL=invitation'); test_assert(RegistrationProtection::enabled());
        } finally {
            $mode===false ? putenv('REGISTRATION_GUARD_MODE') : putenv('REGISTRATION_GUARD_MODE='.$mode);
            $model===false ? putenv('REGISTRATION_ELIGIBILITY_MODEL') : putenv('REGISTRATION_ELIGIBILITY_MODEL='.$model);
        }
    },
    'registration invitations concurrency approval privacy notifications and failure rollback' => function ($root) {
        if(getenv('RUN_SECURITY_DB_TESTS')!=='1') { test_skip('Requires isolated Phase 4 database.'); }
        test_assert(in_array(getenv('SECURITY_TEST_DB_NAME'),['dts_security_test_phase4','dts_security_test_phase5'],true),'Production schemas refused.');
        require_once $root.'/app/init.php';
        $pdo=new PDO('mysql:host='.getenv('SECURITY_TEST_DB_HOST').';port='.getenv('SECURITY_TEST_DB_PORT').';dbname='.getenv('SECURITY_TEST_DB_NAME'),
            getenv('SECURITY_TEST_DB_USER'),getenv('SECURITY_TEST_DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $tables=['registration_reviews','registration_invitations','registration_notice_state','registration_attempts','registration_limit_locks',
            'notifications','security_audit_log','users','departments','user_access_change_history','login_attempts'];
        foreach($tables as $table) { $pdo->exec("DROP TABLE IF EXISTS $table"); }
        try {
            $pdo->exec("CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY,id_number VARCHAR(50) NOT NULL UNIQUE,email VARCHAR(150) NOT NULL UNIQUE,
                firstname VARCHAR(100),lastname VARCHAR(100),department_id INT,role VARCHAR(20) DEFAULT 'staff',status VARCHAR(20) DEFAULT 'inactive',
                password VARCHAR(255),session_version INT DEFAULT 0,mfa_enabled TINYINT DEFAULT 0,mfa_secret TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,password_changed_at DATETIME NULL,must_change_password TINYINT DEFAULT 0,last_login_at DATETIME NULL) ENGINE=InnoDB");
            $pdo->exec("CREATE TABLE departments (id INT PRIMARY KEY,parent_id INT NULL,division_name VARCHAR(100),department_name VARCHAR(100),code VARCHAR(30),office_level VARCHAR(20) DEFAULT 'department',can_release TINYINT DEFAULT 1,INDEX idx_departments_office_level(office_level)) ENGINE=InnoDB");
            $pdo->exec("CREATE TABLE notifications (id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,title VARCHAR(255),message TEXT,link VARCHAR(255),is_read TINYINT DEFAULT 0,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,read_at DATETIME NULL) ENGINE=InnoDB");
            $pdo->exec("CREATE TABLE security_audit_log (id INT AUTO_INCREMENT PRIMARY KEY,event_type VARCHAR(64),actor_user_id INT NULL,target_user_id INT NULL,ip_address VARCHAR(45),user_agent VARCHAR(500),metadata LONGTEXT,created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
            $pdo->exec("INSERT INTO departments (id,division_name,department_name,code) VALUES (1,'Office One','Office One','ONE'),(2,'Office Two','Office Two','TWO')");
            $pdo->exec("INSERT INTO users (id,id_number,email,firstname,lastname,department_id,role,status,password,mfa_enabled) VALUES
                (1,'ADMIN','admin@example.test','Admin','One',1,'admin','active','fixture',1),
                (2,'NO-MFA','nomfa@example.test','Admin','Two',1,'admin','active','fixture',0)");
            $migration=require $root.'/database/migrations/20261010_0020_registration_protection.php'; $migration($pdo);
            $service=new RegistrationProtection($pdo,'fixture-key');
            $throws=static function(string $class,callable $action,?string $message=null) {
                $error=null; try { $action(); } catch(Throwable $e) { $error=$e; }
                test_assert($error instanceof $class,'Expected exception '.$class);
                if($message!==null) { test_assert_same($message,$error->getMessage()); }
            };
            $issue=static function($id,$email,$dep=1) use($service) { return $service->issue(1,$id,$email,$dep,'Verified against authorized employee roster'); };
            $values=static function($id,$email,$dep=1) { return ['id_number'=>$id,'email'=>$email,'firstname'=>'New','lastname'=>'Employee','department_id'=>$dep,'role'=>'admin','status'=>'active']; };
            $throws(RuntimeException::class,static function() use($service) { $service->issue(2,'Unauthorized','unauth@example.test',1,'Verified employee reference'); });
            $invite=$issue('Employee/004','employee@example.test');
            $code=$invite['invitation_code'];
            $bad=$service->register($values('Wrong/ID','employee@example.test'),'Fixture-Password-42!',$code); test_assert_same('invalid',$bad['status']);
            $bad=$service->register($values('Employee/004','employee@example.test',2),'Fixture-Password-42!',$code); test_assert_same('invalid',$bad['status']);
            test_assert_same('invalid',$service->register($values('Employee/004','elsewhere@example.test'),'Fixture-Password-42!',$code)['status']);
            $result=$service->register($values('Employee/004',' EMPLOYEE@example.test '),'Fixture-Password-42!',$code);
            test_assert_same('accepted',$result['status']); $user=$result['user_id'];
            $row=$pdo->query('SELECT * FROM users WHERE id='.$user)->fetch(PDO::FETCH_ASSOC);
            test_assert_same('inactive',$row['status']); test_assert_same('staff',$row['role']);
            test_assert(password_verify('Fixture-Password-42!',$row['password']));
            test_assert_same('duplicate',$service->register($values('Employee/004','employee@example.test'),'Other-Password-43!',$code)['status']);
            test_assert_same(3,(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
            test_assert_same('limited',$service->register($values('Employee/004','employee@example.test'),'Fixture-Password-42!',$code)['status']);
            $other=$issue('Other-Employee','other@example.test',2);
            test_assert_same('accepted',$service->register($values('Other-Employee','other@example.test',2),'Fixture-Password-42!',$other['invitation_code'])['status'],'Shared IP must not block independent invited employees.');
            test_assert_same(2,(int)$pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn(),'One grouped notification per administrator.');
            $throws(RuntimeException::class,static function() use($service,$user) { $service->review(2,$user,'approved','Verified eligibility reference'); });
            $throws(InvalidArgumentException::class,static function() use($service,$user) { $service->review(1,$user,'approved',''); });
            $pdo->exec('UPDATE users SET department_id=2 WHERE id='.$user);
            $throws(InvalidArgumentException::class,static function() use($service,$user) { $service->review(1,$user,'approved','Verified eligibility reference'); });
            $pdo->exec('UPDATE users SET department_id=1 WHERE id='.$user);
            $service->review(1,$user,'rejected','Eligibility review needs additional evidence');
            test_assert_same('inactive',$pdo->query('SELECT status FROM users WHERE id='.$user)->fetchColumn());
            $service->review(1,$user,'approved','Eligibility independently verified with roster owner');
            test_assert_same('active',$pdo->query('SELECT status FROM users WHERE id='.$user)->fetchColumn());
            test_assert_same(2,(int)$pdo->query('SELECT session_version FROM users WHERE id='.$user)->fetchColumn());
            test_assert_same('approved',$service->reviewRecord($user)['decision']);
            $revoked=$issue('Revoked','revoked@example.test'); $service->revoke(1,$revoked['invitation_id'],'Employee invitation cancelled after review');
            test_assert_same('invalid',$service->register($values('Revoked','revoked@example.test'),'Fixture-Password-42!',$revoked['invitation_code'])['status']);
            $expired=$issue('Expired','expired@example.test'); $pdo->exec('UPDATE registration_invitations SET expires_at=UNIX_TIMESTAMP()-1 WHERE id='.$expired['invitation_id']);
            test_assert_same('invalid',$service->register($values('Expired','expired@example.test'),'Fixture-Password-42!',$expired['invitation_code'])['status']);
            $old=$issue('Replacement','replace@example.test'); $new=$issue('Replacement','replace@example.test');
            test_assert_same('invalid',$service->register($values('Replacement','replace@example.test'),'Fixture-Password-42!',$old['invitation_code'])['status']);
            test_assert_same('accepted',$service->register($values('Replacement','replace@example.test'),'Fixture-Password-42!',$new['invitation_code'])['status']);

            $race=$issue('Race-Employee','race@example.test',2); putenv('REGISTRATION_TEST_CODE='.$race['invitation_code']);
            $running=[];
            for($i=0;$i<8;$i++) { $pipes=[]; $p=proc_open([PHP_BINARY,$root.'/tests/support/registration_worker.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes); fclose($pipes[0]); $running[]=[$p,$pipes]; }
            $results=[];
            foreach($running as[$p,$pipes]) { $results[]=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); test_assert_same(0,proc_close($p),$err); }
            putenv('REGISTRATION_TEST_CODE'); $counts=array_count_values($results);
            test_assert_same(1,$counts['accepted']??0); test_assert_same(4,$counts['duplicate']??0); test_assert_same(3,$counts['limited']??0);
            test_assert_same(1,(int)$pdo->query("SELECT COUNT(*) FROM users WHERE id_number='Race-Employee'")->fetchColumn());
            test_assert_same(2,(int)$pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn(),'Retries cannot flood notifications.');
            $metadata=implode('', $pdo->query('SELECT metadata FROM security_audit_log')->fetchAll(PDO::FETCH_COLUMN));
            test_assert(strpos($metadata,$code)===false && strpos($metadata,'Fixture-Password-42!')===false,'No secret code/password in audits.');

            $rollback=$issue('Rollback','rollback@example.test');
            $pdo->exec("CREATE TRIGGER registration_audit_failure BEFORE INSERT ON security_audit_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture audit failure'");
            $throws(RuntimeException::class,static function() use($service,$user) { $service->review(1,$user,'rejected','Fixture review should roll back with audit failure'); });
            test_assert_same('active',$pdo->query('SELECT status FROM users WHERE id='.$user)->fetchColumn());
            test_assert_same('approved',$service->reviewRecord($user)['decision'],'Failed audit cannot change review state.');
            $throws(RuntimeException::class,static function() use($service,$values,$rollback) { $service->register($values('Rollback','rollback@example.test'),'Fixture-Password-42!',$rollback['invitation_code']); },'Registration protection unavailable.');
            test_assert_same(0,(int)$pdo->query("SELECT COUNT(*) FROM users WHERE id_number='Rollback'")->fetchColumn());
            test_assert_same(null,$pdo->query('SELECT used_at FROM registration_invitations WHERE id='.$rollback['invitation_id'])->fetchColumn());
            $pdo->exec('DROP TRIGGER registration_audit_failure');
            $global=hash_hmac('sha256','global','fixture-key'); $q=$pdo->prepare('INSERT INTO registration_attempts(bucket_hash,attempted_at) VALUES (?,UNIX_TIMESTAMP())');
            for($i=0;$i<100;$i++) { $q->execute([$global]); }
            $lockCount=(int)$pdo->query('SELECT COUNT(*) FROM registration_limit_locks')->fetchColumn();
            test_assert_same('limited',$service->register($values('Rollback','rollback@example.test'),'Fixture-Password-42!',$rollback['invitation_code'])['status']);
            test_assert_same('limited',$service->register($values('Rotating-New-ID','rotating@example.test'),'Fixture-Password-42!',str_repeat('a',64))['status']);
            test_assert_same($lockCount,(int)$pdo->query('SELECT COUNT(*) FROM registration_limit_locks')->fetchColumn(),'Global denial must not grow subject tables.');
            $pdo->exec('UPDATE registration_attempts SET attempted_at=UNIX_TIMESTAMP()-3601'); $service->prune();
            test_assert_same(0,(int)$pdo->query('SELECT COUNT(*) FROM registration_attempts')->fetchColumn());
            test_assert_same('accepted',$service->register($values('Rollback','rollback@example.test'),'Fixture-Password-42!',$rollback['invitation_code'])['status'],'Expired windows permit normal retry.');

            // Public HTTP integration uses only this dedicated schema and loopback server.
            $http=$issue('HTTP-Employee','http@example.test');
            $httpDir=str_replace('\\','/',$root).'/storage/logs/dts_registration_http_'.bin2hex(random_bytes(6)); mkdir($httpDir);
            $environment=getenv();
            $environment['APP_ENV']='development'; $environment['APP_URL']='http://127.0.0.1:3318';
            $environment['ADMIN_SECURITY_MODE']='off';
            $environment['DB_HOST']=getenv('SECURITY_TEST_DB_HOST').';port='.getenv('SECURITY_TEST_DB_PORT');
            $environment['DB_NAME']=getenv('SECURITY_TEST_DB_NAME'); $environment['DB_USER']=getenv('SECURITY_TEST_DB_USER');
            $environment['DB_PASS']=getenv('SECURITY_TEST_DB_PASS') ?: ''; $environment['APP_KEY']='fixture-http-key';
            $environment['REGISTRATION_GUARD_MODE']='enforce'; $environment['REGISTRATION_ELIGIBILITY_MODEL']='invitation';
            $environment['REGISTRATION_ID_PATTERN']=''; $environment['SECURITY_MONITOR_MODE']='off';
            $environment['AUTH_GUARD_MODE']='off'; $environment['SMTP_HOST']=''; $environment['SMTP_USER']=''; $environment['SMTP_PASS']='';
            $pipes=[];
            $server=proc_open([PHP_BINARY,'-d','session.save_path='.$httpDir,'-S','127.0.0.1:3318','-t',$root.'/public'],
                [0=>['pipe','r'],1=>['file',$httpDir.'/server.log','a'],2=>['file',$httpDir.'/server.log','a']],$pipes,$root,$environment);
            fclose($pipes[0]);
            $curl=curl_init(); curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>3]);
            $get=static function(string $route) use($curl) { curl_setopt_array($curl,[CURLOPT_URL=>'http://127.0.0.1:3318/index.php?url='.$route,CURLOPT_POST=>false]); return curl_exec($curl); };
            $post=static function(string $route,array $payload) use($curl) {
                curl_setopt_array($curl,[CURLOPT_URL=>'http://127.0.0.1:3318/index.php?url='.$route,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($payload)]);
                $body=curl_exec($curl); return [(int)curl_getinfo($curl,CURLINFO_HTTP_CODE),$body,curl_getinfo($curl,CURLINFO_REDIRECT_URL)];
            };
            try {
                $form=false;
                for($i=0;$i<30;$i++) { $form=$get('auth/register'); if($form!==false) { break; } usleep(100000); }
                test_assert(is_string($form)); test_assert(strpos($form,'name="invitation_code"')!==false);
                test_assert(preg_match('/name="csrf_token" value="([^"]+)"/',$form,$match)===1);
                $payload=$values('HTTP-Employee','http@example.test');
                $payload+=['csrf_token'=>$match[1],'password'=>'Fixture-Password-42!','confirm_password'=>'Fixture-Password-42!','invitation_code'=>$http['invitation_code']];
                $bad=$payload; $bad['csrf_token']='invalid';
                [$status]=$post('auth/register',$bad); test_assert_same(303,$status);
                test_assert_same(0,(int)$pdo->query("SELECT COUNT(*) FROM users WHERE id_number='HTTP-Employee'")->fetchColumn());
                [$status,$body,$redirect]=$post('auth/register',$payload); test_assert_same(303,$status); test_assert(strpos($redirect,'auth/login')!==false);
                $httpUser=$pdo->query("SELECT * FROM users WHERE id_number='HTTP-Employee'")->fetch(PDO::FETCH_ASSOC);
                test_assert_same('staff',$httpUser['role']); test_assert_same('inactive',$httpUser['status']);
                [$status]=$post('auth/register',$payload); test_assert_same(303,$status);
                test_assert_same(1,(int)$pdo->query("SELECT COUNT(*) FROM users WHERE id_number='HTTP-Employee'")->fetchColumn());
                $login=$get('auth/login'); test_assert(preg_match('/name="csrf_token" value="([^"]+)"/',$login,$loginToken)===1);
                [$status,$body]=$post('auth/login',['csrf_token'=>$loginToken[1],'id_number'=>'HTTP-Employee','password'=>'Fixture-Password-42!']);
                test_assert_same(422,$status); test_assert(strpos($body,'inactive')!==false,'Pending registration cannot authenticate.');
                [$status,,$redirect]=$post('users/activate/'.$httpUser['id'],['csrf_token'=>$match[1],'reason'=>'Attempted unauthorized activation']);
                test_assert_same(303,$status); test_assert(strpos($redirect,'auth/login')!==false);
                test_assert_same('inactive',$pdo->query('SELECT status FROM users WHERE id='.(int)$httpUser['id'])->fetchColumn());
                // An invalid invitation is never redisplayed in form-state responses.
                $bad=$payload; $bad['invitation_code']=str_repeat('f',64); $post('auth/register',$bad);
                $reply=$get('auth/register'); test_assert(strpos($reply,str_repeat('f',64))===false);
                test_assert(strpos($reply,'value="'.$http['invitation_code'].'"')===false);
                $pdo->exec('DROP TABLE registration_limit_locks');
                $outage=$payload; $outage['id_number']='Outage'; $outage['email']='outage@example.test';
                [$status]=$post('auth/register',$outage); test_assert_same(303,$status);
                $reply=$get('auth/register'); test_assert(strpos($reply,'We could not complete your registration')!==false);
                test_assert_same(0,(int)$pdo->query("SELECT COUNT(*) FROM users WHERE id_number='Outage'")->fetchColumn());
            } finally {
                curl_close($curl); proc_terminate($server); proc_close($server);
                foreach(glob($httpDir.'/*') as$file) { unlink($file); } rmdir($httpDir);
            }
            $throws(RuntimeException::class,static function() use($service,$values) { $service->register($values('Outage','outage@example.test'),'Fixture-Password-42!',str_repeat('a',64)); },'Registration protection unavailable.');
        } finally { foreach($tables as$table) { $pdo->exec("DROP TABLE IF EXISTS $table"); } }
    },
];
