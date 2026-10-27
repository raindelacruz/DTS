<?php

final class RegistrationProtection
{
    private PDO $pdo;
    private string $key;

    public function __construct(PDO $pdo, string $key)
    {
        if ($key === '') { throw new InvalidArgumentException('Missing registration key.'); }
        $engines=$pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('users','notifications','security_audit_log')")->fetchAll(PDO::FETCH_COLUMN);
        if (count($engines)!==3 || array_filter($engines,static function ($e) { return strtoupper((string)$e)!=='INNODB'; })) {
            throw new RuntimeException('Registration requires transactional account, notification and audit tables.');
        }
        $indexes=$pdo->query("SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND NON_UNIQUE=0 GROUP BY INDEX_NAME")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('id_number',$indexes,true) || !in_array('email',$indexes,true)) {
            throw new RuntimeException('Registration requires unique employee ID and email indexes.');
        }
        $this->pdo=$pdo; $this->key=$key;
    }

    public static function enabled(): bool
    {
        $mode=getenv('REGISTRATION_GUARD_MODE') ?: 'off';
        if (!in_array($mode,['off','enforce'],true)) { throw new RuntimeException('Invalid registration guard mode.'); }
        if ($mode==='enforce' && getenv('REGISTRATION_ELIGIBILITY_MODEL')!=='invitation') {
            throw new RuntimeException('An approved registration eligibility model is required.');
        }
        return $mode==='enforce';
    }

    public static function configured(): self
    {
        $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>2]);
        $pdo->exec('SET SESSION innodb_lock_wait_timeout=2');
        return new self($pdo,APP_KEY);
    }

    public static function validId(string $id): bool
    {
        // Preserve approved spelling and case; invitation binding supplies eligibility.
        if (!preg_match('/\A[^\x00-\x1F\x7F]{1,50}\z/u',$id)) { return false; }
        $pattern=getenv('REGISTRATION_ID_PATTERN') ?: '';
        if ($pattern==='') { return true; }
        if (strlen($pattern)>256 || @preg_match($pattern,'')===false) { throw new RuntimeException('Invalid employee ID policy.'); }
        return preg_match($pattern,$id,$match)===1 && $match[0]===$id;
    }

    private function admin(int $id): void
    {
        $q=$this->pdo->prepare("SELECT id FROM users WHERE id=? AND role='admin' AND status='active' AND mfa_enabled=1 FOR UPDATE");
        $q->execute([$id]);
        if (!$q->fetchColumn()) { throw new RuntimeException('An active MFA-enabled administrator is required.'); }
    }

    private function audit(string $event, ?int $actor, ?int $target, array $metadata): void
    {
        $client=function_exists('clientRequestContext') ? clientRequestContext() : ['ip'=>'0.0.0.0','peer_ip'=>'0.0.0.0','source'=>'direct_peer','forwarded_status'=>'absent'];
        $metadata+=['request_id'=>function_exists('securityRequestId') ? securityRequestId() : bin2hex(random_bytes(12)),
            'peer_ip'=>$client['peer_ip'],'source'=>$client['source'],'forwarded_status'=>$client['forwarded_status']];
        $q=$this->pdo->prepare('INSERT INTO security_audit_log (event_type,actor_user_id,target_user_id,ip_address,user_agent,metadata) VALUES (?,?,?,?,?,?)');
        $q->execute([$event,$actor,$target,$client['ip'],substr((string)($_SERVER['HTTP_USER_AGENT']??'CLI'),0,500),json_encode($metadata,JSON_THROW_ON_ERROR)]);
    }

    public function issue(int $actor,string $id,string $email,int $department,string $reason,int $hours=48): array
    {
        $id=trim($id); $email=strtolower(trim($email)); $reason=trim($reason);
        if (!self::validId($id) || strlen($email)>150 || !filter_var($email,FILTER_VALIDATE_EMAIL)
            || strlen($reason)<10 || strlen($reason)>500 || $hours<1 || $hours>168 || $department<1) {
            throw new InvalidArgumentException('Check employee identity, email, department, eligibility note and expiry.');
        }
        if ($this->pdo->inTransaction()) { throw new LogicException('Registration owns its transaction.'); }
        $this->pdo->beginTransaction();
        try {
            $this->admin($actor);
            $q=$this->pdo->prepare('SELECT id FROM departments WHERE id=?'); $q->execute([$department]);
            if (!$q->fetchColumn()) { throw new InvalidArgumentException('Department does not exist.'); }
            $q=$this->pdo->prepare('SELECT id FROM users WHERE id_number=? OR email=? LIMIT 1'); $q->execute([$id,$email]);
            if ($q->fetchColumn()) { throw new InvalidArgumentException('An account already uses this ID or email.'); }
            // New issuance supersedes outstanding invitations for this employee/email.
            $q=$this->pdo->prepare('UPDATE registration_invitations SET revoked_at=UNIX_TIMESTAMP() WHERE (id_number=? OR email=?) AND used_at IS NULL AND revoked_at IS NULL');
            $q->execute([$id,$email]);
            $token=bin2hex(random_bytes(32));
            $q=$this->pdo->prepare('INSERT INTO registration_invitations (token_hash,id_number,email,department_id,issued_by,issued_reason,expires_at) VALUES (?,?,?,?,?,?,UNIX_TIMESTAMP()+?)');
            $q->execute([hash('sha256',$token),$id,$email,$department,$actor,$reason,$hours*3600]);
            $invitation=(int)$this->pdo->lastInsertId();
            $this->audit('registration_invitation_issued',$actor,null,['invitation_id'=>$invitation,'expiry_hours'=>$hours,'eligibility_note'=>$reason]);
            $this->pdo->commit();
            return ['invitation_id'=>$invitation,'invitation_code'=>$token];
        } catch (Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    public function revoke(int $actor,int $invitation,string $reason): void
    {
        if (strlen(trim($reason))<10 || strlen($reason)>500) { throw new InvalidArgumentException('A review note is required.'); }
        if ($this->pdo->inTransaction()) { throw new LogicException('Registration owns its transaction.'); }
        $this->pdo->beginTransaction();
        try {
            $this->admin($actor);
            $q=$this->pdo->prepare('UPDATE registration_invitations SET revoked_at=UNIX_TIMESTAMP() WHERE id=? AND used_at IS NULL AND revoked_at IS NULL'); $q->execute([$invitation]);
            if ($q->rowCount()!==1) { throw new InvalidArgumentException('Unused invitation not found.'); }
            $this->audit('registration_invitation_revoked',$actor,null,['invitation_id'=>$invitation,'reason'=>trim($reason)]);
            $this->pdo->commit();
        } catch (Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    /** Quotas count evaluated registration submissions, including bad invitation codes. */
    public function register(array $values,string $password,string $code): array
    {
        $id=trim((string)($values['id_number']??'')); $email=strtolower(trim((string)($values['email']??'')));
        $first=trim((string)($values['firstname']??'')); $last=trim((string)($values['lastname']??'')); $department=(int)($values['department_id']??0);
        if (!self::validId($id) || strlen($email)>150 || !filter_var($email,FILTER_VALIDATE_EMAIL)
            || !preg_match('/\A[^\x00-\x1F\x7F]{1,100}\z/u',$first) || !preg_match('/\A[^\x00-\x1F\x7F]{1,100}\z/u',$last)
            || $department<1 || (function_exists('validatePasswordPolicy') && validatePasswordPolicy($password)!==[])) {
            return ['status'=>'invalid'];
        }
        if ($this->pdo->inTransaction()) { throw new LogicException('Registration owns its transaction.'); }
        $buckets=[hash_hmac('sha256','global',$this->key)=>[100,3600],hash_hmac('sha256','email|'.$email,$this->key)=>[5,900],
            hash_hmac('sha256','id|'.strtolower($id),$this->key)=>[5,900],hash_hmac('sha256','invite|'.$code,$this->key)=>[5,900]];
        $global=hash_hmac('sha256','global',$this->key);
        $globalPolicy=$buckets[$global]; unset($buckets[$global]);
        ksort($buckets);
        $buckets=[$global=>$globalPolicy]+$buckets;
        $this->pdo->beginTransaction();
        try {
            $now=(int)$this->pdo->query('SELECT UNIX_TIMESTAMP()')->fetchColumn();
            foreach($buckets as $hash=>[$limit,$window]) {
                $q=$this->pdo->prepare('INSERT INTO registration_limit_locks (bucket_hash,touched_at) VALUES (?,?) ON DUPLICATE KEY UPDATE touched_at=VALUES(touched_at)'); $q->execute([$hash,$now]);
                $q=$this->pdo->prepare('SELECT COUNT(*) FROM registration_attempts WHERE bucket_hash=? AND attempted_at>?'); $q->execute([$hash,$now-$window]);
                if ((int)$q->fetchColumn()>=$limit) {
                    // Do not retain new subject rows from a rejected submission.
                    $this->pdo->rollBack();
                    $this->audit('registration_limited',null,null,['scope'=>$limit===100?'global':'subject','window_seconds'=>$window]);
                    return ['status'=>'limited'];
                }
            }
            foreach($buckets as $hash=>[$limit,$window]) { $q=$this->pdo->prepare('INSERT INTO registration_attempts (bucket_hash,attempted_at) VALUES (?,?)'); $q->execute([$hash,$now]); }
            $q=$this->pdo->prepare('SELECT * FROM registration_invitations WHERE token_hash=? FOR UPDATE'); $q->execute([hash('sha256',$code)]); $invite=$q->fetch(PDO::FETCH_ASSOC);
            if (!preg_match('/^[a-f0-9]{64}$/D',$code) || !$invite || $invite['revoked_at']!==null || (int)$invite['expires_at']<=$now
                || $invite['id_number']!==$id || $invite['email']!==$email || (int)$invite['department_id']!==$department) {
                $this->audit('registration_invitation_rejected',null,null,['reason'=>'invalid_or_mismatched']);
                $this->pdo->commit(); return ['status'=>'invalid'];
            }
            $q=$this->pdo->prepare('SELECT id FROM users WHERE id_number=? OR email=? LIMIT 1'); $q->execute([$id,$email]); $existing=$q->fetchColumn();
            if ($invite['used_at']!==null || $existing!==false) {
                $this->audit('registration_duplicate_suppressed',null,null,['invitation_id'=>(int)$invite['id']]);
                $this->pdo->commit(); return ['status'=>'duplicate'];
            }
            $q=$this->pdo->prepare('SELECT id FROM departments WHERE id=?'); $q->execute([$department]);
            if (!$q->fetchColumn()) { $this->pdo->commit(); return ['status'=>'invalid']; }
            $q=$this->pdo->prepare("INSERT INTO users (id_number,firstname,lastname,email,department_id,password,role,status) VALUES (?,?,?,?,?,?,'staff','inactive')");
            $q->execute([$id,$first,$last,$email,$department,password_hash($password,PASSWORD_DEFAULT)]); $user=(int)$this->pdo->lastInsertId();
            $q=$this->pdo->prepare('UPDATE registration_invitations SET used_by=?,used_at=? WHERE id=?'); $q->execute([$user,$now,$invite['id']]);
            $q=$this->pdo->prepare('INSERT INTO registration_reviews (user_id,invitation_id) VALUES (?,?)'); $q->execute([$user,$invite['id']]);
            $q=$this->pdo->prepare('INSERT INTO registration_notice_state (id,next_notice_at) VALUES (1,0) ON DUPLICATE KEY UPDATE id=VALUES(id)'); $q->execute();
            $next=(int)$this->pdo->query('SELECT next_notice_at FROM registration_notice_state WHERE id=1 FOR UPDATE')->fetchColumn();
            if($next<=$now) {
                $this->pdo->exec("INSERT INTO notifications (user_id,title,message,link) SELECT id,'Registrations awaiting review','New employee registrations are awaiting administrator review. Open User Management.','/users' FROM users WHERE role='admin' AND status='active'");
                $q=$this->pdo->prepare('UPDATE registration_notice_state SET next_notice_at=? WHERE id=1'); $q->execute([$now+900]);
            }
            $this->audit('account_registered',null,$user,['eligibility'=>'invitation','invitation_id'=>(int)$invite['id'],'review'=>'pending']);
            $this->pdo->commit(); return ['status'=>'accepted','user_id'=>$user];
        } catch (Throwable $e) {
            if($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw new RuntimeException('Registration protection unavailable.',0,$e);
        }
    }

    public function reviewRecord(int $user): ?array
    {
        $q=$this->pdo->prepare('SELECT * FROM registration_reviews WHERE user_id=?'); $q->execute([$user]);
        $row=$q->fetch(PDO::FETCH_ASSOC); return $row ?: null;
    }

    public function review(int $actor,int $user,string $decision,string $reason): void
    {
        $reason=trim($reason);
        if(!in_array($decision,['approved','rejected'],true) || strlen($reason)<10 || strlen($reason)>500) { throw new InvalidArgumentException('A verification or rejection note of 10–500 bytes is required.'); }
        if($actor===$user || $this->pdo->inTransaction()) { throw new LogicException('Invalid registration review context.'); }
        $this->pdo->beginTransaction();
        try {
            $this->admin($actor);
            $q=$this->pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE'); $q->execute([$user]); $account=$q->fetch(PDO::FETCH_ASSOC);
            $q=$this->pdo->prepare('SELECT r.*,i.id_number,i.email,i.department_id FROM registration_reviews r JOIN registration_invitations i ON i.id=r.invitation_id WHERE r.user_id=? FOR UPDATE'); $q->execute([$user]); $review=$q->fetch(PDO::FETCH_ASSOC);
            if(!$account || !$review) { throw new InvalidArgumentException('Protected registration not found.'); }
            if($decision==='approved' && $review['initial_approved_at']===null && ($account['id_number']!==$review['id_number'] || strtolower($account['email'])!==$review['email'] || (int)$account['department_id']!==(int)$review['department_id'])) {
                throw new InvalidArgumentException('Account identity differs from the verified invitation. Resolve this with the account approver.');
            }
            $q=$this->pdo->prepare('UPDATE registration_reviews SET decision=?,reason=?,reviewed_by=?,reviewed_at=UNIX_TIMESTAMP() WHERE user_id=?'); $q->execute([$decision,$reason,$actor,$user]);
            if($decision==='approved') { $q=$this->pdo->prepare('UPDATE registration_reviews SET initial_approved_at=COALESCE(initial_approved_at,UNIX_TIMESTAMP()) WHERE user_id=?'); $q->execute([$user]); }
            $q=$this->pdo->prepare("UPDATE users SET status=?, session_version=session_version+1 WHERE id=?"); $q->execute([$decision==='approved'?'active':'inactive',$user]);
            $this->audit('registration_reviewed',$actor,$user,['decision'=>$decision,'reason'=>$reason,'invitation_id'=>(int)$review['invitation_id']]);
            $this->pdo->commit();
        } catch(Throwable $e) { if($this->pdo->inTransaction()) { $this->pdo->rollBack(); } throw $e; }
    }

    public function prune(): void
    {
        $this->pdo->exec('DELETE FROM registration_attempts WHERE attempted_at<=UNIX_TIMESTAMP()-3600 LIMIT 1000');
        $this->pdo->exec('DELETE FROM registration_limit_locks WHERE touched_at<UNIX_TIMESTAMP()-7200 LIMIT 1000');
    }
}
