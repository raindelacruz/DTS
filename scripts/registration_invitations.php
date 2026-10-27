<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$action=$argv[1] ?? '--help';
if ($action==='--help') {
    echo "Read JSON from standard input. Approved server operators only.\n";
    echo "issue: administrator_id, id_number, email, department_id, reason, optional hours (1..168)\n";
    echo "revoke: administrator_id, invitation_id, reason\n";
    echo "prune: no input; expires attempt counters only\n";
    exit;
}
try {
    require dirname(__DIR__).'/app/init.php';
    if (!RegistrationProtection::enabled()) { throw new RuntimeException('Protected registration is disabled.'); }
    $service=RegistrationProtection::configured();
    if($action==='prune') { $service->prune(); echo "Counter maintenance completed.\n"; exit; }
    $input=stream_get_contents(STDIN,4097);
    if(strlen($input)>4096) { throw new InvalidArgumentException('Input is too long.'); }
    $data=json_decode($input,true,8,JSON_THROW_ON_ERROR);
    if(!is_array($data)) { throw new InvalidArgumentException('JSON object required.'); }
    foreach($data as $value) { if(!is_scalar($value)) { throw new InvalidArgumentException('Scalar fields required.'); } }
    if($action==='issue') {
        $result=$service->issue((int)($data['administrator_id']??0),(string)($data['id_number']??''),(string)($data['email']??''),
            (int)($data['department_id']??0),(string)($data['reason']??''),(int)($data['hours']??48));
        // Code is intentionally displayed once to the authorized operator, never audited.
        echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
    } elseif($action==='revoke') {
        $service->revoke((int)($data['administrator_id']??0),(int)($data['invitation_id']??0),(string)($data['reason']??''));
        echo "Invitation revoked.\n";
    } else { throw new InvalidArgumentException('Unknown action.'); }
} catch(Throwable $e) {
    if(function_exists('appLog')) { appLog('error','Registration administration failed',['exception_class'=>get_class($e)]); }
    fwrite(STDERR,"Registration administration could not complete. Check eligibility, authorization, policy and database readiness.\n");
    exit(1);
}
