<?php
/**
 * Release hygiene (V3-9, V3-29): no test-only guards or development dates in production code.
 */
$root = dirname(__DIR__, 2);
$failures = [];
$dirs = ['services', 'models', 'controllers', 'views', 'helpers', 'commands', 'resources/js', 'widgets', 'notifications'];
$allowedDates = [
    'services/LlmClient.php' => true, // API version string, not a date used by logic
    'commands/ImportSparcs2Controller.php' => true, // SPARCS study start date for sample data
];
foreach ($dirs as $dir) {
    if (!is_dir("$root/$dir")) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir", FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $path = $file->getPathname();
        if (!preg_match('/\.(php|js)$/', $path) || str_ends_with($path, '.min.js')) {
            continue;
        }
        $rel = substr($path, strlen($root) + 1);
        $text = (string)file_get_contents($path);
        if (str_contains($text, 'example.test')) {
            $failures[] = "$rel mentions example.test (test-only address in production code)";
        }
        if (!isset($allowedDates[$rel]) && preg_match('/[\'"]20\d{2}-\d{2}-\d{2}[\'"]/', $text, $m)) {
            $failures[] = "$rel contains a hard-coded date {$m[0]}";
        }
    }
}
if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
exit(0);
