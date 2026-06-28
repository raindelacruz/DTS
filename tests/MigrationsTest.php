<?php

return [
    'all migrations return callables' => function ($root) {
        $files = glob($root . '/database/migrations/*.php') ?: [];
        test_assert(!empty($files), 'At least one migration should exist.');

        foreach ($files as $file) {
            $migration = require $file;
            test_assert(is_callable($migration), basename($file) . ' must return a callable.');
        }
    },
];
