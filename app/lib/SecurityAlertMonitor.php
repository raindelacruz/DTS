<?php

final class SecurityAlertMonitor
{
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo=$pdo; }

    public function snapshot(array $modes): array
    {
        if($this->pdo->inTransaction()) { throw new LogicException('Alert reporting owns its transaction.'); }
        $this->pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        try {
            $now=(int)$this->pdo->query('SELECT UNIX_TIMESTAMP()')->fetchColumn();
            $tables=$this->pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
            $alerts=[];
            $add=static function($rule,$severity,$subject,$count,$metadata=[]) use(&$alerts) {
                $alerts[]=['rule'=>$rule,'severity'=>$severity,'subject'=>$subject,'count'=>(int)$count,'metadata'=>$metadata];
            };
            $expected=['observe'=>['security_request_counters'], 'authentication'=>['authentication_guard'],
                'reset'=>['password_reset_limits','password_reset_admissions','password_reset_outbox'],
                'registration'=>['registration_invitations','registration_limit_locks','registration_attempts','registration_reviews','registration_notice_state']];
            $readiness=[];
            foreach($expected as$name=>$required) {
                $mode=$modes[$name]??'off'; $missing=array_values(array_diff($required,$tables));
                $readiness[$name]=['mode'=>$mode,'tables_ready'=>!$missing,'missing_tables'=>$missing];
                if($mode!=='off' && $missing) { $add('protection_schema_unavailable','critical',$name,1,['missing_tables'=>$missing]); }
            }
            if(!in_array('security_audit_log',$tables,true)) { $add('audit_unavailable','critical','global',1); }
            else {
                $rules=[
                    ['repeated_mfa_failures','warning','target_user_id',[ 'mfa_failed'],5],
                    ['repeated_admin_verification_failures','warning','actor_user_id',['admin_reauthentication_failed'],5],
                    ['repeated_reset_requests','warning','target_user_id',['password_reset_requested'],3],
                    ['registration_burst','warning',null,['account_registered'],10],
                    ['privileged_account_changes','warning','actor_user_id',['user_role_changed','user_department_changed','user_status_changed','administrator_password_reset','mfa_reset_by_administrator','registration_reviewed'],1],
                    ['emergency_administrator_access','critical','actor_user_id',['admin_emergency_access'],1],
                    ['reset_protection_unavailable','critical',null,['password_reset_guard_unavailable'],1],
                ];
                foreach($rules as[$rule,$severity,$column,$events,$threshold]) {
                    $subject=$column ? "COALESCE(CAST($column AS CHAR),'unknown')" : "'global'";
                    $placeholders=implode(',',array_fill(0,count($events),'?'));
                    $q=$this->pdo->prepare("SELECT $subject subject,COUNT(*) event_count FROM security_audit_log WHERE created_at>FROM_UNIXTIME(?) AND event_type IN ($placeholders) GROUP BY subject HAVING COUNT(*)>=? LIMIT 1000");
                    $q->execute(array_merge([$now-900],$events,[$threshold]));
                    foreach($q->fetchAll(PDO::FETCH_ASSOC) as$row) { $add($rule,$severity,(string)$row['subject'],$row['event_count']); }
                }
                $q=$this->pdo->prepare("SELECT COUNT(*) FROM security_audit_log WHERE created_at>FROM_UNIXTIME(?) AND event_type='password_reset_guard_processed' AND CASE WHEN JSON_VALID(metadata) THEN JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.outcome')) ELSE '' END='limited'");
                $q->execute([$now-900]); $count=(int)$q->fetchColumn();
                if($count>=10) { $add('reset_request_limits','warning','global',$count); }
            }
            $queue=null;
            if(in_array('password_reset_outbox',$tables,true)) {
                $q=$this->pdo->query("SELECT status,COUNT(*) job_count,SUM(expires_at<=UNIX_TIMESTAMP()) expired FROM password_reset_outbox GROUP BY status");
                $queue=$q->fetchAll(PDO::FETCH_ASSOC);
                $q=$this->pdo->prepare("SELECT COUNT(*) FROM password_reset_outbox WHERE status='queued' AND expires_at<=?"); $q->execute([$now]); $expired=(int)$q->fetchColumn();
                if($expired) { $add('reset_mail_expired_queue','critical','global',$expired); }
                $q=$this->pdo->prepare("SELECT COUNT(*) FROM password_reset_outbox WHERE status='sending' AND claimed_at<=?"); $q->execute([$now-300]); $stalled=(int)$q->fetchColumn();
                if($stalled) { $add('reset_mail_stalled_sender','warning','global',$stalled); }
                $q=$this->pdo->prepare('SELECT user_id,COUNT(*) event_count FROM password_reset_outbox WHERE expires_at>? GROUP BY user_id HAVING COUNT(*)>=3 LIMIT 1000'); $q->execute([$now+900]);
                foreach($q->fetchAll(PDO::FETCH_ASSOC) as$row) { $add('repeated_queued_resets','warning',(string)$row['user_id'],$row['event_count']); }
            }
            $admins=[];
            if(in_array('users',$tables,true)) {
                $admins=$this->pdo->query("SELECT id,mfa_enabled FROM users WHERE role='admin' AND status='active' ORDER BY id LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC);
                foreach($admins as$admin) { if(empty($admin['mfa_enabled'])) { $add('administrator_mfa_missing','critical',(string)$admin['id'],1); } }
                $shared=$this->pdo->query("SELECT id FROM users WHERE role='admin' AND status='active' AND LOWER(id_number)='sysadmin' LIMIT 1000")->fetchAll(PDO::FETCH_COLUMN);
                foreach($shared as$id) { $add('administrator_ownership_review','notice',(string)$id,1,['interpretation'=>'Verify individual ownership; this is not evidence of compromise.']); }
            }
            $this->pdo->rollBack();
            return ['database_clock'=>$now,'evaluation_window_seconds'=>900,'modes'=>$modes,'readiness'=>$readiness,
                'alert_table_ready'=>in_array('security_operational_alerts',$tables,true),'active_administrators'=>$admins,
                'reset_mail_queue'=>$queue,'alerts'=>$alerts,'external_delivery'=>'not_configured'];
        } catch(Throwable $e) { if($this->pdo->inTransaction()) { $this->pdo->rollBack(); } throw $e; }
    }

    public function collect(array $snapshot): int
    {
        if($this->pdo->inTransaction()) { throw new LogicException('Alert collection owns its transaction.'); }
        $window=intdiv((int)$snapshot['database_clock'],900)*900;
        $new=0; $this->pdo->beginTransaction();
        try {
            foreach($snapshot['alerts'] as$alert) {
                $hash=hash('sha256',$alert['rule'].'|'.$alert['subject'].'|'.$window);
                $q=$this->pdo->prepare('INSERT INTO security_operational_alerts (alert_key,rule_name,severity,subject,event_count,window_start,metadata) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE event_count=GREATEST(event_count,VALUES(event_count)),last_seen=CURRENT_TIMESTAMP,metadata=VALUES(metadata)');
                $q->execute([$hash,$alert['rule'],$alert['severity'],$alert['subject'],$alert['count'],$window,json_encode($alert['metadata'],JSON_THROW_ON_ERROR)]);
                if($q->rowCount()===1) { $new++; }
            }
            $this->pdo->commit(); return $new;
        } catch(Throwable $e) { if($this->pdo->inTransaction()) { $this->pdo->rollBack(); } throw $e; }
    }
}
