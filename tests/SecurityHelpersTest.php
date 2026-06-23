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

    'sensitive value encryption round trips' => function ($root) {
        require_once $root . '/app/init.php';

        $ciphertext = encryptSensitiveValue('secret-value');
        test_assert($ciphertext !== 'secret-value', 'Ciphertext must not equal plaintext.');
        test_assert_same('secret-value', decryptSensitiveValue($ciphertext));
    },
];
