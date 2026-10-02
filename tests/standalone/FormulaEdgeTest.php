<?php
/** V3-38: in/not_in lists parse; dependencies and cycles follow named formulas. */
require __DIR__ . '/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\formula\Context;
use humhub\modules\thiscoveryForms\services\formula\Evaluator;
use humhub\modules\thiscoveryForms\services\formula\FormulaDeps;
use humhub\modules\thiscoveryForms\services\formula\FormulaRuntime;
use humhub\modules\thiscoveryForms\services\formula\Parser;

$failures = [];
$parser = new Parser();
$truth = static function (string $formula, array $values) use ($parser): bool {
    return (new Evaluator(Context::fromValues($values, [new FormField(1, 'a', 'radio'), new FormField(2, 'b', 'text')], '2026-09-30')))->truth($parser->parse($formula));
};

// [a] in [1, 2] used to fail: every [ was read as a reference.
standalone_assert($truth('[a] in [1, 2]', [1 => '2']), '[a] in [1, 2] with a=2', $failures);
standalone_assert(!$truth('[a] in [1, 2]', [1 => '3']), '[a] in [1, 2] with a=3', $failures);
standalone_assert($truth('[a] not_in ["x", [b]]', [1 => '1', 2 => 'y']), 'not_in with a reference inside the list', $failures);
standalone_assert(!$truth('[a] not_in ["x", [b]]', [1 => 'y', 2 => 'y']), 'not_in matches the referenced item', $failures);

// Dependencies follow fn: into the named formula.
$named = ['bmi' => $parser->parse('[w] / ([h] * [h])')];
standalone_assert(FormulaDeps::names($parser->parse('fn:bmi > 30'), $named) === ['h', 'w'], 'fn: dependencies', $failures);

// A calculation that calls a named formula runs after the calculation that formula reads.
$fields = [new FormField(1, 'score', 'calculated', 'fn:double_base'), new FormField(2, 'base', 'calculated', '3 + 4')];
$values = ['__named' => ['double_base' => $parser->parse('[base] * 2')]];
FormulaRuntime::fill($values, $fields, '2026-09-30');
standalone_assert(($values[1] ?? null) === '14', 'fn: dependency ordered, got ' . var_export($values[1] ?? null, true), $failures);

// A cycle through a named formula leaves both empty.
$fields = [new FormField(1, 'p', 'calculated', 'fn:via_q + 1'), new FormField(2, 'q', 'calculated', '[p] + 1')];
$values = ['__named' => ['via_q' => $parser->parse('[q]')]];
FormulaRuntime::fill($values, $fields, '2026-09-30');
standalone_assert(array_key_exists(1, $values) && $values[1] === null && array_key_exists(2, $values) && $values[2] === null, 'cycle through fn: left empty', $failures);

// V3-42: score_of on a scored question's unscored option ("prefer not to say" = 9) is empty.
$scored = [new FormField(5, 'phq', 'radio', '', [['code' => '0', 'label' => 'Not at all', 'score' => '0'], ['code' => '1', 'label' => 'Some days', 'score' => '1'], ['code' => '9', 'label' => 'Prefer not to say']])];
$score = static fn(string $code) => (new Evaluator(Context::fromValues([5 => $code], $scored, '2026-09-30')))->evaluate($parser->parse('score_of([phq])'));
standalone_assert($score('1')->type === 'number' && $score('1')->data === '1', 'score_of scored option', $failures);
standalone_assert($score('9')->isEmpty(), 'score_of on an unscored option added its code', $failures);

// LOG-10: "all of these" and "exactly these" on a checkbox.
$cb = [new FormField(7, 'sym', 'checkbox')];
$cbTruth = static fn(string $formula, $value) => (new Evaluator(Context::fromValues([7 => $value], $cb, '2026-09-30')))->truth($parser->parse($formula));
standalone_assert($cbTruth('selected_all([sym], "a", "b")', ['a', 'b', 'c']), 'selected_all with extra ticks', $failures);
standalone_assert(!$cbTruth('selected_all([sym], "a", "b")', ['a', 'c']), 'selected_all with one missing', $failures);
standalone_assert(!$cbTruth('selected_only([sym], "a", "b")', ['a', 'b', 'c']), 'selected_only with an extra tick', $failures);
standalone_assert($cbTruth('selected_only([sym], "b", "a")', ['a', 'b']), 'selected_only in any order', $failures);
standalone_assert(!$cbTruth('selected_only([sym], "a")', []), 'selected_only with nothing ticked', $failures);
standalone_assert($cbTruth('selected_only([sym], "0")', ['0']), 'selected_only with code 0', $failures);

// V3-54: real dates only, known functions only, chains do not count as depth, concat by
// character, contains_text on a list means one of the items.
foreach (['date("2024-02-30")', 'date("2024-2-3")', 'frobnicate([a])'] as $bad) {
    $refused = false;
    try {
        $parser->parse($bad);
    } catch (\Throwable $e) {
        $refused = true;
    }
    standalone_assert($refused, 'accepted ' . $bad, $failures);
}
$chain = implode(' + ', array_fill(0, 60, '1'));
$tree = $parser->parse($chain);
$limitsOk = true;
try {
    \humhub\modules\thiscoveryForms\services\formula\Limits::assertTree($tree);
} catch (\Throwable $e) {
    $limitsOk = false;
}
standalone_assert($limitsOk, 'a 60-term sum hit the depth limit', $failures);
$long = (new Evaluator(Context::fromValues([2 => str_repeat('é', 1999) . 'ü'], [new FormField(1, 'a', 'radio'), new FormField(2, 'b', 'text')], '2026-09-30')))->evaluate($parser->parse('concat([b], "ö")'));
standalone_assert(mb_check_encoding((string)$long->data, 'UTF-8') && mb_strlen((string)$long->data) === 2000, 'concat split a UTF-8 character', $failures);
$animals = [new FormField(3, 'pets', 'checkbox')];
$hasPet = static fn(string $needle) => (new Evaluator(Context::fromValues([3 => ['mammal', 'bird']], $animals, '2026-09-30')))->truth($parser->parse('contains_text([pets], "' . $needle . '")'));
standalone_assert($hasPet('bird') && !$hasPet('ma'), 'contains_text on a list matched a substring', $failures);

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
exit(0);
