<?php
/**
 * SCO-1. Twelve items in sets of four across twelve sets stay distinct.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\services\MaxDiffDesigner;

$failures = [];
$items = [];
for ($i = 1; $i <= 12; $i++) {
    $items[] = 'item' . $i;
}
$sets = (new MaxDiffDesigner())->generateSets($items, 4, 12);
$unique = [];
$pairs = [];
foreach ($sets as $set) {
    if (count($set) !== 4) {
        $failures[] = 'a set does not have 4 items ' . json_encode($set);
    }
    $copy = $set;
    sort($copy);
    $unique[implode(',', $copy)] = true;
    for ($i = 0; $i < count($set); $i++) {
        for ($j = $i + 1; $j < count($set); $j++) {
            $pair = [$set[$i], $set[$j]];
            sort($pair);
            $pairs[implode('|', $pair)] = true;
        }
    }
}
if (count($sets) !== 12) {
    $failures[] = 'set count ' . count($sets);
}
if (count($unique) < 8) {
    $failures[] = 'unordered sets ' . count($unique);
}
if (count($pairs) < 30) {
    $failures[] = 'pairs ' . count($pairs);
}
if ((new MaxDiffDesigner())->generateSets(['only'], 4, 3) !== []) {
    $failures[] = 'one item still produced a set';
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
