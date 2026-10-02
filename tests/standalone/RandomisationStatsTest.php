<?php
/**
 * V3-5: the shuffle reaches every order, places items uniformly, and orders for two
 * scopes of the same response are independent. Block randomisation is exactly balanced.
 */
require __DIR__ . '/bootstrap.php';

use humhub\modules\thiscoveryForms\services\RandomisationEngine;

$failures = [];
$engine = new RandomisationEngine();
$seeds = 60000;

// Every order reachable for n = 2..5, and each order close to 1/n!.
foreach ([2, 3, 4, 5] as $n) {
    $items = range(1, $n);
    $counts = [];
    for ($s = 0; $s < $seeds; $s++) {
        $key = implode(',', $engine->shuffle($items, $engine->seedInt(bin2hex(pack('N', $s)), 'options:q1')));
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }
    $factorial = array_product($items);
    standalone_assert(count($counts) === $factorial, "n=$n reaches " . count($counts) . " of $factorial orders", $failures);
    // Chi-square against uniform; critical value at p=0.001 for df = n!-1 (df<=119 -> 170 is generous).
    $expected = $seeds / $factorial;
    $chi = 0.0;
    foreach ($counts as $c) {
        $chi += ($c - $expected) ** 2 / $expected;
    }
    $critical = [2 => 10.8, 3 => 20.5, 4 => 49.7, 5 => 170.0][$n];
    standalone_assert($chi < $critical, sprintf('n=%d orders not uniform (chi2=%.1f)', $n, $chi), $failures);
}

// Position uniformity for n = 8: item 1 lands in each position about 1/8 of the time.
$positions = array_fill(0, 8, 0);
for ($s = 0; $s < $seeds; $s++) {
    $order = $engine->shuffle(range(1, 8), $engine->seedInt(bin2hex(pack('N', $s)), 'options:q8'));
    $positions[array_search(1, $order, true)]++;
}
foreach ($positions as $p => $c) {
    $share = $c / $seeds;
    standalone_assert(abs($share - 0.125) < 0.01, sprintf('n=8 position %d share %.3f', $p, $share), $failures);
}

// Independence across scopes: two 2-option questions agree about half the time,
// two 4-option questions agree about 1/24 of the time.
$same2 = 0;
$same4 = 0;
for ($s = 0; $s < $seeds; $s++) {
    $hex = bin2hex(pack('N', $s));
    $same2 += $engine->shuffle(['a', 'b'], $engine->seedInt($hex, 'options:q1')) === $engine->shuffle(['a', 'b'], $engine->seedInt($hex, 'options:q2')) ? 1 : 0;
    $same4 += $engine->shuffle([1, 2, 3, 4], $engine->seedInt($hex, 'options:q3')) === $engine->shuffle([1, 2, 3, 4], $engine->seedInt($hex, 'options:q4')) ? 1 : 0;
}
standalone_assert(abs($same2 / $seeds - 0.5) < 0.01, sprintf('2-option scopes agree %.3f (want 0.5)', $same2 / $seeds), $failures);
standalone_assert(abs($same4 / $seeds - 1 / 24) < 0.005, sprintf('4-option scopes agree %.4f (want 0.0417)', $same4 / $seeds), $failures);

// Block randomisation: every block is exactly balanced, and arm A takes the first slot half the time.
$arms = [['code' => 'A', 'weight' => 1], ['code' => 'B', 'weight' => 1]];
$firstA = 0;
for ($s = 0; $s < $seeds; $s++) {
    $block = $engine->block($arms, 4, $engine->seedInt(bin2hex(pack('N', $s)), 'block'));
    $tally = array_count_values($block);
    standalone_assert(($tally['A'] ?? 0) === 2 && ($tally['B'] ?? 0) === 2, 'block of 4 not balanced', $failures);
    $firstA += $block[0] === 'A' ? 1 : 0;
    if (count($failures) > 20) {
        break;
    }
}
standalone_assert(abs($firstA / $seeds - 0.5) < 0.01, sprintf('arm A first-slot share %.3f (want 0.5)', $firstA / $seeds), $failures);

// Same seed, same order (reproducible on resume).
standalone_assert($engine->shuffle(range(1, 6), 12345) === $engine->shuffle(range(1, 6), 12345), 'shuffle not reproducible', $failures);

if ($failures) {
    fwrite(STDERR, implode("\n", array_unique($failures)) . "\n");
    exit(1);
}
exit(0);
