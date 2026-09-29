<?php
/**
 * SCO-2. MaxDiff score is (best − worst) / times shown.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\services\MaxDiffDesigner;

$failures = [];
$scores = (new MaxDiffDesigner())->scores(['A', 'B', 'C'], [
    ['best' => 'A', 'worst' => 'B', 'items' => ['A', 'B', 'C']],
    ['best' => 'A', 'worst' => 'B', 'items' => ['A', 'B', 'C']],
]);

if (($scores['A']['shown'] ?? null) !== 2 || ($scores['B']['shown'] ?? null) !== 2 || ($scores['C']['shown'] ?? null) !== 2) {
    $failures[] = 'shown ' . json_encode($scores);
}
if (($scores['A']['score'] ?? null) !== 1.0) {
    $failures[] = 'A score ' . json_encode($scores['A'] ?? null);
}
if (($scores['B']['score'] ?? null) !== -1.0) {
    $failures[] = 'B score ' . json_encode($scores['B'] ?? null);
}
if (($scores['C']['score'] ?? null) !== 0.0) {
    $failures[] = 'C score ' . json_encode($scores['C'] ?? null);
}

$fallback = (new MaxDiffDesigner())->scores(['A', 'B'], [
    ['best' => 'A', 'worst' => 'B'],
    ['best' => 'A', 'worst' => 'B'],
]);
if (($fallback['A']['shown'] ?? 0) !== 2 || ($fallback['A']['score'] ?? null) !== 1.0) {
    $failures[] = 'fallback ' . json_encode($fallback);
}

$half = (new MaxDiffDesigner())->scores(['A'], [
    ['best' => 'A', 'items' => ['A', 'B']],
    ['items' => ['A', 'B']],
]);
if (($half['A']['shown'] ?? 0) !== 2 || ($half['A']['score'] ?? null) !== 0.5) {
    $failures[] = 'half ' . json_encode($half);
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
