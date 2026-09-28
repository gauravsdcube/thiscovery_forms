<?php
/**
 * NEW-14. A stored file guid is accepted only when that file is attached
 * to this answer. Grants are dropped after attach and capped at 50.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\file\models\File;
use humhub\modules\thiscoveryForms\commands\DetectUnattachedFilesController;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\thiscoveryForms\services\UploadGrant;

$failures = [];
ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV R14 file grant', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
    'allow_edit' => 0,
]);
ReviewLib::clearFields($form);
$field = ReviewLib::field($form, FormField::TYPE_FILE, 'Upload', ['variable' => 'r14_file']);
$form = ReviewLib::publishOpen($form);

$loose = new File();
$loose->file_name = 'r14-loose.txt';
$loose->title = 'r14-loose.txt';
$loose->size = 4;
$loose->mime_type = 'text/plain';
$loose->save(false);

$answer = new FormAnswer();
$answer->form_id = (int)$form->id;
$answer->status = FormAnswer::STATUS_IN_PROGRESS;
$answer->save(false);
$row = new FormAnswerField();
$row->answer_id = (int)$answer->id;
$row->field_id = (int)$field->id;
$row->value = (string)$loose->guid;
$row->save(false);

$submit = new SubmitForm(['form' => $form]);
$submit->editingAnswer = $answer;
$submit->loadValuesFromRequest(['values' => [(string)$field->id => (string)$loose->guid]]);
if (($submit->values[(int)$field->id] ?? '') !== '') {
    $failures[] = 'stored guid was accepted without an attachment';
}

$answer->fileManager->attach($loose->guid);
$submit->loadValuesFromRequest(['values' => [(string)$field->id => (string)$loose->guid]]);
if (($submit->values[(int)$field->id] ?? '') !== (string)$loose->guid) {
    $failures[] = 'attached stored guid was rejected';
}

UploadGrant::remember((int)$form->id, (string)$loose->guid);
UploadGrant::forget((int)$form->id, (string)$loose->guid);
if (UploadGrant::granted((int)$form->id, (string)$loose->guid)) {
    $failures[] = 'grant survived forget';
}

$answer->status = FormAnswer::STATUS_COMPLETE;
$answer->save(false);
$loose->refresh();
if (UploadGrant::mayRemove($form, $loose, $answer, false)) {
    $failures[] = 'completed answer file was removable';
}
if (!UploadGrant::mayRemove($form, $loose, $answer, true)) {
    $failures[] = 'editing a completed answer could not remove its file';
}

UploadGrant::forgetAll((int)$form->id);
for ($i = 0; $i < 51; $i++) {
    UploadGrant::remember((int)$form->id, 'r14-grant-' . $i);
}
if (UploadGrant::granted((int)$form->id, 'r14-grant-0')) {
    $failures[] = 'grant list grew past 50';
}
if (!UploadGrant::granted((int)$form->id, 'r14-grant-50')) {
    $failures[] = 'newest grant was dropped';
}

$loose->refresh();
$loose->object_id = 0;
$loose->object_model = '';
$loose->save(false);
$found = false;
foreach ((new DetectUnattachedFilesController('detect-unattached-files', Yii::$app->getModule('thiscovery-forms')))->unattachedFileAnswers() as $hit) {
    if ($hit['answer_id'] === (int)$answer->id && $hit['field_id'] === (int)$field->id) {
        $found = true;
    }
}
if (!$found) {
    $failures[] = 'repair query missed an unattached file answer';
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
