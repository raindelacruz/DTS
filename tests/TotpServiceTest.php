<?php

return [
    'totp service generates and verifies current code' => function ($root) {
        require_once $root . '/app/init.php';

        $secret = TotpService::generateSecret();
        $method = new ReflectionMethod(TotpService::class, 'code');
        $method->setAccessible(true);
        $code = $method->invoke(null, $secret, (int) floor(time() / 30));

        test_assert(preg_match('/^\d{6}$/', $code) === 1, 'TOTP code should be six digits.');
        $invalidCode = $code === '000000' ? '111111' : '000000';
        test_assert(TotpService::verify($secret, $code), 'Generated TOTP code should verify.');
        test_assert(!TotpService::verify($secret, $invalidCode, 0), 'Invalid TOTP code should not verify without drift.');
    },
];
