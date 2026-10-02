<?php
/**
 * NEW-20 / V3-50. Only files attached to the response are granted again, and a
 * cleared file is deleted only after the answer save commits.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\file\models\File;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\SubmitForm;

$failures = [];
$admin = review_user('review_netadmin');
ReviewLib::asUser($admin);
$form = ReviewLib::form(review_space(), 'EV R20 legacy file', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
ReviewLib::clearFields($form);
$field = ReviewLib::field($form, FormField::TYPE_FILE, 'Upload', ['variable' => 'r20_file']);
$form = ReviewLib::publishOpen($form);

$loose = new File();
$loose->file_name = 'r20-loose.txt';
$loose->title = 'r20-loose.txt';
$loose->size = 4;
$loose->mime_type = 'text/plain';
$loose->created_by = (int)$admin->id;
$loose->object_model = '';
$loose->save(false);

$answer = new FormAnswer();
$answer->form_id = (int)$form->id;
$answer->status = FormAnswer::STATUS_IN_PROGRESS;
$answer->save(false);
$submit = new SubmitForm(['form' => $form]);
$submit->editingAnswer = $answer;
$submit->loadValuesFromRequest(['values' => [(string)$field->id => (string)$loose->guid]]);
// V3-50: an unattached HumHub file is no longer granted just because this user owns it.
if (($submit->values[(int)$field->id] ?? '') === (string)$loose->guid) {
    $failures[] = 'an unattached file from another module was accepted';
}

$kept = new File();
$kept->file_name = 'r20-kept.txt';
$kept->title = 'r20-kept.txt';
$kept->size = 4;
$kept->mime_type = 'text/plain';
$kept->save(false);
$answer->fileManager->attach($kept->guid);
$row = new FormAnswerField();
$row->answer_id = (int)$answer->id;
$row->field_id = (int)$field->id;
$row->value = (string)$kept->guid;
$row->save(false);
FormAnswerField::$deferFileDeletes = true;
$row->delete();
if (File::findOne(['guid' => (string)$kept->guid]) === null) {
    $failures[] = 'the file was deleted before commit';
}
FormAnswerField::discardDeferredFiles();
if (File::findOne(['guid' => (string)$kept->guid]) === null) {
    $failures[] = 'a rolled-back save deleted the file';
}

$gone = new File();
$gone->file_name = 'r20-gone.txt';
$gone->title = 'r20-gone.txt';
$gone->size = 4;
$gone->mime_type = 'text/plain';
$gone->save(false);
$answer->fileManager->attach($gone->guid);
$goneRow = new FormAnswerField();
$goneRow->answer_id = (int)$answer->id;
$goneRow->field_id = (int)$field->id;
$goneRow->value = (string)$gone->guid;
$goneRow->save(false);
FormAnswerField::$deferFileDeletes = true;
$goneRow->delete();
FormAnswerField::deleteDeferredFiles();
if (File::findOne(['guid' => (string)$gone->guid]) !== null) {
    $failures[] = 'the cleared file was kept after commit';
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
