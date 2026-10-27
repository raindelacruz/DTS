<?php

return [
    'untrusted client cannot forge forwarded source address' => function ($root) {
        require_once $root . '/app/lib/ClientRequestContext.php';
        $context = ClientRequestContext::resolve([
            'REMOTE_ADDR' => '203.0.113.7', 'HTTP_X_FORWARDED_FOR' => '198.51.100.10',
        ], ['192.0.2.1']);
        test_assert_same('203.0.113.7', $context['ip']);
        test_assert_same('untrusted_peer', $context['forwarded_status']);
    },
    'trusted chain stops at rightmost untrusted hop' => function ($root) {
        require_once $root . '/app/lib/ClientRequestContext.php';
        $context = ClientRequestContext::resolve([
            'REMOTE_ADDR' => '192.0.2.1',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.99, 203.0.113.7, 192.0.2.2',
        ], ['192.0.2.1', '192.0.2.2']);
        test_assert_same('203.0.113.7', $context['ip'], 'An injected leftmost IP must not be trusted.');
    },
    'malformed and oversized proxy chains fall back to peer' => function ($root) {
        require_once $root . '/app/lib/ClientRequestContext.php';
        foreach (['203.0.113.7, invalid', '203.0.113.7,', str_repeat('203.0.113.7,', 200)] as $header) {
            $context = ClientRequestContext::resolve([
                'REMOTE_ADDR' => '192.0.2.1', 'HTTP_X_FORWARDED_FOR' => $header,
            ], ['192.0.2.1']);
            test_assert_same('192.0.2.1', $context['ip']);
            test_assert_same('invalid', $context['forwarded_status']);
        }
    },
    'proxy allowlist normalizes equivalent IPv6 addresses' => function ($root) {
        require_once $root . '/app/lib/ClientRequestContext.php';
        $context = ClientRequestContext::resolve([
            'REMOTE_ADDR' => '2001:db8:0:0:0:0:0:1', 'HTTP_X_FORWARDED_FOR' => '2001:db8::7',
        ], ['2001:db8::1']);
        test_assert_same('2001:db8::7', $context['ip']);
    },
];
