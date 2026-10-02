<?php
/**
 * Pure-PHP tests. No HumHub, no database. Exits non-zero on failure.
 */
$failed = 0;
$files = glob(__DIR__ . '/*Test.php') ?: [];
sort($files);
foreach ($files as $file) {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file), $code);
    echo basename($file) . ' ' . ($code === 0 ? 'PASS' : 'FAIL') . "\n";
    $failed += $code === 0 ? 0 : 1;
}
echo ($failed === 0 ? 'OK' : 'FAILED ' . $failed) . ' / ' . count($files) . "\n";
exit($failed === 0 ? 0 : 1);
