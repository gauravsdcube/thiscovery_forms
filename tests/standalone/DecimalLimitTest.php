<?php
/** V3-4: huge numbers are empty, so they cannot use up CPU. */
require __DIR__ . '/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\formula\Context;
use humhub\modules\thiscoveryForms\services\formula\Decimal;
use humhub\modules\thiscoveryForms\services\formula\Evaluator;
use humhub\modules\thiscoveryForms\services\formula\FormulaRuntime;
use humhub\modules\thiscoveryForms\services\formula\Parser;

$failures = [];

$started = microtime(true);
$fields = [new FormField(1, 'x', 'number'), new FormField(2, 'third', 'calculated', '[x] / 3'), new FormField(3, 'avg', 'calculated', 'mean([x], 1)')];
$values = [1 => str_repeat('9', 8000)];
FormulaRuntime::fill($values, $fields, '2026-09-30');
$elapsed = microtime(true) - $started;
standalone_assert($values[2] === null, 'an 8000-digit input divides to empty', $failures);
standalone_assert($elapsed < 0.5, sprintf('8000-digit input took %.2fs', $elapsed), $failures);

$started = microtime(true);
$tree = (new Parser())->parse('((99999999999^20)^20)^20');
$result = (new Evaluator(new Context()))->evaluate($tree);
$elapsed = microtime(true) - $started;
standalone_assert($result->isEmpty(), 'nested powers overflow to empty', $failures);
standalone_assert($elapsed < 0.5, sprintf('nested powers took %.2fs', $elapsed), $failures);

standalone_assert(Decimal::canonical(str_repeat('1', 30)) === str_repeat('1', 30), '30 whole digits are allowed', $failures);
standalone_assert(Decimal::canonical(str_repeat('1', 31)) === null, '31 whole digits are empty', $failures);
standalone_assert(Decimal::mul(str_repeat('9', 20), str_repeat('9', 20)) === null, 'a product over 30 digits is empty', $failures);
standalone_assert(Decimal::add('0.1', '0.2') === '0.3', '0.1 + 0.2 still 0.3', $failures);

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
exit(0);
