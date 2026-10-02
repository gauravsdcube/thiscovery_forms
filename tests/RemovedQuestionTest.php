<?php
/**
 * NEW-11 / V3-36. On a form that was never published, a removed question with no answers is
 * deleted. Once an edition is published, a removed question is kept as removed (the edition may
 * still show it), keeps its original removal time, and is labelled as removed.
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
$emptyAfter = FormField::findOne((int)$empty->id);
if (!$emptyAfter || !$emptyAfter->isRemoved()) {
    $failures[] = 'a question of a published form was hard-deleted (V3-36)';
}

// A form that has never been published deletes an answer-less question outright.
$draft = ReviewLib::form(review_space(), 'EV R11 draft only', ['allow_anonymous' => 1]);
ReviewLib::clearFields($draft);
$draftKeep = ReviewLib::field($draft, FormField::TYPE_TEXT, 'Keep', ['variable' => 'r11d_keep', 'sort_order' => 0]);
$draftGone = ReviewLib::field($draft, FormField::TYPE_TEXT, 'Gone', ['variable' => 'r11d_gone', 'sort_order' => 1]);
$draft = ReviewLib::reload($draft);
$draft->current_edition_id = null;
$draft->saveFieldsFromPost([
    (int)$draftKeep->id => ['id' => (int)$draftKeep->id, 'type' => FormField::TYPE_TEXT, 'label' => 'Keep', 'variable' => 'r11d_keep', 'sort_order' => 0],
]);
if (FormField::findOne((int)$draftGone->id)) {
    $failures[] = 'an answer-less question of a never-published form was kept';
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
