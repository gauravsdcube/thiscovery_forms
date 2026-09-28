<?php
/**
 * NEW-2. A complete answer missing one required on-route question is counted.
 * A fully answered one is not. Answers on soft-deleted questions are ignored.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\commands\DetectPartialCompletesController;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryForms\models\FormField;
use yii\db\Query;

$failures = [];
ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV R2 partial complete', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
(new Query())->createCommand()->delete('custom_form_answer_field', [
    'answer_id' => (new Query())->select('id')->from('custom_form_answer')->where(['form_id' => (int)$form->id]),
])->execute();
(new Query())->createCommand()->delete('custom_form_answer', ['form_id' => (int)$form->id])->execute();
ReviewLib::clearFields($form);
$first = ReviewLib::field($form, FormField::TYPE_TEXT, 'First', ['variable' => 'r2_first', 'required' => 1, 'sort_order' => 1]);
$second = ReviewLib::field($form, FormField::TYPE_TEXT, 'Second', ['variable' => 'r2_second', 'required' => 1, 'sort_order' => 2]);
$old = ReviewLib::field($form, FormField::TYPE_TEXT, 'Old', ['variable' => 'r2_old', 'required' => 1, 'sort_order' => 3]);
$form = ReviewLib::publishOpen($form);
$old->softDelete();

$partial = new FormAnswer();
$partial->form_id = (int)$form->id;
$partial->status = FormAnswer::STATUS_COMPLETE;
$partial->is_test = 0;
$partial->submitted_at = date('Y-m-d H:i:s');
$partial->save(false);
$cell = new FormAnswerField();
$cell->answer_id = (int)$partial->id;
$cell->field_id = (int)$first->id;
$cell->value = 'only-one';
$cell->save(false);
$gone = new FormAnswerField();
$gone->answer_id = (int)$partial->id;
$gone->field_id = (int)$old->id;
$gone->value = 'deleted-question';
$gone->save(false);

$complete = new FormAnswer();
$complete->form_id = (int)$form->id;
$complete->status = FormAnswer::STATUS_COMPLETE;
$complete->is_test = 0;
$complete->submitted_at = date('Y-m-d H:i:s');
$complete->save(false);
foreach ([$first, $second] as $field) {
    $row = new FormAnswerField();
    $row->answer_id = (int)$complete->id;
    $row->field_id = (int)$field->id;
    $row->value = 'answered';
    $row->save(false);
}

$detector = new DetectPartialCompletesController('detect-partial-completes', Yii::$app->getModule('thiscovery-forms'));
$partialMissing = $detector->missingRequired($partial);
$completeMissing = $detector->missingRequired($complete);
if ($partialMissing !== 1) {
    $failures[] = 'partial missing=' . $partialMissing;
}
if ($completeMissing !== 0) {
    $failures[] = 'complete missing=' . $completeMissing;
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
