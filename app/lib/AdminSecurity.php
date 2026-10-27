<?php

final class AdminSecurity
{
    private PDO $pdo;
    private AuthenticationGuard $guard;
    private array $policy;

    public function __construct(PDO $pdo, AuthenticationGuard $guard, array $policy)
    {
        $this->pdo=$pdo; $this->guard=$guard; $this->policy=self::validatePolicy($policy);
    }

    public static function enabled(): bool
    {
        $mode=getenv('ADMIN_SECURITY_MODE') ?: 'off';
        if(!in_array($mode,['off','enforce'],true)) { throw new RuntimeException('Invalid administrator security mode.'); }
        if($mode==='enforce' && !AuthenticationGuard::enabled()) { throw new RuntimeException('Administrator protection requires Phase 2 enforcement.'); }
        return $mode==='enforce';
    }

    public static function configuredPolicy(): array
    {
        return self::validatePolicy([
            'personal_ids'=>json_decode(getenv('ADMIN_PERSONAL_ACCOUNT_IDS') ?: '[]',true,8,JSON_THROW_ON_ERROR),
            'networks'=>json_decode(getenv('ADMIN_ALLOWED_NETWORKS') ?: '[]',true,8,JSON_THROW_ON_ERROR),
            'emergency'=>json_decode(getenv('ADMIN_EMERGENCY_ACCOUNT') ?: 'null',true,8,JSON_THROW_ON_ERROR),
        ]);
    }

    private static function validatePolicy(array $policy): array
    {
        $ids=$policy['personal_ids']??null; $networks=$policy['networks']??[]; $emergency=$policy['emergency']??null;
        if(!is_array($ids) || !$ids || !array_is_list($ids) || count($ids)>100) { throw new InvalidArgumentException('Approved personal administrator IDs are required.'); }
        foreach($ids as$id) { if(!is_int($id) || $id<1) { throw new InvalidArgumentException('Invalid administrator ID.'); } }
        if(!is_array($networks) || !array_is_list($networks) || count($networks)>100) { throw new InvalidArgumentException('Invalid administrator networks.'); }
        foreach($networks as$network) { self::networkParts($network); }
        if($emergency!==null && (!is_array($emergency) || !is_int($emergency['user_id']??null) || $emergency['user_id']<1
            || !is_int($emergency['expires_at']??null) || !is_string($emergency['authorization_reference']??null)
            || strlen($emergency['authorization_reference'])<10 || strlen($emergency['authorization_reference'])>200)) {
            throw new InvalidArgumentException('Invalid emergency account authorization.');
        }
        if($emergency!==null && in_array($emergency['user_id'],$ids,true)) { throw new InvalidArgumentException('Emergency account must be separate from personal accounts.'); }
        return ['personal_ids'=>array_values(array_unique($ids)),'networks'=>$networks,'emergency'=>$emergency];
    }

    private static function networkParts($network): array
    {
        if(!is_string($network)) { throw new InvalidArgumentException('Invalid network.'); }
        $parts=explode('/',$network);
        $packed=@inet_pton($parts[0]);
        if($packed===false || count($parts)>2) { throw new InvalidArgumentException('Invalid network.'); }
        $bits=strlen($packed)*8;
        if(isset($parts[1]) && (!ctype_digit($parts[1]) || (int)$parts[1]>$bits)) { throw new InvalidArgumentException('Invalid prefix.'); }
        return [$packed,isset($parts[1])?(int)$parts[1]:$bits];
    }

    public static function networkMatches(string $ip,string $network): bool
    {
        [$base,$prefix]=self::networkParts($network); $address=@inet_pton($ip);
        if($address===false || strlen($address)!==strlen($base)) { return false; }
        $whole=intdiv($prefix,8); $rest=$prefix%8;
        if(substr($address,0,$whole)!==substr($base,0,$whole)) { return false; }
        return $rest===0 || ((ord($address[$whole]) & (255 << (8-$rest))) === (ord($base[$whole]) & (255 << (8-$rest))));
    }

    public static function access(array $session,string $ip,int $now,array $policy): string
    {
        $policy=self::validatePolicy($policy);
        if(($session['role']??'')!=='admin' || empty($session['mfa_verified'])) { throw new RuntimeException('Verified administrator session required.'); }
        if($policy['networks']) {
            $allowed=false; foreach($policy['networks'] as$network) { if(self::networkMatches($ip,$network)) { $allowed=true; break; } }
            if(!$allowed) { throw new RuntimeException('Administrator network is not approved.'); }
        }
        $id=(int)($session['user_id']??0);
        if(in_array($id,$policy['personal_ids'],true)) { return 'personal'; }
        $e=$policy['emergency'];
        if($e && $e['user_id']===$id && $e['expires_at']>$now && $e['expires_at']<=$now+86400) { return 'emergency'; }
        throw new RuntimeException('Administrator account is not approved.');
    }

    public static function challenge(array &$session,string $action,int $target,int $now): string
    {
        $allowed=['role','department','password','mfa_reset','status'];
        if(!in_array($action,$allowed,true) || $target<1) { throw new InvalidArgumentException('Invalid administrator action.'); }
        $pending=$session['admin_challenges']??[];
        foreach($pending as$id=>$c) { if(($c['started']??0)<=$now-300) { unset($pending[$id]); } }
        while(count($pending)>=10) { array_shift($pending); }
        $id=bin2hex(random_bytes(16));
        $pending[$id]=['action'=>$action,'target'=>$target,'user'=>(int)($session['user_id']??0),
            'version'=>(int)($session['session_version']??-1),'started'=>$now,'attempts'=>0];
        $session['admin_challenges']=$pending; return $id;
    }

    public static function challengeValid(array $session,string $id,string $action,int $target,int $now): bool
    {
        $c=$session['admin_challenges'][$id]??null;
        return is_array($c) && $c['action']===$action && $c['target']===$target && $c['user']===(int)($session['user_id']??0)
            && $c['version']===(int)($session['session_version']??-1) && $c['started']<=$now && $now-$c['started']<300 && $c['attempts']<5;
    }

    public static function configured(): self
    {
        $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>2]);
        return new self($pdo,AuthenticationGuard::configured(),self::configuredPolicy());
    }

    public function audit(string $event,int $actor,?int $target,array $metadata): void
    {
        $client=clientRequestContext();
        $metadata+=['request_id'=>securityRequestId(),'peer_ip'=>$client['peer_ip'],'source'=>$client['source'],'forwarded_status'=>$client['forwarded_status']];
        $q=$this->pdo->prepare('INSERT INTO security_audit_log (event_type,actor_user_id,target_user_id,ip_address,user_agent,metadata) VALUES (?,?,?,?,?,?)');
        $q->execute([$event,$actor,$target,$client['ip'],substr((string)($_SERVER['HTTP_USER_AGENT']??'CLI'),0,500),json_encode($metadata,JSON_THROW_ON_ERROR)]);
    }

    public function authorize(array &$session,string $action,int $target,string $challenge,string $password,string $code,string $ip,string $reason=''): void
    {
        $now=(int)$this->pdo->query('SELECT UNIX_TIMESTAMP()')->fetchColumn();
        $access=self::access($session,$ip,$now,$this->policy);
        if(!self::challengeValid($session,$challenge,$action,$target,$now)) { throw new ValidationException('This administrator form expired or was already used. Reload it before trying again.'); }
        if(in_array($action,['password','mfa_reset'],true) && (strlen(trim($reason))<10 || strlen($reason)>500)) {
            throw new ValidationException('Record the independently verified recovery reference before proceeding.');
        }
        $session['admin_challenges'][$challenge]['attempts']++;
        $actor=(int)$session['user_id'];
        $q=$this->pdo->prepare("SELECT id_number,password,mfa_secret,session_version FROM users WHERE id=? AND role='admin' AND status='active' AND mfa_enabled=1");
        $q->execute([$actor]); $user=$q->fetch(PDO::FETCH_ASSOC);
        if(!$user || (int)$user['session_version']!==(int)$session['session_version']) { throw new ValidationException('Sign in again before making administrator changes.'); }
        $result=$this->guard->check('login',$user['id_number'],static function() use($password,$user) { return password_verify($password,$user['password']) ? true : false; });
        if($result['status']!=='accepted') {
            $this->audit('admin_reauthentication_failed',$actor,$target,['action'=>$action,'stage'=>'password','retry_seconds'=>$result['retry']]);
            throw new ValidationException('Administrator verification failed. Try again later'.($result['retry']>0?' after '.$result['retry'].' seconds':'').'.');
        }
        $secret=decryptSensitiveValue($user['mfa_secret']);
        $result=$this->guard->check('mfa','user:'.$actor,static function($clock) use($secret,$code) { return $secret==='' ? false : TotpService::matchingStep($secret,$code,$clock); },$secret);
        if($result['status']!=='accepted') {
            $this->audit('admin_reauthentication_failed',$actor,$target,['action'=>$action,'stage'=>'mfa','retry_seconds'=>$result['retry']]);
            throw new ValidationException('Administrator verification failed. Use a fresh authenticator code and try again'.($result['retry']>0?' after '.$result['retry'].' seconds':'').'.');
        }
        // Proof is one-use for this request/action/target, never a reusable privileged session grant.
        unset($session['admin_challenges'][$challenge]);
        $this->audit('admin_action_authorized',$actor,$target,['action'=>$action,'access'=>$access,'verification_reference'=>trim($reason)]);
    }
}
