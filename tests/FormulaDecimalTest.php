<?php

/**
 * Scale-12 decimals. No database.
 */

use humhub\modules\thiscoveryForms\services\formula\Decimal;

require dirname(__DIR__) . '/services/formula/Decimal.php';

$cases = json_decode((string)file_get_contents(__DIR__ . '/test-vectors/formula/decimal.json'), true);
$failed = 0;
foreach ($cases as $case) {
    $op = $case['op'];
    $args = $case['args'];
    if ($op === 'round') {
        $got = Decimal::round((string)$args[0], (int)$args[1]);
    } elseif ($op === 'cmp') {
        $got = Decimal::cmp((string)$args[0], (string)$args[1]);
    } elseif ($op === 'canonical') {
        $got = Decimal::canonical((string)$args[0]);
    } else {
        $got = Decimal::$op((string)$args[0], (string)$args[1]);
    }
    if ($got !== $case['expect']) {
        fwrite(STDERR, $op . ' ' . json_encode($args) . ' got ' . json_encode($got) . ' expect ' . json_encode($case['expect']) . "\n");
        $failed++;
    }
}
if ($failed) {
    fwrite(STDERR, "FAIL {$failed}\n");
    exit(1);
}
echo "PASS\n";
