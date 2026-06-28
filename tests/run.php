<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "The test runner must be executed from the command line.\n");
    exit(1);
}

$root = dirname(__DIR__);
$testFiles = glob(__DIR__ . '/*Test.php') ?: [];
$integrationFiles = getenv('RUN_DB_TESTS') === '1'
    ? (glob(__DIR__ . '/integration/*Test.php') ?: [])
    : [];

$files = array_merge($testFiles, $integrationFiles);
sort($files, SORT_STRING);

$passed = 0;
$failed = 0;
$skipped = 0;

function test_assert($condition, $message = 'Assertion failed')
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function test_assert_same($expected, $actual, $message = '')
{
    if ($expected !== $actual) {
        throw new RuntimeException($message ?: 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function test_skip($reason)
{
    throw new TestSkippedException($reason);
}

class TestSkippedException extends RuntimeException {}

foreach ($files as $file) {
    $tests = require $file;
    if (!is_array($tests)) {
        throw new RuntimeException($file . ' must return an array of named test callables.');
    }

    foreach ($tests as $name => $test) {
        if (!is_callable($test)) {
            throw new RuntimeException($file . ' test ' . $name . ' is not callable.');
        }

        try {
            $test($root);
            $passed++;
            echo "[PASS] {$name}\n";
        } catch (TestSkippedException $e) {
            $skipped++;
            echo "[SKIP] {$name}: {$e->getMessage()}\n";
        } catch (Throwable $e) {
            $failed++;
            echo "[FAIL] {$name}: {$e->getMessage()}\n";
        }
    }
}

echo "\n{$passed} passed, {$failed} failed, {$skipped} skipped.\n";
exit($failed > 0 ? 1 : 0);
