<?php
/**
 * Run every module test. Exits non-zero if any test fails.
 * Requires THISCOVERY_FORMS_TEST_DB=1.
 */

if (getenv('THISCOVERY_FORMS_TEST_DB') !== '1') {
    fwrite(STDERR, "Refusing to run. Set THISCOVERY_FORMS_TEST_DB=1 to use the test database.\n");
    exit(2);
}

$files = glob(__DIR__ . '/*Test.php') ?: [];
sort($files);
if ($files === []) {
    fwrite(STDERR, "No tests found.\n");
    exit(1);
}

$failed = 0;
foreach ($files as $file) {
    $name = basename($file);
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file);
    passthru($cmd, $code);
    echo $name . ' ' . ($code === 0 ? 'PASS' : 'FAIL ' . $code) . "\n";
    if ($code !== 0) {
        $failed++;
    }
}

echo ($failed === 0 ? 'OK' : 'FAILED ' . $failed) . ' / ' . count($files) . "\n";
exit($failed === 0 ? 0 : 1);
