<?php
/**
 * HF-7. Deleting a file answer must not delete a file that belongs to someone else.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\file\models\File;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\thiscoveryForms\services\UploadGrant;
use yii\db\Query;

$failures = [];
$admin = review_user('review_netadmin');
ReviewLib::asUser($admin);
$form = ReviewLib::form(review_space(), 'EV HF7 file ownership', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
(new Query())->createCommand()->delete('custom_form_answer_field', ['answer_id' => (new Query())->select('id')->from('custom_form_answer')->where(['form_id' => (int)$form->id])])->execute();
(new Query())->createCommand()->delete('custom_form_answer', ['form_id' => (int)$form->id])->execute();
ReviewLib::clearFields($form);
$field = ReviewLib::field($form, FormField::TYPE_FILE, 'Upload', ['variable' => 'up']);
$form = ReviewLib::publishOpen($form);

$victim = new File();
$victim->file_name = 'hf7-victim.txt';
$victim->title = 'hf7-victim.txt';
$victim->size = 4;
$victim->mime_type = 'text/plain';
$victim->save(false);
$victimGuid = (string)$victim->guid;

$answer = new FormAnswer();
$answer->form_id = (int)$form->id;
$answer->status = FormAnswer::STATUS_IN_PROGRESS;
$answer->save(false);
$row = new FormAnswerField();
$row->answer_id = (int)$answer->id;
$row->field_id = (int)$field->id;
$row->value = $victimGuid;
$row->save(false);
$row->delete();
if (File::findOne(['guid' => $victimGuid]) === null) {
    $failures[] = 'deleting the answer field removed a file that was not attached to the answer';
}

$own = new File();
$own->file_name = 'hf7-own.txt';
$own->title = 'hf7-own.txt';
$own->size = 4;
$own->mime_type = 'text/plain';
$own->save(false);
$answer->fileManager->attach($own->guid);
$ownRow = new FormAnswerField();
$ownRow->answer_id = (int)$answer->id;
$ownRow->field_id = (int)$field->id;
$ownRow->value = (string)$own->guid;
$ownRow->save(false);
$ownGuid = (string)$own->guid;
$ownRow->delete();
if (File::findOne(['guid' => $ownGuid]) !== null) {
    $failures[] = 'deleting the answer field left a file that belongs to the answer';
}

UploadGrant::remember((int)$form->id, $ownGuid);
$submit = new SubmitForm(['form' => ReviewLib::reload($form)]);
$submit->editingAnswer = $answer;
$submit->loadValuesFromRequest(['values' => [(string)$field->id => $victimGuid]]);
if (($submit->values[(int)$field->id] ?? '') !== '') {
    $failures[] = 'a foreign file guid was accepted';
}
$submit->loadValuesFromRequest(['values' => [(string)$field->id => $ownGuid]]);
if (($submit->values[(int)$field->id] ?? '') !== $ownGuid) {
    $failures[] = 'a file uploaded in this session was rejected';
}

if (!UploadGrant::mayRemove($form, $victim, $answer)) {
    // expected: victim is not removable
} else {
    $failures[] = 'delete-file would remove the victim file';
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS victim kept\n";
