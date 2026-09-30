<?php

/**
 * Library formulas parse, and BMI, age, and the EQ-5D profile use the formula engine.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\services\formula\Context;
use humhub\modules\thiscoveryForms\services\formula\Evaluator;
use humhub\modules\thiscoveryForms\services\formula\FormulaLibrary;
use humhub\modules\thiscoveryForms\services\formula\Parser;
use humhub\modules\thiscoveryForms\services\formula\Value;

$failures = [];
$parser = new Parser();
foreach (FormulaLibrary::all() as $name => $text) {
    try {
        $parser->parse($text);
    } catch (Throwable $e) {
        $failures[] = $name . ' ' . $e->getMessage();
    }
}

$context = new Context();
$context->today = '2026-09-30';
$context->fields['weight_kg'] = Value::number('80');
$context->fields['height_m'] = Value::number('1.6');
$bmi = (new Evaluator($context))->evaluate($parser->parse(FormulaLibrary::bmi()));
if ($bmi->type !== 'number' || (string)$bmi->data !== '31.3') {
    $failures[] = 'bmi ' . (string)$bmi->data;
}

$context->fields['dob'] = Value::date('2000-09-30');
$age = (new Evaluator($context))->evaluate($parser->parse(FormulaLibrary::age()));
if ($age->type !== 'number' || (string)$age->data !== '26') {
    $failures[] = 'age ' . (string)$age->data;
}

foreach (['d1', 'd2', 'd3', 'd4', 'd5'] as $i => $name) {
    $context->fields[$name] = Value::text((string)($i + 1));
    $context->scores[$name] = ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5'];
}
$profile = (new Evaluator($context))->evaluate($parser->parse(FormulaLibrary::eq5dProfile()));
if ((string)$profile->data !== '12345') {
    $failures[] = 'profile ' . (string)$profile->data;
}
$sum = (new Evaluator($context))->evaluate($parser->parse(FormulaLibrary::eq5dLevelSum()));
if ((string)$sum->data !== '15') {
    $failures[] = 'level sum ' . (string)$sum->data;
}

$named = new Context();
$named->fields['weight_kg'] = Value::number('80');
$named->fields['height_m'] = Value::number('1.6');
$named->named['bmi'] = $parser->parse(FormulaLibrary::bmi());
$viaName = (new Evaluator($named))->evaluate($parser->parse('fn:bmi'));
if ((string)$viaName->data !== '31.3') {
    $failures[] = 'fn:bmi ' . (string)$viaName->data;
}

if ($failures) {
    fwrite(STDERR, "FAIL\n" . implode("\n", $failures) . "\n");
    exit(1);
}
fwrite(STDOUT, "PASS\n");
