<?php

final class ClientRequestContext
{
    public static function resolve(array $server, array $trustedProxies): array
    {
        $remote = self::ip((string) ($server['REMOTE_ADDR'] ?? ''));
        $trusted = array_values(array_filter(array_map([self::class, 'ip'], $trustedProxies)));
        $isTrusted = $remote !== null && in_array($remote, $trusted, true);
        $result = [
            'ip' => $remote ?? '0.0.0.0',
            'peer_ip' => $remote ?? '0.0.0.0',
            'trusted_peer' => $isTrusted,
            'source' => 'direct_peer',
            'forwarded_status' => 'absent',
        ];
        if (!isset($server['HTTP_X_FORWARDED_FOR'])) {
            return $result;
        }
        if (!$isTrusted) {
            $result['forwarded_status'] = 'untrusted_peer';
            return $result;
        }
        $header = $server['HTTP_X_FORWARDED_FOR'];
        if (!is_string($header) || strlen($header) > 2048) {
            $result['forwarded_status'] = 'invalid';
            return $result;
        }
        $parts = explode(',', $header);
        if (count($parts) > 16) {
            $result['forwarded_status'] = 'invalid';
            return $result;
        }
        $chain = [];
        foreach ($parts as $part) {
            $ip = self::ip(trim($part));
            if ($ip === null) {
                $result['forwarded_status'] = 'invalid';
                return $result;
            }
            $chain[] = $ip;
        }
        // The rightmost untrusted hop is the client boundary. Ignore any
        // attacker-supplied values to its left, even when they look valid.
        $candidate = $remote;
        for ($i = count($chain) - 1; $i >= 0 && in_array($candidate, $trusted, true); $i--) {
            $candidate = $chain[$i];
        }
        $result['ip'] = $candidate;
        $result['source'] = 'trusted_proxy_chain';
        $result['forwarded_status'] = 'accepted';
        return $result;
    }

    private static function ip(string $value): ?string
    {
        if (!filter_var($value, FILTER_VALIDATE_IP)) {
            return null;
        }
        return inet_ntop(inet_pton($value));
    }
}
