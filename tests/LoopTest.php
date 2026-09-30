<?php
/**
 * F5. One-level loops. Hidden instances stay in the table and leave the default export.
 */
require __DIR__ . '/support/bootstrap.php';
require_once dirname(__DIR__) . '/migrations/m261001_100000_answer_instance_key.php';
require_once dirname(__DIR__) . '/migrations/m261001_120000_widen_instance_key.php';
require_once dirname(__DIR__) . '/migrations/m261001_130000_roster_json.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\services\DashboardService;
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
        'allow_multiple' => 1,
        'identity_mode' => CustomForm::IDENTITY_IDENTIFIED,
    ]);
    $nested->setSetting('loops_enabled', '1');
    $nested->save(false);
    ReviewLib::clearFields($nested);
    $people = ReviewLib::field($nested, FormField::TYPE_CHECKBOX, 'People', [
        'variable' => 'people',
        'options' => [
            ['code' => 'alex', 'label' => 'Alex'],
            ['code' => 'sam', 'label' => 'Sam'],
        ],
    ]);
    ReviewLib::field($nested, FormField::TYPE_QUESTION_GROUP, 'Each person', [
        'variable' => 'each_person',
        'options' => ['loop' => ['source' => 'choices', 'field_key' => 'people', 'max' => 4, 'items' => []]],
    ]);
    $conditions = ReviewLib::field($nested, FormField::TYPE_CHECKBOX, 'Conditions', [
        'variable' => 'conditions',
        'options' => [
            ['code' => 'asthma', 'label' => 'Asthma'],
            ['code' => 'diabetes', 'label' => 'Diabetes'],
        ],
    ]);
    ReviewLib::field($nested, FormField::TYPE_QUESTION_GROUP, 'Each condition', [
        'variable' => 'each_condition',
        'options' => ['loop' => ['source' => 'choices', 'field_key' => 'conditions', 'max' => 4, 'items' => []]],
    ]);
    $symptom = ReviewLib::field($nested, FormField::TYPE_TEXT, 'Symptom', ['variable' => 'symptom']);
    ReviewLib::field($nested, FormField::TYPE_GROUP_END, 'End condition');
    ReviewLib::field($nested, FormField::TYPE_GROUP_END, 'End person');
    $nestedErrors = $loops->authoringErrors(ReviewLib::reload($nested));
    $check($nestedErrors === [], 'one nested loop can be published: ' . implode(' ', $nestedErrors));
    $nested = ReviewLib::publishOpen($nested);
    $people = FormField::findOne($people->id);
    $conditions = FormField::findOne($conditions->id);
    $symptom = FormField::findOne($symptom->id);
    $nestedAnswer = ReviewLib::submit($nested, [
        (int)$people->id => ['alex', 'sam'],
        (int)$conditions->id => ['alex' => ['asthma'], 'sam' => ['asthma', 'diabetes']],
        (int)$symptom->id => [
            'alex/asthma' => 'wheeze',
            'sam/asthma' => 'mild',
            'sam/diabetes' => 'thirst',
            'nope' => 'drop-me',
        ],
    ]);
    $check($nestedAnswer !== null, 'nested repeats saved ' . json_encode($nestedAnswer ? [] : (new \humhub\modules\thiscoveryForms\models\SubmitForm())->errors));
    if ($nestedAnswer === null) {
        $probe = new \humhub\modules\thiscoveryForms\models\SubmitForm();
        $probe->form = $nested;
        $probe->values = [
            (int)$people->id => ['alex', 'sam'],
            (int)$conditions->id => ['alex' => ['asthma'], 'sam' => ['asthma', 'diabetes']],
            (int)$symptom->id => ['alex/asthma' => 'wheeze'],
        ];
        $probe->validate();
        $check(false, 'nested validation ' . json_encode($probe->errors));
    }
    $nestedMap = $nestedAnswer ? $nestedAnswer->getValuesMap() : [];
    $posted = new \humhub\modules\thiscoveryForms\models\SubmitForm();
    $posted->form = $nested;
    $posted->loadValuesFromRequest([
        'SubmitForm' => ['values' => [
            (string)$conditions->id => ['alex' => ['asthma'], 'sam' => ['diabetes']],
        ]],
    ]);
    $postedConditions = $posted->values[(int)$conditions->id] ?? null;
    $check(is_array($postedConditions) && ($postedConditions['alex'][0] ?? '') === 'asthma' && ($postedConditions['sam'][0] ?? '') === 'diabetes', 'a checkbox inside a repeat keeps the outer instance');
    $check(($nestedMap[(int)$symptom->id]['alex/asthma'] ?? '') === 'wheeze', 'outer and inner path is one cell');
    $check(($nestedMap[(int)$symptom->id]['sam/diabetes'] ?? '') === 'thirst', 'second person keeps a separate cell');
    $check(!isset($nestedMap[(int)$symptom->id]['nope']), 'unknown nested path is dropped');
    $nestedCsv = (new ExportService())->toCsv($nested, ['header_mode' => ExportService::HEADER_VARIABLE]);
    $check(str_contains($nestedCsv, 'symptom__alex__asthma') && str_contains($nestedCsv, 'symptom__sam__diabetes'), 'nested wide columns');
    $check(str_contains((new ExportService())->codebookCsv($nested), 'symptom__alex__asthma'), 'codebook lists the nested column');
    if ($nestedAnswer) {
    ReviewLib::submit($nested, [
        (int)$people->id => ['alex', 'sam'],
        (int)$conditions->id => ['alex' => ['asthma'], 'sam' => ['asthma']],
        (int)$symptom->id => ['alex/asthma' => 'wheeze', 'sam/asthma' => 'mild'],
    ], false, $nestedAnswer);
    }
    $hiddenNested = $nestedAnswer ? FormAnswerField::find()->where([
        'answer_id' => (int)$nestedAnswer->id,
        'field_id' => (int)$symptom->id,
        'instance_key' => 'sam/diabetes',
    ])->one() : null;
    $check($hiddenNested && (string)$hiddenNested->value === 'thirst', 'hiding an inner repeat keeps the cell');
    $nestedFields = array_values($nested->fields);
    $expanded = $loops->expandPages([
        'pages' => [[
            'pageKey' => 'p',
            'title' => '',
            'break' => null,
            'items' => $nestedFields,
        ]],
        'pageKeyIndex' => ['p' => 0],
    ], $nested, $nestedFields, [
        (int)$people->id => ['alex', 'sam'],
        (int)$conditions->id => ['alex' => ['asthma'], 'sam' => ['asthma', 'diabetes']],
    ]);
    $headings = [];
    foreach ($expanded['pages'] as $page) {
        if (!empty($page['instanceHeading'])) {
            $headings[] = $page['instanceHeading'];
        }
    }
    $check(in_array('Alex, 1 of 2 — Asthma, 1 of 1', $headings, true), 'inner page names the outer repeat and the inner repeat');
    $check(in_array('Sam, 2 of 2 — Diabetes, 2 of 2', $headings, true), 'the second person gets a second inner page');
    LoopService::$pipe = [
        'label' => 'Asthma',
        'index' => 1,
        'count' => 1,
        'key' => 'alex/asthma',
        'parent' => ['label' => 'Alex', 'index' => 1, 'count' => 2, 'key' => 'alex'],
    ];
    $nestedPipe = (new VariableSubstitutor())->substitutePlain(
        '{{loop.parent.label}} {{loop.label}} {{answer:symptom[sam/diabetes]}}',
        null,
        $nested,
        $nestedMap,
        $nestedFields
    );
    $check($nestedPipe === 'Alex Asthma thirst', 'piping reads the outer repeat and a nested answer');
    LoopService::$pipe = null;

    $third = ReviewLib::form($space, 'EV F5 third loop', [
        'allow_anonymous' => 0,
        'identity_mode' => CustomForm::IDENTITY_IDENTIFIED,
    ]);
    $third->setSetting('loops_enabled', '1');
    $third->save(false);
    ReviewLib::clearFields($third);
    ReviewLib::field($third, FormField::TYPE_QUESTION_GROUP, 'Outer', [
        'options' => ['loop' => ['source' => 'fixed', 'max' => 2, 'items' => [['code' => 'a', 'label' => 'A']]]],
    ]);
    ReviewLib::field($third, FormField::TYPE_QUESTION_GROUP, 'Inner', [
        'options' => ['loop' => ['source' => 'fixed', 'max' => 2, 'items' => [['code' => 'b', 'label' => 'B']]]],
    ]);
    ReviewLib::field($third, FormField::TYPE_QUESTION_GROUP, 'Third', [
        'options' => ['loop' => ['source' => 'fixed', 'max' => 2, 'items' => [['code' => 'c', 'label' => 'C']]]],
    ]);
    ReviewLib::field($third, FormField::TYPE_GROUP_END, 'End third');
    ReviewLib::field($third, FormField::TYPE_GROUP_END, 'End inner');
    ReviewLib::field($third, FormField::TYPE_GROUP_END, 'End outer');
    $thirdErrors = $loops->authoringErrors(ReviewLib::reload($third));
    $check(in_array('A loop can contain only one nested loop.', $thirdErrors, true), 'a third loop is refused');

    $slash = ReviewLib::form($space, 'EV F5 slash', [
        'allow_anonymous' => 0,
        'identity_mode' => CustomForm::IDENTITY_IDENTIFIED,
    ]);
    $slash->setSetting('loops_enabled', '1');
    $slash->save(false);
    ReviewLib::clearFields($slash);
    ReviewLib::field($slash, FormField::TYPE_QUESTION_GROUP, 'Outer', [
        'options' => ['loop' => ['source' => 'fixed', 'max' => 2, 'items' => [['code' => 'a/b', 'label' => 'A']]]],
    ]);
    ReviewLib::field($slash, FormField::TYPE_QUESTION_GROUP, 'Inner', [
        'options' => ['loop' => ['source' => 'fixed', 'max' => 2, 'items' => [['code' => 'c', 'label' => 'C']]]],
    ]);
    ReviewLib::field($slash, FormField::TYPE_GROUP_END, 'End inner');
    ReviewLib::field($slash, FormField::TYPE_GROUP_END, 'End outer');
    $slashErrors = implode(' ', $loops->authoringErrors(ReviewLib::reload($slash)));
    $check(str_contains($slashErrors, 'a/b'), 'a slash in a nested code is refused');

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

    $dash = (new DashboardService())->getFormDashboard($form);
    $symptomRow = null;
    foreach ($dash['loops']['questions'] ?? [] as $question) {
        if ($question['label'] === 'Symptom') {
            $symptomRow = $question;
        }
    }
    $allRepeats = (int)($symptomRow['counts']['*'] ?? 0);
    $asthmaCount = (int)($symptomRow['counts']['asthma'] ?? 0);
    $diabetesCount = (int)($symptomRow['counts']['diabetes'] ?? 0);
    $check($symptomRow !== null && $allRepeats === $asthmaCount + $diabetesCount, 'dashboard adds every repeat');
    $check($asthmaCount >= 1 && $diabetesCount >= 1 && $asthmaCount !== $allRepeats, 'dashboard can split one instance from the total');

    $roster = ReviewLib::form($space, 'EV F5 roster', [
        'allow_anonymous' => 0,
        'allow_multiple' => 1,
        'identity_mode' => CustomForm::IDENTITY_IDENTIFIED,
    ]);
    $roster->setSetting('loops_enabled', '1');
    $roster->save(false);
    ReviewLib::clearFields($roster);
    ReviewLib::field($roster, FormField::TYPE_QUESTION_GROUP, 'Medication', [
        'variable' => 'medications',
        'options' => ['loop' => ['source' => 'roster', 'max' => 2, 'min' => 0, 'label_field' => 'drug_name', 'items' => []]],
    ]);
    $drug = ReviewLib::field($roster, FormField::TYPE_TEXT, 'Drug', ['variable' => 'drug_name']);
    ReviewLib::field($roster, FormField::TYPE_GROUP_END, 'End medication');
    $rosterErrors = $loops->authoringErrors(ReviewLib::reload($roster));
    $check($rosterErrors === [], 'a roster can be published: ' . implode(' ', $rosterErrors));
    $roster = ReviewLib::publishOpen($roster);
    $drug = FormField::findOne($drug->id);
    $group = null;
    foreach ($roster->fields as $field) {
        if ($field->type === FormField::TYPE_QUESTION_GROUP) {
            $group = $field;
        }
    }
    $rosterAnswer = ReviewLib::submit($roster, []);
    $check($rosterAnswer !== null && $group, 'an empty roster can be saved');
    $firstKey = $group && $rosterAnswer ? $loops->addRow($rosterAnswer, $group) : null;
    $secondKey = $group && $rosterAnswer ? $loops->addRow($rosterAnswer, $group) : null;
    $thirdKey = $group && $rosterAnswer ? $loops->addRow($rosterAnswer, $group) : 'skipped';
    $check(is_string($firstKey) && preg_match('/^r[a-f0-9]{8}$/', $firstKey) === 1, 'the server creates an r key');
    $check(is_string($secondKey) && $secondKey !== $firstKey, 'a second row gets a new key');
    $check($thirdKey === null, 'a roster stops at its maximum');
    if ($rosterAnswer && $firstKey && $secondKey) {
        $rosterAnswer = ReviewLib::submit($roster, [
            (int)$drug->id => [$firstKey => 'Aspirin', $secondKey => 'Ibuprofen', 'rdeadbeef' => 'Forged'],
        ], false, $rosterAnswer);
    }
    $check($rosterAnswer && $rosterAnswer->getValue((int)$drug->id, (string)$firstKey) === 'Aspirin', 'the first row stores the name');
    $check($rosterAnswer && $rosterAnswer->getValue((int)$drug->id, 'rdeadbeef') === null, 'a client cannot invent a roster key');
    $hiddenOk = $rosterAnswer && $group ? $loops->hideRow($rosterAnswer, $group, '', (string)$secondKey) : false;
    $check($hiddenOk, 'removing a row hides it');
    $keptDrug = $rosterAnswer ? FormAnswerField::find()->where([
        'answer_id' => (int)$rosterAnswer->id,
        'field_id' => (int)$drug->id,
        'instance_key' => (string)$secondKey,
    ])->one() : null;
    $check($keptDrug && (string)$keptDrug->value === 'Ibuprofen', 'a removed row stays in the table');
    $check($rosterAnswer && $group && !in_array((string)$secondKey, $loops->rosterInstanceKeys($rosterAnswer, $group), true), 'a removed row leaves the shown list');
    $long = $rosterAnswer ? (new ExportService())->longCsv($roster) : '';
    $check(str_contains($long, 'Aspirin') && str_contains($long, (string)$firstKey), 'the long export lists the shown row');
    $check(!str_contains($long, 'Ibuprofen'), 'the long export omits a removed row');
    $longHidden = $rosterAnswer ? (new ExportService())->longCsv($roster, ['include_hidden_instances' => 1]) : '';
    $check(str_contains($longHidden, 'Ibuprofen'), 'the long export can include a removed row');
    $minForm = ReviewLib::form($space, 'EV F5 roster min', [
        'allow_anonymous' => 0,
        'identity_mode' => CustomForm::IDENTITY_IDENTIFIED,
    ]);
    $minForm->setSetting('loops_enabled', '1');
    $minForm->save(false);
    ReviewLib::clearFields($minForm);
    ReviewLib::field($minForm, FormField::TYPE_QUESTION_GROUP, 'Dose', [
        'variable' => 'doses',
        'options' => ['loop' => ['source' => 'roster', 'max' => 1, 'min' => 2, 'items' => []]],
    ]);
    ReviewLib::field($minForm, FormField::TYPE_TEXT, 'Amount', ['variable' => 'amount']);
    ReviewLib::field($minForm, FormField::TYPE_GROUP_END, 'End dose');
    $minErrors = implode(' ', $loops->authoringErrors(ReviewLib::reload($minForm)));
    $check(str_contains($minErrors, 'cannot require more rows'), 'minimum cannot exceed maximum');
    $floor = ReviewLib::form($space, 'EV F5 roster floor', [
        'allow_anonymous' => 0,
        'allow_multiple' => 1,
        'identity_mode' => CustomForm::IDENTITY_IDENTIFIED,
    ]);
    $floor->setSetting('loops_enabled', '1');
    $floor->save(false);
    ReviewLib::clearFields($floor);
    ReviewLib::field($floor, FormField::TYPE_QUESTION_GROUP, 'Kept', [
        'variable' => 'kept_rows',
        'options' => ['loop' => ['source' => 'roster', 'max' => 2, 'min' => 1, 'items' => []]],
    ]);
    ReviewLib::field($floor, FormField::TYPE_TEXT, 'Note', ['variable' => 'kept_note']);
    ReviewLib::field($floor, FormField::TYPE_GROUP_END, 'End kept');
    $floor = ReviewLib::publishOpen($floor);
    $floorGroup = null;
    foreach ($floor->fields as $field) {
        if ($field->type === FormField::TYPE_QUESTION_GROUP) {
            $floorGroup = $field;
        }
    }
    $floorAnswer = ReviewLib::submit($floor, [], true);
    $floorKeys = $floorAnswer && $floorGroup ? $loops->rosterInstanceKeys($floorAnswer, $floorGroup) : [];
    if ($floorAnswer && $floorGroup && $floorKeys === []) {
        $created = $loops->addRow($floorAnswer, $floorGroup);
        $floorKeys = $created ? [$created] : [];
    }
    $check(count($floorKeys) === 1 && $loops->hideRow($floorAnswer, $floorGroup, '', $floorKeys[0]) === false, 'a roster cannot go below its minimum');
    if ($rosterAnswer) {
        $rosterDown = new m261001_130000_roster_json();
        $rosterDown->init();
        $check($rosterDown->safeDown() === false, 'roster migration down refuses while a row exists');
    }

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

    if ($nestedAnswer) {
        $long = new FormAnswerField();
        $long->answer_id = (int)$nestedAnswer->id;
        $long->field_id = (int)$symptom->id;
        $long->instance_key = str_repeat('n', 70);
        $long->value = 'kept';
        $long->save(false);
        $widen = new m261001_120000_widen_instance_key();
        $widen->init();
        $check($widen->safeDown() === false, 'widen down refuses a long nested key');
        $long->delete();
    }

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
