<?php

return [
    'password policy rejects weak passwords' => function ($root) {
        require_once $root . '/app/init.php';

        $errors = validatePasswordPolicy('short1!');
        test_assert(!empty($errors), 'Weak password should be rejected.');

        $errors = validatePasswordPolicy('Correct-Horse-42!');
        test_assert_same([], $errors, 'Strong password should satisfy the configured policy.');
    },

    'safe redirects allow local paths only' => function ($root) {
        require_once $root . '/app/init.php';

        test_assert(isSafeRedirectTarget('/documents/index'));
        test_assert(!isSafeRedirectTarget('//evil.example.test'));
        test_assert(!isSafeRedirectTarget('https://evil.example.test'));
    },

    'application cookies default to root path' => function ($root) {
        require_once $root . '/app/init.php';

        test_assert_same('/', APP_COOKIE_PATH, 'MFA trusted-device cookies must be sent on root-routed production URLs.');
    },

    'csrf tokens survive missing form-state session' => function ($root) {
        require_once $root . '/app/init.php';

        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $_SERVER['HTTP_USER_AGENT'] = 'DTS test browser';
        $_SESSION = [];

        $token = csrfToken('login');
        unset($_SESSION['csrf_token']);

        test_assert(validateCsrfToken($token, 'login'), 'Signed CSRF token should validate without the session-stored token.');
        test_assert(!validateCsrfToken($token . 'tampered', 'login'), 'Tampered CSRF token should fail.');
        test_assert(!validateCsrfToken($token, 'profile'), 'CSRF token should not validate for a different context.');
    },

    'sensitive value encryption round trips' => function ($root) {
        require_once $root . '/app/init.php';

        $ciphertext = encryptSensitiveValue('secret-value');
        test_assert($ciphertext !== 'secret-value', 'Ciphertext must not equal plaintext.');
        test_assert_same('secret-value', decryptSensitiveValue($ciphertext));
    },
];
