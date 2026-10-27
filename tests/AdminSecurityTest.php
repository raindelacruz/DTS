<?php
return [
    'administrator network matching account policy emergency expiry and bound challenge' => function($root) {
        require_once $root.'/app/init.php';
        test_assert(AdminSecurity::networkMatches('192.0.2.17','192.0.2.0/24'));
        test_assert(!AdminSecurity::networkMatches('192.0.3.17','192.0.2.0/24'));
        test_assert(AdminSecurity::networkMatches('2001:db8::5','2001:db8::/32'));
        test_assert(!AdminSecurity::networkMatches('2001:db9::5','2001:db8::/32'));
        test_assert(AdminSecurity::networkMatches('::1','0:0:0:0:0:0:0:1/128'));
        $policy=['personal_ids'=>[1],'networks'=>['192.0.2.0/24'],'emergency'=>['user_id'=>2,'expires_at'=>2000,'authorization_reference'=>'Approved recovery ticket 42']];
        $s=['user_id'=>1,'role'=>'admin','mfa_verified'=>true,'session_version'=>4];
        test_assert_same('personal',AdminSecurity::access($s,'192.0.2.1',1000,$policy));
        $s['user_id']=2; test_assert_same('emergency',AdminSecurity::access($s,'192.0.2.1',1000,$policy));
        $error=null; try { AdminSecurity::access($s,'192.0.2.1',2000,$policy); } catch(Throwable $e) { $error=$e; } test_assert($error instanceof RuntimeException);
        $error=null; try { AdminSecurity::access($s,'198.51.100.1',1000,$policy); } catch(Throwable $e) { $error=$e; } test_assert($error instanceof RuntimeException);
        $id=AdminSecurity::challenge($s,'role',9,1000);
        test_assert(AdminSecurity::challengeValid($s,$id,'role',9,1299));
        test_assert(!AdminSecurity::challengeValid($s,$id,'password',9,1001));
        test_assert(!AdminSecurity::challengeValid($s,$id,'role',8,1001));
        test_assert(!AdminSecurity::challengeValid($s,$id,'role',9,1300));
        $s['session_version']=5; test_assert(!AdminSecurity::challengeValid($s,$id,'role',9,1001));
    },
    'administrator reauthentication uses shared counters rejects replay and fails closed on audit outage' => function($root) {
        if(getenv('RUN_SECURITY_DB_TESTS')!=='1') { test_skip('Requires isolated Phase 5 database.'); }
        test_assert_same('dts_security_test_phase5',getenv('SECURITY_TEST_DB_NAME'));
        require_once $root.'/app/init.php';
        $connect=static function() { return new PDO('mysql:host='.getenv('SECURITY_TEST_DB_HOST').';port='.getenv('SECURITY_TEST_DB_PORT').';dbname='.getenv('SECURITY_TEST_DB_NAME'),
            getenv('SECURITY_TEST_DB_USER'),getenv('SECURITY_TEST_DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); };
        $pdo=$connect(); $guardPdo=$connect();
        $tables=['authentication_guard','security_operational_alerts','security_audit_log','users','password_reset_outbox','security_request_counters','departments','notifications','user_access_change_history'];
        foreach($tables as$table) { $pdo->exec("DROP TABLE IF EXISTS $table"); }
        try {
            $pdo->exec("CREATE TABLE users (id INT PRIMARY KEY,id_number VARCHAR(50),password VARCHAR(255),mfa_secret TEXT,mfa_enabled TINYINT,session_version INT,role VARCHAR(16),status VARCHAR(16),
                firstname VARCHAR(100) DEFAULT 'Fixture',lastname VARCHAR(100) DEFAULT 'Employee',email VARCHAR(150) DEFAULT 'fixture@example.test',department_id INT DEFAULT 1,
                last_login_at DATETIME NULL,password_changed_at DATETIME NULL,must_change_password TINYINT DEFAULT 0) ENGINE=InnoDB");
            $pdo->exec("CREATE TABLE security_audit_log (id INT AUTO_INCREMENT PRIMARY KEY,event_type VARCHAR(64),actor_user_id INT NULL,target_user_id INT NULL,ip_address VARCHAR(45),user_agent VARCHAR(500),metadata LONGTEXT,created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
            $migration=require $root.'/database/migrations/20261010_0018_authentication_guard.php'; $migration($pdo);
            $migration=require $root.'/database/migrations/20261010_0021_security_operational_alerts.php'; $migration($pdo);
            $secret=TotpService::generateSecret();
            $q=$pdo->prepare("INSERT INTO users (id,id_number,password,mfa_secret,mfa_enabled,session_version,role,status) VALUES (1,'PERSONAL-ADMIN',?,?,1,4,'admin','active'),(2,'SYSADMIN',?,?,1,4,'admin','active')");
            $q->execute([password_hash('Fixture-Password-42!',PASSWORD_DEFAULT),encryptSensitiveValue($secret),password_hash('Fixture-Password-42!',PASSWORD_DEFAULT),encryptSensitiveValue($secret)]);
            $guard=new AuthenticationGuard($guardPdo,'fixture-key');
            $policy=['personal_ids'=>[1],'networks'=>[],'emergency'=>null];
            $service=new AdminSecurity($pdo,$guard,$policy);
            $throws=static function(callable $action,string $class) { $error=null; try { $action(); } catch(Throwable $e) { $error=$e; } test_assert($error instanceof $class,'Expected '.$class); };
            $s=['user_id'=>1,'role'=>'admin','session_version'=>4,'mfa_verified'=>true];
            $method=new ReflectionMethod(TotpService::class,'code'); $method->setAccessible(true);
            // Use the previous accepted step, leaving the current step available for replay/outage checks.
            $now=(int)$pdo->query('SELECT UNIX_TIMESTAMP()')->fetchColumn();
            $code=$method->invoke(null,$secret,intdiv($now,30)-1);
            $challenge=AdminSecurity::challenge($s,'role',9,time());
            $service->authorize($s,'role',9,$challenge,'Fixture-Password-42!',$code,'192.0.2.1');
            test_assert(!isset($s['admin_challenges'][$challenge]));
            test_assert_same(1,(int)$pdo->query("SELECT COUNT(*) FROM security_audit_log WHERE event_type='admin_action_authorized'")->fetchColumn());
            $throws(static function() use($service,&$s,$challenge,$code) { $service->authorize($s,'role',9,$challenge,'Fixture-Password-42!',$code,'192.0.2.1'); },ValidationException::class);
            $challenge=AdminSecurity::challenge($s,'role',9,time());
            $throws(static function() use($service,&$s,$challenge,$code) { $service->authorize($s,'role',9,$challenge,'Fixture-Password-42!',$code,'192.0.2.1'); },ValidationException::class);
            $pdo->exec('UPDATE authentication_guard SET retry_at=0');
            $pdo->exec("CREATE TRIGGER admin_audit_failure BEFORE INSERT ON security_audit_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture audit failure'");
            $fresh=$method->invoke(null,$secret,intdiv((int)$pdo->query('SELECT UNIX_TIMESTAMP()')->fetchColumn(),30));
            $challenge=AdminSecurity::challenge($s,'password',9,time());
            $throws(static function() use($service,&$s,$challenge,$fresh) { $service->authorize($s,'password',9,$challenge,'Fixture-Password-42!',$fresh,'192.0.2.1','Independent verified recovery ticket 42'); },PDOException::class);
            test_assert(!isset($s['admin_challenges'][$challenge]),'Failed strict audit must not leave reusable proof.');
            $pdo->exec('DROP TRIGGER admin_audit_failure');
            for($i=0;$i<5;$i++) {
                $challenge=AdminSecurity::challenge($s,'role',9,time());
                $throws(static function() use($service,&$s,$challenge) { $service->authorize($s,'role',9,$challenge,'Wrong-Password-42!','000000','192.0.2.1'); },ValidationException::class);
            }
            $challenge=AdminSecurity::challenge($s,'role',9,time());
            $throws(static function() use($service,&$s,$challenge,$fresh) { $service->authorize($s,'role',9,$challenge,'Fixture-Password-42!',$fresh,'192.0.2.1'); },ValidationException::class);
            test_assert_same(1,(int)$pdo->query("SELECT COUNT(*) FROM security_audit_log WHERE event_type='admin_action_authorized'")->fetchColumn(),'Fresh challenges cannot erase shared password failure history.');
            $metadata=implode('',$pdo->query('SELECT metadata FROM security_audit_log')->fetchAll(PDO::FETCH_COLUMN));
            test_assert(strpos($metadata,'Fixture-Password-42!')===false && strpos($metadata,$secret)===false && strpos($metadata,'"'.$code.'"')===false);

            require_once $root.'/app/lib/SecurityAlertMonitor.php';
            $pdo->exec("INSERT INTO security_audit_log(event_type,target_user_id,metadata) VALUES ('mfa_failed',1,'{}'),('mfa_failed',1,'{}'),('mfa_failed',1,'{}'),('mfa_failed',1,'{}'),('mfa_failed',1,'{}')");
            $monitor=new SecurityAlertMonitor($pdo);
            $snapshot=$monitor->snapshot(['observe'=>'off','authentication'=>'enforce','reset'=>'off','registration'=>'off']);
            $rules=array_column($snapshot['alerts'],'rule'); test_assert(in_array('repeated_mfa_failures',$rules,true)); test_assert(in_array('administrator_ownership_review',$rules,true));
            $new=$monitor->collect($snapshot); test_assert($new>0);
            test_assert_same(0,$monitor->collect($snapshot),'Repeated scans deduplicate local notifications.');
            test_assert_same($new,(int)$pdo->query('SELECT COUNT(*) FROM security_operational_alerts')->fetchColumn());
            $snapshot=$monitor->snapshot(['observe'=>'off','authentication'=>'enforce','reset'=>'enforce','registration'=>'enforce']);
            test_assert(in_array('protection_schema_unavailable',array_column($snapshot['alerts'],'rule'),true),'Enabled protections with missing schema must be visible.');
            $pdo->exec('DROP TABLE security_operational_alerts');
            $throws(static function() use($monitor,$snapshot) { $monitor->collect($snapshot); },PDOException::class);

            // Exercise protected administrator mutation through actual HTTP and a trusted-device login.
            $pdo->exec('DELETE FROM authentication_guard');
            $pdo->exec("CREATE TABLE departments(id INT PRIMARY KEY,parent_id INT NULL,division_name VARCHAR(100),department_name VARCHAR(100),code VARCHAR(20),office_level VARCHAR(20) DEFAULT 'department',can_release TINYINT DEFAULT 1,INDEX idx_departments_office_level(office_level)) ENGINE=InnoDB");
            $pdo->exec("INSERT INTO departments(id,division_name,department_name,code) VALUES(1,'Test Office','Test Office','TEST')");
            $pdo->exec("CREATE TABLE notifications(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,title VARCHAR(255),message TEXT,link VARCHAR(255),is_read TINYINT DEFAULT 0,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,read_at DATETIME NULL) ENGINE=InnoDB");
            $httpDir=str_replace('\\','/',$root).'/storage/logs/dts_admin_http_'.bin2hex(random_bytes(6)); mkdir($httpDir);
            $environment=getenv();
            $environment['APP_ENV']='development'; $environment['APP_URL']='http://127.0.0.1:3318';
            $environment['DB_HOST']=getenv('SECURITY_TEST_DB_HOST').';port='.getenv('SECURITY_TEST_DB_PORT');
            $environment['DB_NAME']=getenv('SECURITY_TEST_DB_NAME'); $environment['DB_USER']=getenv('SECURITY_TEST_DB_USER');
            $environment['DB_PASS']=getenv('SECURITY_TEST_DB_PASS') ?: ''; $environment['APP_KEY']=APP_KEY;
            $environment['ADMIN_SECURITY_MODE']='enforce'; $environment['ADMIN_PERSONAL_ACCOUNT_IDS']='[1]';
            $environment['ADMIN_ALLOWED_NETWORKS']='["127.0.0.1/32"]'; $environment['ADMIN_EMERGENCY_ACCOUNT']='null';
            $environment['AUTH_GUARD_MODE']='enforce'; $environment['REGISTRATION_GUARD_MODE']='off';
            $environment['PASSWORD_RESET_GUARD_MODE']='off'; $environment['SECURITY_MONITOR_MODE']='off';
            $environment['SMTP_HOST']=''; $environment['SMTP_USER']=''; $environment['SMTP_PASS']='';
            $pipes=[]; $server=proc_open([PHP_BINARY,'-d','session.save_path='.$httpDir,'-S','127.0.0.1:3318','-t',$root.'/public'],
                [0=>['pipe','r'],1=>['file',$httpDir.'/server.log','a'],2=>['file',$httpDir.'/server.log','a']],$pipes,$root,$environment); fclose($pipes[0]);
            $previousAgent=$_SERVER['HTTP_USER_AGENT']??null; $_SERVER['HTTP_USER_AGENT']='DTS-phase5-test';
            $security=(new ReflectionClass(SecurityService::class))->newInstanceWithoutConstructor();
            $cookieMethod=new ReflectionMethod(SecurityService::class,'signedTrustedMfaCookie'); $cookieMethod->setAccessible(true);
            $cookie=$cookieMethod->invoke($security,1,4,'fixture-cookie');
            $curl=curl_init(); curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>3,CURLOPT_USERAGENT=>'DTS-phase5-test',CURLOPT_COOKIE=>MFA_TRUSTED_DEVICE_COOKIE.'='.$cookie]);
            $get=static function($route) use($curl) { curl_setopt_array($curl,[CURLOPT_URL=>'http://127.0.0.1:3318/index.php?url='.$route,CURLOPT_POST=>false]); return curl_exec($curl); };
            $post=static function($route,$payload) use($curl) {
                curl_setopt_array($curl,[CURLOPT_URL=>'http://127.0.0.1:3318/index.php?url='.$route,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($payload)]);
                $body=curl_exec($curl); return [(int)curl_getinfo($curl,CURLINFO_HTTP_CODE),$body,curl_getinfo($curl,CURLINFO_REDIRECT_URL)];
            };
            try {
                $form=false; for($i=0;$i<30;$i++) { $form=$get('auth/login'); if($form!==false) { break; } usleep(100000); }
                test_assert(is_string($form)); test_assert(preg_match('/name="csrf_token" value="([^"]+)"/',$form,$token)===1);
                [$status,,$redirect]=$post('auth/login',['csrf_token'=>$token[1],'id_number'=>'PERSONAL-ADMIN','password'=>'Fixture-Password-42!']);
                test_assert_same(303,$status); test_assert(strpos($redirect,'dashboard')!==false,'Trusted-device login should still work.');
                $page=$get('users/show/2'); test_assert_same(200,(int)curl_getinfo($curl,CURLINFO_HTTP_CODE));
                test_assert(preg_match('#<form action="[^"]*/users/updateRole/2".*?</form>#s',$page,$roleForm)===1,'Role form must render.');
                test_assert(preg_match('/name="csrf_token" value="([^"]+)"/',$roleForm[0],$token)===1);
                test_assert(preg_match('/name="admin_challenge" value="([^"]+)"/',$roleForm[0],$proof)===1);
                [$status]=$post('users/updateRole/2',['csrf_token'=>$token[1],'role'=>'staff']); test_assert_same(303,$status);
                test_assert_same('admin',$pdo->query('SELECT role FROM users WHERE id=2')->fetchColumn(),'Trusted device is not privileged reauthentication.');
                $clock=(int)$pdo->query('SELECT UNIX_TIMESTAMP()')->fetchColumn(); $fresh=$method->invoke(null,$secret,intdiv($clock,30));
                $payload=['csrf_token'=>$token[1],'role'=>'staff','admin_challenge'=>$proof[1],'admin_password'=>'Fixture-Password-42!','admin_code'=>$fresh];
                [$status]=$post('users/updateRole/2',$payload); test_assert_same(303,$status);
                test_assert_same('staff',$pdo->query('SELECT role FROM users WHERE id=2')->fetchColumn(),'Fresh proof permits the authorized role change.');
                $payload['role']='manager'; $post('users/updateRole/2',$payload);
                test_assert_same('staff',$pdo->query('SELECT role FROM users WHERE id=2')->fetchColumn(),'Replayed form cannot change the role again.');
                $page=$get('users/show/2'); preg_match('#<form action="[^"]*/users/updateRole/2".*?</form>#s',$page,$roleForm);
                preg_match('/name="admin_challenge" value="([^"]+)"/',$roleForm[0],$proof);
                $pdo->exec("CREATE TRIGGER admin_authorization_outage BEFORE INSERT ON security_audit_log FOR EACH ROW BEGIN IF NEW.event_type='admin_action_authorized' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture authorization outage'; END IF; END");
                $payload['admin_challenge']=$proof[1]; $payload['admin_code']=$method->invoke(null,$secret,intdiv((int)$pdo->query('SELECT UNIX_TIMESTAMP()')->fetchColumn(),30)+1);
                $post('users/updateRole/2',$payload); test_assert_same('staff',$pdo->query('SELECT role FROM users WHERE id=2')->fetchColumn(),'Strict authorization audit outage prevents mutation.');
                $pdo->exec('DROP TRIGGER admin_authorization_outage');
            } finally {
                curl_close($curl); proc_terminate($server); proc_close($server);
                foreach(glob($httpDir.'/*') as$file) { unlink($file); } rmdir($httpDir);
                if($previousAgent===null) { unset($_SERVER['HTTP_USER_AGENT']); } else { $_SERVER['HTTP_USER_AGENT']=$previousAgent; }
            }
            $pdo->exec("CREATE TABLE password_reset_outbox(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,status VARCHAR(16),expires_at BIGINT,claimed_at BIGINT NULL) ENGINE=InnoDB");
            $pdo->exec("INSERT INTO password_reset_outbox(user_id,status,expires_at,claimed_at) VALUES
                (1,'queued',UNIX_TIMESTAMP()-1,NULL),(1,'sending',UNIX_TIMESTAMP()+1000,UNIX_TIMESTAMP()-301),
                (1,'accepted',UNIX_TIMESTAMP()+1800,NULL),(1,'accepted',UNIX_TIMESTAMP()+1800,NULL),(1,'accepted',UNIX_TIMESTAMP()+1800,NULL)");
            for($i=0;$i<10;$i++) { $pdo->exec("INSERT INTO security_audit_log(event_type,metadata) VALUES('account_registered','{}')"); }
            $pdo->exec("INSERT INTO security_audit_log(event_type,metadata) VALUES('password_reset_guard_processed','malformed')");
            $snapshot=$monitor->snapshot(['observe'=>'off','authentication'=>'enforce','reset'=>'off','registration'=>'off']);
            $rules=array_column($snapshot['alerts'],'rule');
            foreach(['privileged_account_changes','reset_mail_expired_queue','reset_mail_stalled_sender','repeated_queued_resets','registration_burst'] as$rule) { test_assert(in_array($rule,$rules,true),'Expected alert '.$rule); }
            $migration=require $root.'/database/migrations/20261010_0021_security_operational_alerts.php'; $migration($pdo);
            $new=$monitor->collect($snapshot); test_assert($new>0); test_assert_same(0,$monitor->collect($snapshot));
        } finally { foreach($tables as$table) { $pdo->exec("DROP TABLE IF EXISTS $table"); } }
    },
];
