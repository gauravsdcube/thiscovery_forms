<?php
/**
 * HF-2. Deleting a question must not destroy stored answers.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\ExportService;
use humhub\modules\thiscoveryForms\services\FormSnapshotService;
use yii\db\Query;

$failures = [];
$admin = review_user('review_netadmin');
ReviewLib::asUser($admin);
$space = review_space();
$form = ReviewLib::form($space, 'EV HF2 soft delete', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
(new yii\db\Query())->createCommand()->delete('custom_form_answer_field', ['answer_id' => (new yii\db\Query())->select('id')->from('custom_form_answer')->where(['form_id' => (int)$form->id])])->execute();
(new yii\db\Query())->createCommand()->delete('custom_form_answer', ['form_id' => (int)$form->id])->execute();
ReviewLib::clearFields($form);
$keep = ReviewLib::field($form, FormField::TYPE_TEXT, 'Keep', ['variable' => 'keep', 'required' => 0, 'sort_order' => 1]);
$drop = ReviewLib::field($form, FormField::TYPE_TEXT, 'Drop', ['variable' => 'drop', 'required' => 0, 'sort_order' => 2]);
$form = ReviewLib::publishOpen($form);
$dropId = (int)$drop->id;

ReviewLib::asUser(null);
$answer = ReviewLib::submit($form, [(int)$keep->id => 'stay', $dropId => 'kept-answer']);
if (!$answer) {
    fwrite(STDOUT, "FAIL submit\n");
    exit(1);
}

ReviewLib::asUser($admin);
$snapshot = (new FormSnapshotService())->export($form);
$keepRow = $keep->toPostRow();
$keepRow['id'] = (int)$keep->id;
$form->saveFieldsFromPost(['k' => $keepRow]);

$still = (new Query())->from('custom_form_answer_field')->where(['answer_id' => (int)$answer->id, 'field_id' => $dropId])->one();
if (!$still || (string)$still['value'] !== 'kept-answer') {
    $failures[] = 'answer cell missing after question delete';
}
$live = FormField::find()->where(['id' => $dropId, 'deleted_at' => null])->exists();
if ($live) {
    $failures[] = 'removed question is still live';
}
$csv = (new ExportService())->toCsv($form);
if (!str_contains($csv, 'kept-answer')) {
    $failures[] = 'export omitted the deleted question';
}

$restored = (new FormSnapshotService())->import($form, $snapshot);
$dropAgain = FormField::findOne($dropId);
if (!$restored || !$dropAgain || $dropAgain->deleted_at !== null) {
    $failures[] = 'restore did not undelete the same question id';
}
$reattached = (new Query())->from('custom_form_answer_field')->where(['answer_id' => (int)$answer->id, 'field_id' => $dropId])->exists();
if (!$reattached) {
    $failures[] = 'answers did not reattach';
}

$form->hardDelete();
$orphanFields = (new Query())->from('custom_form_field')->where(['form_id' => (int)$form->id])->count();
$orphanAnswers = (new Query())->from('custom_form_answer')->where(['form_id' => (int)$form->id])->count();
if ($orphanFields || $orphanAnswers) {
    $failures[] = 'form delete left orphans fields=' . $orphanFields . ' answers=' . $orphanAnswers;
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
