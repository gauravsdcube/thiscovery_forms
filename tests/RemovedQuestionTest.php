<?php
/**
 * NEW-11. A removed question with no answers is deleted. One that has answers
 * stays removed, keeps its original removal time, and is labelled as removed.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\ExportService;

$failures = [];
ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV R11 removed questions', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
ReviewLib::clearFields($form);
$live = ReviewLib::field($form, FormField::TYPE_TEXT, 'Live', [
    'variable' => 'r11_live',
    'sort_order' => 2,
]);
$empty = ReviewLib::field($form, FormField::TYPE_TEXT, 'Empty', [
    'variable' => 'r11_empty',
    'sort_order' => 1,
]);
$kept = ReviewLib::field($form, FormField::TYPE_TEXT, 'Kept', [
    'variable' => 'r11_kept',
    'sort_order' => 0,
]);
$form = ReviewLib::publishOpen($form);
$answer = new FormAnswer();
$answer->form_id = (int)$form->id;
$answer->status = FormAnswer::STATUS_COMPLETE;
$answer->is_test = 1;
$answer->submitted_at = date('Y-m-d H:i:s');
$answer->save(false);
Yii::$app->db->createCommand()->insert('custom_form_answer_field', [
    'answer_id' => (int)$answer->id,
    'field_id' => (int)$kept->id,
    'value' => 'stored',
])->execute();
$kept->updateAttributes(['deleted_at' => '2020-01-01 00:00:00']);
$kept->refresh();
if (!$kept->softDelete()) {
    $failures[] = 'softDelete returned false';
}
$kept->refresh();
if ((string)$kept->deleted_at !== '2020-01-01 00:00:00') {
    $failures[] = 'deleted_at was restamped to ' . $kept->deleted_at;
}

$form = ReviewLib::reload($form);
$saved = $form->saveFieldsFromPost([
    (int)$live->id => [
        'id' => (int)$live->id,
        'type' => FormField::TYPE_TEXT,
        'label' => 'Live',
        'variable' => 'r11_live',
        'sort_order' => 1,
    ],
]);
if (!$saved) {
    $failures[] = 'save failed';
}
if (FormField::findOne((int)$empty->id)) {
    $failures[] = 'answer-less question was kept';
}
$kept->refresh();
if (!$kept->isRemoved()) {
    $failures[] = 'answered question was not kept as removed';
}

$ids = array_map(static fn($f) => (int)$f->id, ReviewLib::reload($form)->getAllFields()->all());
$livePos = array_search((int)$live->id, $ids, true);
$keptPos = array_search((int)$kept->id, $ids, true);
if ($livePos === false || $keptPos === false || $livePos > $keptPos) {
    $failures[] = 'removed question was not sorted last ' . json_encode($ids);
}
if (!str_contains($kept->displayLabel(), '(removed)')) {
    $failures[] = 'label ' . $kept->displayLabel();
}

$csv = (new ExportService())->toCsv(ReviewLib::reload($form), ['header_mode' => ExportService::HEADER_LABEL]);
if (!str_contains($csv, 'Kept (removed)')) {
    $failures[] = 'export header did not mark the removed question';
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
