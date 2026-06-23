<?php

return [
    'action slip sequence reservation uses row locking' => function ($root) {
        $source = file_get_contents($root . '/app/models/DepartmentActionSlip.php');
        test_assert(stripos($source, 'FOR UPDATE') !== false, 'Action slip sequence reservation must use SELECT ... FOR UPDATE.');
        test_assert(stripos($source, 'beginTransaction') !== false, 'Action slip creation must reserve numbers inside a transaction.');
        test_assert(stripos($source, 'UNIQUE KEY unique_das_sequence') !== false, 'Sequence table must have a department/year/month uniqueness guard.');
    },

    'document qr token generation handles duplicate-token races' => function ($root) {
        $source = file_get_contents($root . '/app/models/Document.php');
        test_assert(stripos($source, '1062') !== false, 'QR token generation should catch duplicate key races.');
        test_assert(stripos($source, 'uq_documents_qr_token') !== false, 'QR token column must have a unique index.');
    },
];
