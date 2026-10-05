<?php
/**
 * NEW-10 / DAT-10. Only one live question may use a variable name. Deleting a question
 * frees that name. Export does not collapse a removed question and a later question that
 * reuse the name into one column.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\commands\DetectDuplicateVariablesController;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\ExportService;

$failures = [];
ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();
$form = ReviewLib::form($space, 'EV R10 duplicate age', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
ReviewLib::clearFields($form);
$removed = ReviewLib::field($form, FormField::TYPE_NUMBER, 'Age', [
    'variable' => 'age',
    'required' => 0,
    'sort_order' => 1,
]);
$second = null;
try {
    $second = ReviewLib::field($form, FormField::TYPE_NUMBER, 'Age twin', ['variable' => 'age', 'sort_order' => 3]);
} catch (\Throwable $e) {
    $second = null;
}
if ($second !== null) {
    $failures[] = 'the database accepted two live questions named age';
    $second->delete();
}
$removed->softDelete();
$live = ReviewLib::field($form, FormField::TYPE_NUMBER, 'Age', [
    'variable' => 'age',
    'required' => 0,
    'sort_order' => 2,
]);
$form = ReviewLib::publishOpen($form);
$answer = new FormAnswer();
$answer->form_id = (int)$form->id;
$answer->status = FormAnswer::STATUS_COMPLETE;
$answer->is_test = 0;
$answer->submitted_at = date('Y-m-d H:i:s');
$answer->save(false);
$db = Yii::$app->db;
$db->createCommand()->insert('custom_form_answer_field', [
    'answer_id' => (int)$answer->id,
    'field_id' => (int)$removed->id,
    'value' => '99',
])->execute();
$db->createCommand()->insert('custom_form_answer_field', [
    'answer_id' => (int)$answer->id,
    'field_id' => (int)$live->id,
    'value' => '30',
])->execute();

$csv = (new ExportService())->toCsv(ReviewLib::reload($form), ['header_mode' => ExportService::HEADER_VARIABLE]);
$rows = array_map('str_getcsv', preg_split('/\r\n|\r|\n/', trim($csv)) ?: []);
$header = $rows[0] ?? [];
$data = $rows[1] ?? [];
$ageCols = [];
foreach ($header as $i => $name) {
    if (strcasecmp((string)$name, 'age') === 0 || str_starts_with(strtolower((string)$name), 'age ')) {
        $ageCols[$name] = $data[$i] ?? '';
    }
}
$values = array_values($ageCols);
sort($values);
if ($values !== ['30', '99']) {
    $failures[] = 'export columns ' . json_encode($ageCols);
}
if (count($ageCols) < 2 || count(array_unique(array_map('strtolower', array_keys($ageCols)))) < 2) {
    $failures[] = 'duplicate variable headers were not suffixed ' . json_encode(array_keys($ageCols));
}

$detector = new DetectDuplicateVariablesController('detect-duplicate-variables', Yii::$app->getModule('thiscovery-forms'));
$dupes = $detector->duplicateForms();
$found = false;
foreach ($dupes as $row) {
    if ((int)$row['form_id'] === (int)$form->id && $row['variable'] === 'age') {
        $found = true;
    }
}
if (!$found) {
    $failures[] = 'detector missed this form';
}

$form = ReviewLib::reload($form);
$saved = $form->saveFieldsFromPost([
    (int)$live->id => [
        'id' => (int)$live->id,
        'type' => FormField::TYPE_NUMBER,
        'label' => 'Age',
        'variable' => 'age',
        'sort_order' => 1,
    ],
    'new' => [
        'type' => FormField::TYPE_NUMBER,
        'label' => 'Age again',
        'variable' => 'age',
        'sort_order' => 2,
    ],
]);
if ($saved) {
    $failures[] = 'a chosen variable name already in use was saved (silently suffixed?)';
} elseif (!str_contains(implode(' ', $form->getErrors('title')), '“age”')) {
    $failures[] = 'the refusal does not name the variable: ' . json_encode($form->getErrors('title'));
}

// With no name chosen, the generated one steps past both the live and the removed "age".
$form = ReviewLib::reload($form);
$saved = $form->saveFieldsFromPost([
    (int)$live->id => ['id' => (int)$live->id, 'type' => FormField::TYPE_NUMBER, 'label' => 'Age', 'variable' => 'age', 'sort_order' => 1],
    'new' => ['type' => FormField::TYPE_NUMBER, 'label' => 'Age', 'variable' => '', 'sort_order' => 2],
]);
$fresh = FormField::find()->where(['form_id' => (int)$form->id, 'label' => 'Age'])->andWhere(['<>', 'id', (int)$live->id])->andWhere(['deleted_at' => null])->one();
if (!$saved || !$fresh || strtolower((string)$fresh->variable) === 'age') {
    $failures[] = 'a generated name reused "age": ' . ($fresh->variable ?? 'missing');
}

// Deleting the live question frees "age" for a new question in the same save.
$form = ReviewLib::reload($form);
$form->clearErrors();
$saved = $form->saveFieldsFromPost([
    'reuse' => [
        'type' => FormField::TYPE_NUMBER,
        'label' => 'Age reused',
        'variable' => 'age',
        'sort_order' => 1,
    ],
]);
$reused = FormField::find()->where(['form_id' => (int)$form->id, 'deleted_at' => null, 'variable' => 'age'])->one();
if (!$saved || !$reused || (int)$reused->id === (int)$live->id) {
    $failures[] = 'deleting a question did not free its variable: ' . json_encode($form->getErrors());
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
