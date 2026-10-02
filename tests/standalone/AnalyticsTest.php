<?php
/** SCO-8, SCO-20, SCO-22: percentages that add up, numeric statistics, option parsing. */
require __DIR__ . '/bootstrap.php';
foreach (['ChoiceOptions', 'RoundService', 'DashboardService'] as $class) {
    require_once dirname(__DIR__, 2) . '/services/' . $class . '.php';
}

use humhub\modules\thiscoveryForms\services\ChoiceOptions;
use humhub\modules\thiscoveryForms\services\DashboardService;
use humhub\modules\thiscoveryForms\services\RoundService;

$failures = [];

$pct = RoundService::percentages(['a' => 1, 'b' => 1, 'c' => 1], 3);
standalone_assert(array_sum($pct) === 100, 'thirds add to ' . array_sum($pct), $failures);
$pct = RoundService::percentages(['a' => 2, 'b' => 2, 'c' => 2, 'd' => 1], 7);
standalone_assert(array_sum($pct) === 100, 'sevenths add to ' . array_sum($pct), $failures);

$stats = DashboardService::numericStats([1.0, 2.0, 3.0, 4.0, 100.0]);
standalone_assert($stats['median'] == 3.0 && $stats['p25'] == 2.0 && $stats['p75'] == 4.0 && $stats['min'] == 1.0 && $stats['max'] == 100.0, 'numeric stats ' . json_encode($stats), $failures);

standalone_assert(ChoiceOptions::parseLine('1|Yes') === ['code' => '1', 'label' => 'Yes'], 'compact code|label', $failures);
standalone_assert(ChoiceOptions::parseLine('1 | Yes') === ['code' => '1', 'label' => 'Yes'], 'spaced code | label', $failures);
standalone_assert(ChoiceOptions::parseLine('Yes') === ['code' => 'Yes', 'label' => 'Yes'], 'plain option', $failures);
$items = ChoiceOptions::itemsFromDecoded([['code' => '1', 'label' => 'A'], ['code' => '1', 'label' => 'B']]);
standalone_assert(array_column($items, 'code') === ['1', '1-2'], 'duplicate codes merged: ' . json_encode($items), $failures);

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
exit(0);
