<?php

class SecurityService
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function isLoginBlocked($identifier, $ipAddress)
    {
        $identifierHash = hash('sha256', strtolower(trim($identifier)));
        $ipBucketHash = hash('sha256', 'ip-bucket');
        $this->db->query("SELECT MAX(locked_until) AS locked_until FROM login_attempts WHERE (identifier_hash = :identifier_hash AND ip_address IN (:ip_address, '*')) OR (identifier_hash = :ip_bucket_hash AND ip_address = :ip_address2)");
        $this->db->bind(':identifier_hash', $identifierHash);
        $this->db->bind(':ip_address', $ipAddress);
        $this->db->bind(':ip_bucket_hash', $ipBucketHash);
        $this->db->bind(':ip_address2', $ipAddress);
        $row = $this->db->single();
        return $row && !empty($row->locked_until) && strtotime($row->locked_until) > time();
    }

    public function recordLoginFailure($identifier, $ipAddress)
    {
        $hash = hash('sha256', strtolower(trim($identifier)));
        foreach ([[$hash, $ipAddress], [$hash, '*'], [hash('sha256', 'ip-bucket'), $ipAddress]] as [$bucketHash, $bucketIp]) {
            $this->recordFailureBucket($bucketHash, $bucketIp);
        }
    }

    private function recordFailureBucket($hash, $ipAddress)
    {
        $this->db->query("
            INSERT INTO login_attempts (identifier_hash, ip_address, failed_attempts, first_failed_at, last_failed_at, locked_until)
            VALUES (:identifier_hash, :ip_address, 1, NOW(), NOW(), NULL)
            ON DUPLICATE KEY UPDATE
                failed_attempts = IF(first_failed_at < DATE_SUB(NOW(), INTERVAL :window SECOND), 1, failed_attempts + 1),
                first_failed_at = IF(first_failed_at < DATE_SUB(NOW(), INTERVAL :window2 SECOND), NOW(), first_failed_at),
                last_failed_at = NOW(),
                locked_until = IF(failed_attempts + 1 >= :max_attempts, DATE_ADD(NOW(), INTERVAL :lockout SECOND), locked_until)
        ");
        $this->db->bind(':identifier_hash', $hash);
        $this->db->bind(':ip_address', $ipAddress);
        $this->db->bind(':window', LOGIN_LOCKOUT_SECONDS);
        $this->db->bind(':window2', LOGIN_LOCKOUT_SECONDS);
        $this->db->bind(':max_attempts', LOGIN_MAX_ATTEMPTS);
        $this->db->bind(':lockout', LOGIN_LOCKOUT_SECONDS);
        $this->db->execute();
    }

    public function clearLoginFailures($identifier, $ipAddress)
    {
        $this->db->query("DELETE FROM login_attempts WHERE identifier_hash = :identifier_hash AND ip_address IN (:ip_address, '*')");
        $this->db->bind(':identifier_hash', hash('sha256', strtolower(trim($identifier))));
        $this->db->bind(':ip_address', $ipAddress);
        $this->db->execute();
    }

    public function createTrustedMfaDevice($userId, $sessionVersion)
    {
        $token = bin2hex(random_bytes(32));
        $this->db->query("
            INSERT INTO mfa_trusted_devices
                (user_id, token_hash, user_agent_hash, session_version, expires_at, last_used_at)
            VALUES
                (:user_id, :token_hash, :user_agent_hash, :session_version, DATE_ADD(NOW(), INTERVAL :ttl SECOND), NOW())
        ");
        $this->db->bind(':user_id', (int) $userId);
        $this->db->bind(':token_hash', hash('sha256', $token));
        $this->db->bind(':user_agent_hash', $this->userAgentHash());
        $this->db->bind(':session_version', (int) $sessionVersion);
        $this->db->bind(':ttl', MFA_REMEMBER_DEVICE_SECONDS);
        $this->db->execute();

        return (int) $userId . ':' . $token;
    }

    public function isTrustedMfaDevice($cookieValue, $userId, $sessionVersion)
    {
        $parts = explode(':', (string) $cookieValue, 2);
        if (count($parts) !== 2 || (int) $parts[0] !== (int) $userId || !preg_match('/^[a-f0-9]{64}$/', $parts[1])) {
            return false;
        }

        $this->db->query("
            SELECT id
            FROM mfa_trusted_devices
            WHERE user_id = :user_id
                AND token_hash = :token_hash
                AND user_agent_hash = :user_agent_hash
                AND session_version = :session_version
                AND expires_at > NOW()
                AND revoked_at IS NULL
            LIMIT 1
        ");
        $this->db->bind(':user_id', (int) $userId);
        $this->db->bind(':token_hash', hash('sha256', $parts[1]));
        $this->db->bind(':user_agent_hash', $this->userAgentHash());
        $this->db->bind(':session_version', (int) $sessionVersion);
        $device = $this->db->single();
        if (!$device) {
            return false;
        }

        $this->db->query('UPDATE mfa_trusted_devices SET last_used_at = NOW() WHERE id = :id');
        $this->db->bind(':id', (int) $device->id);
        $this->db->execute();
        return true;
    }

    public function pruneExpiredTrustedMfaDevices()
    {
        $this->db->query('DELETE FROM mfa_trusted_devices WHERE expires_at <= NOW() OR revoked_at IS NOT NULL');
        $this->db->execute();
    }

    private function userAgentHash()
    {
        return hash('sha256', substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500));
    }

    public function audit($eventType, $actorUserId = null, $targetUserId = null, $metadata = [])
    {
        $this->db->query("
            INSERT INTO security_audit_log
                (event_type, actor_user_id, target_user_id, ip_address, user_agent, metadata)
            VALUES
                (:event_type, :actor_user_id, :target_user_id, :ip_address, :user_agent, :metadata)
        ");
        $this->db->bind(':event_type', $eventType);
        $this->db->bind(':actor_user_id', $actorUserId);
        $this->db->bind(':target_user_id', $targetUserId);
        $this->db->bind(':ip_address', clientIpAddress());
        $this->db->bind(':user_agent', substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500));
        $this->db->bind(':metadata', empty($metadata) ? null : json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->db->execute();
    }

    public function createPasswordReset($userId)
    {
        $token = bin2hex(random_bytes(32));
        $this->db->query('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL');
        $this->db->bind(':user_id', $userId);
        $this->db->execute();
        $this->db->query("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
        $this->db->bind(':user_id', $userId);
        $this->db->bind(':token_hash', hash('sha256', $token));
        $this->db->execute();
        return $token;
    }

    public function findValidPasswordReset($token)
    {
        $this->db->query("SELECT * FROM password_reset_tokens WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > NOW() LIMIT 1");
        $this->db->bind(':token_hash', hash('sha256', $token));
        return $this->db->single();
    }

    public function consumePasswordReset($id)
    {
        $this->db->query('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id AND used_at IS NULL');
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }
}
