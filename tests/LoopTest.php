<?php
/**
 * F5. One-level loops. Hidden instances stay in the table and leave the default export.
 */
require __DIR__ . '/support/bootstrap.php';
require_once dirname(__DIR__) . '/migrations/m261001_100000_answer_instance_key.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\services\ExportService;
use humhub\modules\thiscoveryForms\services\LogicEngine;
use humhub\modules\thiscoveryForms\services\LoopService;
use humhub\modules\thiscoveryForms\services\VariableSubstitutor;
use yii\db\Query;

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
$loops = new LoopService();

try {
    $form = ReviewLib::form($space, 'EV F5 loops', [
        'allow_anonymous' => 0,
        'allow_multiple' => 1,
        'allow_resume' => 1,
        'identity_mode' => CustomForm::IDENTITY_IDENTIFIED,
    ]);
    $form->setSetting('loops_enabled', '1');
    $form->save(false);
    ReviewLib::clearFields($form);
    FormAnswerField::deleteAll(['answer_id' => (new Query())->select('id')->from('custom_form_answer')->where(['form_id' => (int)$form->id])]);
    \humhub\modules\thiscoveryForms\models\FormAnswer::deleteAll(['form_id' => (int)$form->id]);

    $conditions = ReviewLib::field($form, FormField::TYPE_CHECKBOX, 'Conditions', [
        'variable' => 'conditions',
        'options' => ['asthma', 'diabetes'],
    ]);
    ReviewLib::field($form, FormField::TYPE_QUESTION_GROUP, 'Each condition', [
        'variable' => 'each_condition',
        'options' => [
            'loop' => [
                'source' => 'choices',
                'field_key' => 'conditions',
                'max' => 8,
                'min' => 0,
                'items' => [],
                'randomise' => false,
                'show' => null,
            ],
        ],
    ]);
    $symptom = ReviewLib::field($form, FormField::TYPE_TEXT, 'Symptom', ['variable' => 'symptom']);
    ReviewLib::field($form, FormField::TYPE_GROUP_END, 'End conditions');
    $form = ReviewLib::publishOpen($form);
    $conditions = FormField::findOne($conditions->id);
    $symptom = FormField::findOne($symptom->id);

    $answer = ReviewLib::submit($form, [
        (int)$conditions->id => ['asthma', 'diabetes'],
        (int)$symptom->id => ['asthma' => 'wheeze', 'diabetes' => 'thirst', 'nope' => 'drop-me'],
    ]);
    $check($answer !== null, 'two conditions saved');
    $rows = FormAnswerField::find()->where(['answer_id' => (int)$answer->id, 'field_id' => (int)$symptom->id])->all();
    $byKey = [];
    foreach ($rows as $row) {
        $byKey[(string)$row->instance_key] = (string)$row->value;
    }
    $check(isset($byKey['asthma']) && $byKey['asthma'] === 'wheeze', 'asthma cell');
    $check(isset($byKey['diabetes']) && $byKey['diabetes'] === 'thirst', 'diabetes cell');
    $check(!isset($byKey['nope']), 'unknown instance is dropped');

    $map = $answer->getValuesMap();
    $check(is_array($map[(int)$symptom->id] ?? null) && ($map[(int)$symptom->id]['asthma'] ?? '') === 'wheeze', 'values map nests the loop');
    $check(!is_array($map[(int)$conditions->id] ?? null) || array_is_list((array)$map[(int)$conditions->id]), 'the source stays a list');

    ReviewLib::submit($form, [
        (int)$conditions->id => ['asthma'],
        (int)$symptom->id => ['asthma' => 'wheeze'],
    ], false, $answer);
    $hidden = FormAnswerField::find()->where([
        'answer_id' => (int)$answer->id,
        'field_id' => (int)$symptom->id,
        'instance_key' => 'diabetes',
    ])->one();
    $check($hidden && (string)$hidden->value === 'thirst', 'unselected instance stays in the table');

    $csv = (new ExportService())->toCsv($form, ['header_mode' => ExportService::HEADER_VARIABLE]);
    $check(str_contains($csv, 'symptom__asthma') && str_contains($csv, 'symptom__diabetes'), 'wide columns');
    $check(str_contains($csv, 'wheeze'), 'shown instance is exported');
    $lines = preg_split('/\r\n|\n/', trim($csv));
    $header = str_getcsv((string)$lines[0]);
    $data = str_getcsv((string)($lines[1] ?? ''));
    $cells = array_combine($header, $data);
    $check(($cells['symptom__diabetes'] ?? 'missing') === '', 'hidden instance is blank in the default export');
    $codebook = (new ExportService())->codebookCsv($form);
    $check(str_contains($codebook, 'symptom__asthma'), 'codebook lists the instance column');

    $shownAgain = ReviewLib::submit($form, [
        (int)$conditions->id => ['asthma', 'diabetes'],
        (int)$symptom->id => ['asthma' => 'wheeze'],
    ], false, $answer);
    $restored = $shownAgain ? $shownAgain->getValue((int)$symptom->id, 'diabetes') : null;
    $check($restored === 'thirst', 'selecting the option again restores the kept answer');

    $engine = new LogicEngine();
    $logicValues = [(int)$symptom->id => ['asthma' => 'wheeze', 'diabetes' => 'thirst']];
    $fields = array_values($form->fields);
    $check($engine->evaluateRule([
        'fieldKey' => 'symptom', 'operator' => 'equals', 'value' => 'wheeze', 'aggregate' => 'any',
    ], $logicValues, $fields), 'any matches one instance');
    $check(!$engine->evaluateRule([
        'fieldKey' => 'symptom', 'operator' => 'equals', 'value' => 'wheeze', 'aggregate' => 'all',
    ], $logicValues, $fields), 'all needs every instance');
    $check($engine->evaluateRule([
        'fieldKey' => 'symptom', 'operator' => 'gt', 'value' => '1', 'aggregate' => 'count',
    ], $logicValues, $fields), 'count compares the number of answers');

    LoopService::$pipe = ['label' => 'Asthma', 'index' => 2, 'count' => 4, 'key' => 'asthma'];
    $piped = (new VariableSubstitutor())->substitutePlain(
        '{{loop.label}} {{loop.index}} {{answer:symptom[diabetes]}}',
        null,
        $form,
        $logicValues,
        $fields
    );
    $check(str_contains($piped, 'Asthma') && str_contains($piped, 'thirst'), 'piping reads the instance and the loop label');
    LoopService::$pipe = null;

    $nested = ReviewLib::form($space, 'EV F5 nested', [
        'allow_anonymous' => 0,
        'identity_mode' => CustomForm::IDENTITY_IDENTIFIED,
    ]);
    $nested->setSetting('loops_enabled', '1');
    $nested->save(false);
    ReviewLib::clearFields($nested);
    ReviewLib::field($nested, FormField::TYPE_QUESTION_GROUP, 'Outer', [
        'options' => ['loop' => ['source' => 'fixed', 'max' => 2, 'items' => [['code' => 'a', 'label' => 'A']]]],
    ]);
    ReviewLib::field($nested, FormField::TYPE_QUESTION_GROUP, 'Inner', [
        'options' => ['loop' => ['source' => 'fixed', 'max' => 2, 'items' => [['code' => 'b', 'label' => 'B']]]],
    ]);
    ReviewLib::field($nested, FormField::TYPE_GROUP_END, 'End inner');
    ReviewLib::field($nested, FormField::TYPE_GROUP_END, 'End outer');
    $nestedErrors = $loops->authoringErrors(ReviewLib::reload($nested));
    $check(in_array('A loop cannot contain another loop.', $nestedErrors, true), 'nested loop is refused');

    $bad = ReviewLib::form($space, 'EV F5 aggregate', [
        'allow_anonymous' => 0,
        'identity_mode' => CustomForm::IDENTITY_IDENTIFIED,
    ]);
    $bad->setSetting('loops_enabled', '1');
    $bad->save(false);
    ReviewLib::clearFields($bad);
    ReviewLib::field($bad, FormField::TYPE_TEXT, 'Score', [
        'logic' => ['rules' => [['fieldKey' => 'score', 'operator' => 'equals', 'value' => '1', 'aggregate' => 'mean']]],
    ]);
    $badErrors = $loops->authoringErrors(ReviewLib::reload($bad));
    $check($badErrors !== [] && str_contains(implode(' ', $badErrors), 'mean'), 'unknown aggregate fails authoring');

    $numberForm = ReviewLib::form($space, 'EV F5 number', [
        'allow_anonymous' => 0,
        'allow_multiple' => 1,
        'allow_resume' => 1,
        'identity_mode' => CustomForm::IDENTITY_IDENTIFIED,
    ]);
    $numberForm->setSetting('loops_enabled', '1');
    $numberForm->save(false);
    ReviewLib::clearFields($numberForm);
    $howMany = ReviewLib::field($numberForm, FormField::TYPE_NUMBER, 'How many', ['variable' => 'how_many']);
    ReviewLib::field($numberForm, FormField::TYPE_QUESTION_GROUP, 'Repeats', [
        'options' => ['loop' => ['source' => 'number', 'field_key' => 'how_many', 'max' => 4, 'items' => []]],
    ]);
    $note = ReviewLib::field($numberForm, FormField::TYPE_TEXT, 'Note', ['variable' => 'note']);
    ReviewLib::field($numberForm, FormField::TYPE_GROUP_END, 'End repeats');
    $numberForm = ReviewLib::publishOpen($numberForm);
    $howMany = FormField::findOne($howMany->id);
    $note = FormField::findOne($note->id);
    $numbered = ReviewLib::submit($numberForm, [
        (int)$howMany->id => '2',
        (int)$note->id => ['n1' => 'first', 'n2' => 'second'],
    ]);
    $check($numbered && $numbered->getValue((int)$note->id, 'n2') === 'second', 'number source writes n2');
    $numbered->current_instance_key = 'n2';
    $numbered->save(false, ['current_instance_key']);
    ReviewLib::submit($numberForm, [
        (int)$howMany->id => '1',
        (int)$note->id => ['n1' => 'first'],
    ], false, $numbered);
    $kept = FormAnswerField::find()->where([
        'answer_id' => (int)$numbered->id,
        'field_id' => (int)$note->id,
        'instance_key' => 'n2',
    ])->one();
    $check($kept && (string)$kept->value === 'second', 'reducing the number hides n2 and does not renumber it');
    $check((string)$numbered->current_instance_key === 'n2', 'resume keeps the instance key');

    $module->settings->set(Module::SETTING_LOOPS, '0');
    $plain = ReviewLib::form($space, 'EV F5 flag off', [
        'allow_anonymous' => 0,
        'allow_multiple' => 1,
        'identity_mode' => CustomForm::IDENTITY_IDENTIFIED,
    ]);
    $plain->setSetting('loops_enabled', '0');
    $plain->save(false);
    ReviewLib::clearFields($plain);
    $colour = ReviewLib::field($plain, FormField::TYPE_TEXT, 'Colour', ['variable' => 'colour']);
    $plain = ReviewLib::publishOpen($plain);
    $colour = FormField::findOne($colour->id);
    $flat = ReviewLib::submit($plain, [(int)$colour->id => 'blue']);
    $flatMap = $flat ? $flat->getValuesMap() : [];
    $check(array_key_exists((int)$colour->id, $flatMap) && $flatMap[(int)$colour->id] === 'blue', 'flag off keeps a flat map');
    $check(!is_array($flatMap[(int)$colour->id] ?? null), 'flag off value is not nested');
    $flatRow = FormAnswerField::find()->where(['answer_id' => (int)$flat->id, 'field_id' => (int)$colour->id])->one();
    $check($flatRow && (string)$flatRow->instance_key === '', 'flag off writes a blank instance key');

    $migration = new m261001_100000_answer_instance_key();
    $migration->init();
    $down = $migration->safeDown();
    $check($down === false, 'migration down refuses while a loop answer exists');
    $still = Yii::$app->db->schema->getTableSchema('{{%custom_form_answer_field}}', true);
    $check($still && isset($still->columns['instance_key']), 'instance_key column remains');

    $node = dirname(__DIR__) . '/tests/test-vectors/loops/aggregates.js';
    $nodeBin = trim((string)shell_exec('command -v node'));
    if ($nodeBin !== '' && is_file($node)) {
        exec(escapeshellarg($nodeBin) . ' ' . escapeshellarg($node), $nodeOut, $nodeCode);
        $check($nodeCode === 0, 'node aggregate vector ' . implode(' ', $nodeOut));
    }
} finally {
    $module->settings->set(Module::SETTING_LOOPS, '0');
}

if ($failures) {
    echo 'FAIL ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
