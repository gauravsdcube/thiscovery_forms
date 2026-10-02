<?php
/** V3-3: posted calculated values are discarded; calculated fields run in dependency order; cycles stay empty. */
require __DIR__ . '/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\formula\FormulaRuntime;

$failures = [];

// a reads b, and b is defined after a. The browser posts a forged b.
$fields = [new FormField(1, 'x', 'number'), new FormField(2, 'a', 'calculated', '[b] * 2'), new FormField(3, 'b', 'calculated', '[x] + 1')];
$values = [1 => '5', 2 => '', 3 => '999999', 'b' => '999999'];
FormulaRuntime::fill($values, $fields, '2026-09-30');
standalone_assert(($values[3] ?? null) === '6', 'b recomputed from x, got ' . var_export($values[3] ?? null, true), $failures);
standalone_assert(($values[2] ?? null) === '12', 'a uses server b, got ' . var_export($values[2] ?? null, true), $failures);

// A cycle leaves both fields empty and is reported.
$fields = [new FormField(1, 'p', 'calculated', '[q] + 1'), new FormField(2, 'q', 'calculated', '[p] + 1')];
$values = [1 => '7', 2 => '8'];
Yii::$warnings = [];
FormulaRuntime::fill($values, $fields, '2026-09-30');
standalone_assert(array_key_exists(1, $values) && $values[1] === null && $values[2] === null, 'cycle fields empty', $failures);
standalone_assert((bool)array_filter(Yii::$warnings, fn($w) => str_contains($w, 'cycle')), 'cycle warning logged', $failures);

// A posted value for a calculated field with no formula is still discarded.
$fields = [new FormField(1, 'c', 'calculated', '')];
$values = [1 => '42', 'c' => '42'];
FormulaRuntime::fill($values, $fields, '2026-09-30');
standalone_assert(array_key_exists(1, $values) && $values[1] === null && $values['c'] === null, 'empty formula discards posted value', $failures);

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
exit(0);
