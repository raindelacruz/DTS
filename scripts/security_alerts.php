<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
$collect=false;
foreach(array_slice($argv,1) as$arg) {
    if($arg==='--help') { echo "php scripts/security_alerts.php [--collect]\nRead-only JSON by default. --collect stores deduplicated local alerts. No external delivery.\n"; exit; }
    if($arg==='--collect') { $collect=true; } else { fwrite(STDERR,"Unknown option.\n"); exit(2); }
}
try {
    require dirname(__DIR__).'/app/init.php';
    require APPROOT.'/lib/SecurityAlertMonitor.php';
    $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>2]);
    $monitor=new SecurityAlertMonitor($pdo);
    $snapshot=$monitor->snapshot(['observe'=>SECURITY_MONITOR_MODE,'authentication'=>AuthenticationGuard::enabled()?'enforce':'off',
        'reset'=>PasswordResetProtection::enabled()?'enforce':'off','registration'=>RegistrationProtection::enabled()?'enforce':'off',
        'administrator'=>AdminSecurity::enabled()?'enforce':'off']);
    if(AdminSecurity::enabled()) { AdminSecurity::configuredPolicy(); }
    $snapshot['new_local_alerts']=$collect ? $monitor->collect($snapshot) : null;
    echo json_encode($snapshot,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
} catch(Throwable $e) {
    if(function_exists('appLog')) { appLog('error','Security alert evaluation unavailable',['exception_class'=>get_class($e)]); }
    fwrite(STDERR,"Security alert evaluation unavailable. Check configuration, database readiness and local logs.\n"); exit(1);
}
