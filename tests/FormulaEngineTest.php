<?php

/**
 * Formula parser and evaluator.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\services\formula\Context;
use humhub\modules\thiscoveryForms\services\formula\Evaluator;
use humhub\modules\thiscoveryForms\services\formula\FormulaException;
use humhub\modules\thiscoveryForms\services\formula\Parser;
use humhub\modules\thiscoveryForms\services\formula\Value;

$failures = [];
$parser = new Parser();

function formula_eval(Parser $parser, string $text, array $fields = [], string $today = '2026-09-30'): Value
{
    $context = new Context();
    $context->today = $today;
    foreach ($fields as $name => $value) {
        $context->fields[$name] = Context::scalar($value);
    }
    return (new Evaluator($context))->evaluate($parser->parse($text));
}

function formula_show(Value $value): string
{
    if ($value->isEmpty()) {
        return 'empty';
    }
    if ($value->type === 'bool') {
        return $value->data ? 'bool:true' : 'bool:false';
    }
    return $value->type . ':' . (string)$value->data;
}

$cases = [
    ['1 + 2', [], 'number:3'],
    ['0.1 + 0.2', [], 'number:0.3'],
    ['[age] > 3', ['age' => '10 years'], 'bool:false'],
    ['[age] > 3', ['age' => '10'], 'bool:true'],
    ['[age] = ""', ['age' => ''], 'bool:false'],
    ['[age] != ""', ['age' => ''], 'bool:true'],
    ['sum([a], [b])', ['a' => '', 'b' => ''], 'empty'],
    ['sum([a], [b])', ['a' => '', 'b' => '2'], 'number:2'],
    ['contains_text([mood], "a")', ['mood' => 'Ab'], 'bool:true'],
    ['round(1.5, 0)', [], 'number:2'],
    ['round(-1.5, 0)', [], 'number:-2'],
    ['round(1.25, 1)', [], 'number:1.3'],
    ['if([age] > 3, 1, 0)', ['age' => ''], 'number:0'],
    ['date_diff(date("2020-02-29"), date("2021-02-28"), "years")', [], 'number:0'],
    ['date_diff(date("2020-02-29"), date("2021-03-01"), "years")', [], 'number:1'],
    ['add_months(date("2021-01-31"), 1)', [], 'date:2021-02-28'],
    ['[score] = 1', ['score' => '1'], 'bool:true'],
];

foreach ($cases as [$text, $fields, $expect]) {
    $got = formula_show(formula_eval($parser, $text, $fields));
    if ($got !== $expect) {
        $failures[] = $text . ' => ' . $got . ' expected ' . $expect;
    }
}

$tree = $parser->parse('[arm] = "B"');
$context = new Context();
$context->arm = Value::text('B');
if (!(new Evaluator($context))->truth($tree)) {
    $failures[] = 'arm B';
}

try {
    $parser->parse('1 > 2 > 3');
    $failures[] = 'chained comparison was accepted';
} catch (FormulaException $e) {
    if (!str_contains($e->getMessage(), 'chained')) {
        $failures[] = 'chain message ' . $e->getMessage();
    }
}

if ($failures) {
    fwrite(STDERR, "FAIL\n" . implode("\n", $failures) . "\n");
    exit(1);
}
fwrite(STDOUT, "PASS " . count($cases) . "\n");
