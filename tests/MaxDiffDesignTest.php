<?php
/**
 * SCO-1. Twelve items in sets of four across twelve sets stay distinct; several design
 * versions pair items differently and a respondent keeps theirs.
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

// V3-42: 10 items, sets of 4, 5 sets shows every item (items 9 and 10 were never shown).
$ten = array_map(static fn($i) => 'item' . $i, range(1, 10));
$shown = array_count_values(array_merge(...(new MaxDiffDesigner())->generateSets($ten, 4, 5)));
if (count($shown) !== 10 || min($shown) !== max($shown)) {
    $failures[] = '10 items in 5 sets of 4 were not all shown equally: ' . json_encode($shown);
}

// V3-42: a save with the same design keeps the stored sets.
$md = new \humhub\modules\thiscoveryForms\models\FormField();
$md->type = \humhub\modules\thiscoveryForms\models\FormField::TYPE_MAXDIFF;
$md->setItemsConfig(['items' => implode("\n", $ten), 'setSize' => 4, 'setCount' => 5]);
$stored = json_decode((string)$md->options_json, true);
$stored['designs'][0][0] = array_reverse($stored['designs'][0][0]);
$md->options_json = json_encode($stored);
$md->setItemsConfig(['items' => implode("\n", $ten), 'setSize' => 4, 'setCount' => 5]);
if ((json_decode((string)$md->options_json, true)['designs'] ?? null) !== $stored['designs']) {
    $failures[] = 'a save with an unchanged design rebuilt the MaxDiff sets';
}

// SCO-1: several versions, each showing every item, pairing items differently; a
// respondent's version is stable and the stored one wins.
$cfg = $md->getItemsConfig();
if ($cfg['versions'] < 2) {
    $failures[] = 'only one MaxDiff version was built';
}
$pairSets = [];
foreach ($cfg['designs'] as $v => $design) {
    $counts = array_count_values(array_merge(...$design));
    if (count($counts) !== 10) {
        $failures[] = "version {$v} does not show every item";
    }
    $pairs = [];
    foreach ($design as $set) {
        sort($set);
        for ($i = 0; $i < count($set); $i++) {
            for ($j = $i + 1; $j < count($set); $j++) {
                $pairs[$set[$i] . '|' . $set[$j]] = true;
            }
        }
    }
    ksort($pairs);
    $pairSets[implode(',', array_keys($pairs))] = true;
}
if (count($pairSets) < 2) {
    $failures[] = 'the versions all pair the same items';
}
$md->id = 99;
if ($md->maxDiffVersionFor(null) !== $md->maxDiffVersionFor(null)) {
    $failures[] = 'a respondent\'s version changed between pages';
}
if ($md->maxDiffVersionFor(['version' => 1, 'sets' => []]) !== 1) {
    $failures[] = 'the stored version was not kept';
}
if ($md->maxDiffVersionFor(['version' => 999]) >= $cfg['versions']) {
    $failures[] = 'an out-of-range version was accepted';
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
