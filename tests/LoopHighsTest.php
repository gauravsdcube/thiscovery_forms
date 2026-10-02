<?php
/**
 * V3-15 to V3-20. Loops with numeric repeat codes, nested groups, file and HTML
 * questions, per-repeat visibility, exports, and schema-check memoisation.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\services\LogicEngine;
use humhub\modules\thiscoveryForms\services\LoopService;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

$module = Yii::$app->getModule('thiscovery-forms');
$module->settings->set(Module::SETTING_LOOPS, '1');
ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();

$loopForm = static function (string $title) use ($space): CustomForm {
    $form = ReviewLib::form($space, $title, [
        'allow_anonymous' => 0,
        'allow_multiple' => 1,
        'identity_mode' => CustomForm::IDENTITY_IDENTIFIED,
    ]);
    $form->setSetting('loops_enabled', '1');
    $form->save(false);
    ReviewLib::clearFields($form);
    return $form;
};
$fixedLoop = static function (CustomForm $form, string $variable, array $codes): FormField {
    return ReviewLib::field($form, FormField::TYPE_QUESTION_GROUP, $variable, [
        'variable' => $variable,
        'options' => ['loop' => [
            'source' => 'fixed', 'field_key' => '', 'max' => 0, 'min' => 0, 'randomise' => false, 'show' => null,
            'items' => array_map(static fn($c) => ['code' => (string)$c, 'label' => 'Item ' . $c], $codes),
        ]],
    ]);
};
$cells = static function (FormAnswer $answer, FormField $field): array {
    $out = [];
    foreach (FormAnswerField::find()->where(['answer_id' => (int)$answer->id, 'field_id' => (int)$field->id])->all() as $row) {
        $out[(string)$row->instance_key] = (string)$row->value;
    }
    return $out;
};

try {
    // V3-15: repeat codes 0..n post as a PHP list; they are still instance answers.
    $form = $loopForm('EV V3-15 zero codes');
    $fixedLoop($form, 'likert', [0, 1, 2]);
    $q = ReviewLib::field($form, FormField::TYPE_TEXT, 'Why', ['variable' => 'why', 'required' => 0]);
    ReviewLib::field($form, FormField::TYPE_GROUP_END, 'End likert');
    $form = ReviewLib::publishOpen($form);
    $q = FormField::findOne($q->id);
    $answer = ReviewLib::submit($form, [(int)$q->id => [0 => 'zero', 1 => 'one', 2 => 'two']]);
    $check($answer instanceof FormAnswer, 'the zero-coded loop did not save');
    if ($answer) {
        $got = $cells($answer, $q);
        $check(($got['0'] ?? '') === 'zero' && ($got['2'] ?? '') === 'two', 'zero-coded repeats were not stored per instance: ' . json_encode($got));
    }
    $fields = array_values(ReviewLib::reload($form)->fields);
    $engine = new LogicEngine();
    $values = [(int)$q->id => [0 => 'zero', 1 => '', 2 => 'two']];
    $check($engine->evaluateRule(LogicEngine::fromFormula('count_answered([why[*]]) = 2'), $values, $fields), 'aggregates over zero-coded repeats were empty');
    $check($engine->evaluateRule(LogicEngine::fromFormula('[why["0"]] = "zero"'), $values, $fields), 'the "0" repeat could not be addressed');

    // Integer-like codes 1..n (PHP turns the keys into ints).
    $form1 = $loopForm('EV V3-15 one codes');
    $fixedLoop($form1, 'visits', [1, 2]);
    $n = ReviewLib::field($form1, FormField::TYPE_NUMBER, 'Nights', ['variable' => 'nights', 'required' => 0]);
    ReviewLib::field($form1, FormField::TYPE_GROUP_END, 'End visits');
    $form1 = ReviewLib::publishOpen($form1);
    $fields1 = array_values(ReviewLib::reload($form1)->fields);
    $check($engine->evaluateRule(LogicEngine::fromFormula('sum([nights[*]]) = 5'), [(int)$n->id => [1 => '2', 2 => '3']], $fields1), 'sum over 1..n repeats was wrong');

    // V3-16: Outer{Q1, Inner{Q2}, Q3} and Outer{Plain{P1}, Inner{Q2}}.
    $nested = $loopForm('EV V3-16 nested');
    $fixedLoop($nested, 'outer', ['a', 'b']);
    $q1 = ReviewLib::field($nested, FormField::TYPE_TEXT, 'Q1', ['variable' => 'nq1', 'required' => 0]);
    ReviewLib::field($nested, FormField::TYPE_QUESTION_GROUP, 'Plain', ['variable' => 'plain']);
    $p1 = ReviewLib::field($nested, FormField::TYPE_TEXT, 'P1', ['variable' => 'np1', 'required' => 0]);
    ReviewLib::field($nested, FormField::TYPE_GROUP_END, 'End plain');
    $fixedLoop($nested, 'inner', ['x', 'y', 'z']);
    $q2 = ReviewLib::field($nested, FormField::TYPE_TEXT, 'Q2', ['variable' => 'nq2', 'required' => 0]);
    ReviewLib::field($nested, FormField::TYPE_GROUP_END, 'End inner');
    $q3 = ReviewLib::field($nested, FormField::TYPE_TEXT, 'Q3', ['variable' => 'nq3', 'required' => 0]);
    ReviewLib::field($nested, FormField::TYPE_GROUP_END, 'End outer');
    $nested = ReviewLib::publishOpen($nested);
    $nestedFields = array_values($nested->fields);
    $expanded = (new LoopService())->expandPages([
        'pages' => [['pageKey' => 'p', 'title' => '', 'break' => null, 'items' => $nestedFields]],
        'pageKeyIndex' => ['p' => 0],
    ], $nested, $nestedFields, []);
    $where = [];
    foreach ($expanded['pages'] as $page) {
        foreach ($page['items'] as $item) {
            $where[(int)$item->id][] = (string)$page['instanceKey'];
        }
    }
    $check(($where[(int)$q3->id] ?? []) === ['a', 'b'], 'Q3 should show once per outer repeat, got ' . json_encode($where[(int)$q3->id] ?? []));
    $check(count($where[(int)$q2->id] ?? []) === 6 && in_array('b/z', $where[(int)$q2->id], true), 'Q2 should show per inner repeat, got ' . json_encode($where[(int)$q2->id] ?? []));
    $check(($where[(int)$p1->id] ?? []) === ['a', 'b'], 'P1 in a plain group should show per outer repeat, got ' . json_encode($where[(int)$p1->id] ?? []));
    $check(($where[(int)$q1->id] ?? []) === ['a', 'b'], 'Q1 should show per outer repeat');

    // V3-17 / V3-18: every type is parsed per repeat, and "If yes, when?" is judged per repeat.
    $perRepeat = $loopForm('EV V3-17-18 per repeat');
    $fixedLoop($perRepeat, 'visit', ['a', 'b']);
    $had = ReviewLib::field($perRepeat, FormField::TYPE_RADIO, 'Any symptoms?', [
        'variable' => 'had', 'required' => 1, 'options' => ['yes', 'no'],
    ]);
    $when = ReviewLib::field($perRepeat, FormField::TYPE_TEXT, 'When?', [
        'variable' => 'when_started', 'required' => 1, 'logic' => LogicEngine::fromFormula('[had] = "yes"'),
    ]);
    $rank = ReviewLib::field($perRepeat, FormField::TYPE_RANKING, 'Rank', ['variable' => 'rank', 'required' => 0, 'options' => ['x', 'y']]);
    ReviewLib::field($perRepeat, FormField::TYPE_GROUP_END, 'End visit');
    $perRepeat = ReviewLib::publishOpen($perRepeat);
    $post = ['SubmitForm' => ['values' => [
        (string)$had->id => ['a' => 'yes', 'b' => 'no'],
        (string)$when->id => ['a' => 'Monday', 'b' => 'posted but hidden'],
        (string)$rank->id => ['a' => json_encode(['y', 'x']), 'b' => json_encode(['x', 'y'])],
    ]]];
    $submit = new \humhub\modules\thiscoveryForms\models\SubmitForm();
    $submit->form = ReviewLib::reload($perRepeat);
    $submit->loadValuesFromRequest($post);
    $check(($submit->values[(int)$rank->id]['a'] ?? null) === ['y', 'x'], 'a ranking repeat was not parsed: ' . json_encode($submit->values[(int)$rank->id] ?? null));
    $saved = $submit->save(null, false, false, false);
    $check($saved instanceof FormAnswer, 'a hidden follow-up in one repeat blocked the submit: ' . implode(' ', $submit->getErrorSummary(true)));
    if ($saved) {
        $got = $cells($saved, $when);
        $check(($got['a'] ?? '') === 'Monday' && !isset($got['b']), 'the follow-up was not stored per repeat: ' . json_encode($got));
    }
    $missing = new \humhub\modules\thiscoveryForms\models\SubmitForm();
    $missing->form = ReviewLib::reload($perRepeat);
    $missing->loadValuesFromRequest(['SubmitForm' => ['values' => [
        (string)$had->id => ['a' => 'no', 'b' => 'yes'],
        (string)$when->id => ['a' => '', 'b' => ''],
    ]]]);
    $check($missing->save(null, false, false, false) === null && str_contains(implode(' ', $missing->getErrorSummary(true)), 'Item b'), 'a follow-up shown in repeat b was not required there');

    // V3-19: a choices loop with a maximum still exports the options that were selected.
    $choiceForm = $loopForm('EV V3-19 choices max');
    $pick = ReviewLib::field($choiceForm, FormField::TYPE_CHECKBOX, 'Pick', ['variable' => 'pick', 'options' => ['a', 'b', 'c', 'd']]);
    ReviewLib::field($choiceForm, FormField::TYPE_QUESTION_GROUP, 'Each pick', [
        'variable' => 'each_pick',
        'options' => ['loop' => ['source' => 'choices', 'field_key' => 'pick', 'max' => 2, 'min' => 0, 'items' => [], 'randomise' => false, 'show' => null]],
    ]);
    $note = ReviewLib::field($choiceForm, FormField::TYPE_TEXT, 'Note', ['variable' => 'pnote', 'required' => 0]);
    ReviewLib::field($choiceForm, FormField::TYPE_GROUP_END, 'End pick');
    $choiceForm = ReviewLib::publishOpen($choiceForm);
    $exported = ReviewLib::submit($choiceForm, [
        (int)$pick->id => ['c', 'd'],
        (int)$note->id => ['c' => 'see', 'd' => '=1+1'],
    ]);
    $check($exported instanceof FormAnswer, 'the choices-loop answer did not save');
    $wide = (new \humhub\modules\thiscoveryForms\services\ExportService())->toCsv($choiceForm, ['header_mode' => 'variable']);
    $check(str_contains($wide, 'pnote__c') && str_contains($wide, 'pnote__d') && str_contains($wide, 'see'), 'selected options past the first N defined had no export column');
    $long = (new \humhub\modules\thiscoveryForms\services\ExportService())->toCsv($choiceForm, ['long' => 1]);
    $check(str_contains($long, 'pnote') && str_contains($long, 'see'), 'the long export (through toCsv) had no loop rows');
    $check(str_contains($long, "'=1+1") && !preg_match('/(^|,)=1\+1/m', $long), 'the long export did not neutralise a formula cell');

    // V3-45: unticking a repeat clears it, "Other" text is per repeat, repeats are not collapsed.
    $v45 = $loopForm('EV V3-45 repeats');
    $fixedLoop($v45, 'site', ['a', 'b']);
    $where45 = ReviewLib::field($v45, FormField::TYPE_CHECKBOX, 'Where?', [
        'variable' => 'where45', 'required' => 0, 'options' => ['Home', 'Clinic', 'Other'],
    ]);
    ReviewLib::field($v45, FormField::TYPE_GROUP_END, 'End site');
    $v45 = ReviewLib::publishOpen($v45);
    $first45 = new \humhub\modules\thiscoveryForms\models\SubmitForm();
    $first45->form = ReviewLib::reload($v45);
    $first45->loadValuesFromRequest(['SubmitForm' => [
        'values' => [(string)$where45->id => ['a' => ['', 'Home', 'Other'], 'b' => ['', 'Other']]],
        'other_text' => [(string)$where45->id => ['a' => 'garden', 'b' => 'work']],
    ]]);
    $check(($first45->values[(int)$where45->id]['a'] ?? []) === ['Home', 'Other: garden'], 'repeat a did not keep its own other text: ' . json_encode($first45->values[(int)$where45->id] ?? null));
    $check(($first45->values[(int)$where45->id]['b'] ?? []) === ['Other: work'], 'repeat b took another repeat\'s other text');
    $saved45 = $first45->save(null, false, false, false);
    $check($saved45 instanceof FormAnswer, 'the repeat answer did not save');
    if ($saved45) {
        $edit45 = new \humhub\modules\thiscoveryForms\models\SubmitForm();
        $edit45->form = ReviewLib::reload($v45);
        $edit45->loadValuesFromRequest(['SubmitForm' => ['values' => [(string)$where45->id => ['a' => [''], 'b' => ['', 'Clinic']]]]]);
        $edit45->changeReason = 'test';
        $edit45->save(FormAnswer::findOne((int)$saved45->id), false, false, false);
        $got = $cells(FormAnswer::findOne((int)$saved45->id), $where45);
        $check(!isset($got['a']) && isset($got['b']), 'unticking every box in a repeat did not clear it: ' . json_encode($got));
        $reloaded = FormAnswer::findOne((int)$saved45->id);
        $check($reloaded->getFieldValue((int)$where45->id) === null, 'getFieldValue returned an arbitrary repeat');
        $check(count($reloaded->loopCells((int)$where45->id)) === 1, 'loopCells did not list the repeats');
        $labels = (new LoopService())->instanceLabels($reloaded, FormField::findOne((int)$where45->id));
        $check(($labels['b'] ?? '') === 'Item b', 'repeats were not given readable labels: ' . json_encode($labels));
    }

    // V3-46: loop settings survive question export and import, and loop items translate.
    $qio = new \humhub\modules\thiscoveryForms\services\QuestionImportExportService();
    $loopOf = static function (CustomForm $form): ?array {
        foreach (ReviewLib::reload($form)->fields as $f) {
            $cfg = (new LoopService())->config($f);
            if ($cfg) {
                return $cfg;
            }
        }
        return null;
    };
    foreach (['json', 'csv'] as $format) {
        $copy = $loopForm('EV V3-46 import ' . $format);
        $error = $format === 'json'
            ? $qio->importJson($copy, $qio->exportJsonString(ReviewLib::reload($choiceForm)), true)
            : $qio->importCsv($copy, $qio->exportCsv(ReviewLib::reload($choiceForm)), true);
        $check($error === null, $format . ' import of a loop form failed: ' . (string)$error);
        $cfg = $loopOf($copy);
        $check($cfg !== null && $cfg['source'] === 'choices' && $cfg['max'] === 2, $format . ' import lost the loop settings: ' . json_encode($cfg));
        if ($cfg) {
            $src = null;
            foreach (ReviewLib::reload($copy)->fields as $f) {
                if ((string)$f->variable === $cfg['field_key'] || (string)$f->id === $cfg['field_key']) {
                    $src = $f;
                }
            }
            $check($src !== null && (int)$src->form_id === (int)$copy->id, $format . ' import left the loop pointing at another form\'s question');
        }
    }
    $fixedGroup = null;
    foreach (ReviewLib::reload($form)->fields as $f) {
        if ($f->type === FormField::TYPE_QUESTION_GROUP) {
            $fixedGroup = $f;
        }
    }
    $tio = new \humhub\modules\thiscoveryForms\services\TranslationImportExportService();
    $unitKey = 'field.' . (int)$fixedGroup->id . '.loop_item.c:0';
    $keys = array_column($tio->exportJson(ReviewLib::reload($form))['strings'], 'key');
    $check(in_array($unitKey, $keys, true), 'translation export has no field-keyed loop item unit');
    $imported = $tio->importJson(ReviewLib::reload($form), json_encode(['strings' => [['key' => $unitKey, 'translations' => ['cy' => 'Eitem sero']]]]));
    $check($imported === null, 'loop item translation import failed: ' . (string)$imported);
    $cyForm = ReviewLib::reload($form);
    (new \humhub\modules\thiscoveryForms\services\TranslationService())->overlay($cyForm, 'cy');
    $cyLabel = '';
    foreach ($cyForm->fields as $f) {
        if ((int)$f->id === (int)$fixedGroup->id) {
            $cyLabel = (string)((new LoopService())->config($f)['items'][0]['label'] ?? '');
        }
    }
    $check($cyLabel === 'Eitem sero', 'the translated loop item was not shown: ' . $cyLabel);

    // V3-55: repeat codes are validated on every loop, and repeats are capped.
    $codes55 = $loopForm('EV V3-55 codes');
    $fixedLoop($codes55, 'bad55', ['a', 'a', 'x/y', 'p__q']);
    ReviewLib::field($codes55, FormField::TYPE_TEXT, 'Inner', ['variable' => 'inner55', 'required' => 0]);
    ReviewLib::field($codes55, FormField::TYPE_GROUP_END, 'End bad');
    $errors55 = implode(' ', (new LoopService())->authoringErrors(ReviewLib::reload($codes55)));
    $check(str_contains($errors55, 'twice') && str_contains($errors55, 'x/y') && str_contains($errors55, 'p__q'), 'bad repeat codes were accepted: ' . $errors55);
    $big = new FormField();
    $big->type = FormField::TYPE_QUESTION_GROUP;
    $big->options_json = json_encode(['loop' => ['source' => 'number', 'field_key' => 'n', 'max' => 100000]]);
    $check((new LoopService())->config($big)['max'] === LoopService::MAX_REPEATS, 'loop_max was not capped');

    // V3-20: after the first call, schema checks and a loaded answer's values map run no queries.
    if ($exported) {
        $loaded = FormAnswer::find()->with('answerFields')->where(['id' => (int)$exported->id])->one();
        $loaded->populateRelation('form', ReviewLib::reload($choiceForm));
        $loaded->getValuesMap();
        LoopService::columnReady();
        $queries = static function (): int {
            return (int)(Yii::getLogger()->getDbProfiling()[0] ?? 0);
        };
        $before = $queries();
        for ($i = 0; $i < 50; $i++) {
            LoopService::columnReady();
            LoopService::rosterColumnReady();
            $loaded->getValuesMap();
        }
        $spent = $queries() - $before;
        $check($spent === 0, '50 values-map reads ran ' . $spent . ' queries');
    }
} finally {
    $module->settings->set(Module::SETTING_LOOPS, '0');
}

if ($failures) {
    echo 'FAIL ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
