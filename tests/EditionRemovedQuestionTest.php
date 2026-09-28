<?php
/**
 * NEW-1. An answer filled against a published edition keeps questions that
 * were removed from the draft afterwards. A fill of the live draft does not.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\thiscoveryForms\services\FormVersionService;
use yii\db\Query;

$failures = [];
ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();
$form = ReviewLib::form($space, 'EV R1 edition answer', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
(new Query())->createCommand()->delete('custom_form_answer_field', [
    'answer_id' => (new Query())->select('id')->from('custom_form_answer')->where(['form_id' => (int)$form->id]),
])->execute();
(new Query())->createCommand()->delete('custom_form_answer', ['form_id' => (int)$form->id])->execute();
ReviewLib::clearFields($form);
$keep = ReviewLib::field($form, FormField::TYPE_TEXT, 'Keep', ['variable' => 'r1_keep', 'required' => 0, 'sort_order' => 1]);
$removed = ReviewLib::field($form, FormField::TYPE_TEXT, 'Removed', ['variable' => 'r1_removed', 'required' => 0, 'sort_order' => 2]);
$form = ReviewLib::publishOpen($form);
$removed->softDelete();

$edition = ReviewLib::reload($form);
(new FormVersionService())->applyFillDefinition($edition);
$hydrated = false;
foreach ($edition->fields as $field) {
    if ((int)$field->id === (int)$removed->id) {
        $hydrated = true;
    }
}
if (!$hydrated) {
    $failures[] = 'published fill did not include the removed question';
}

ReviewLib::asUser(review_user('review_respondent'));
$submit = new SubmitForm();
$submit->form = $edition;
$submit->values = [(int)$keep->id => 'kept', (int)$removed->id => 'still-there'];
$answer = $submit->save(null, true, false, false);
$stored = [];
if ($answer) {
    foreach (ReviewLib::answerRows((int)$answer->id) as $row) {
        $stored[(int)$row['field_id']] = $row['value'];
    }
}
if (($stored[(int)$removed->id] ?? null) !== 'still-there') {
    $failures[] = 'edition answer dropped the removed question: ' . json_encode($stored);
}
if (($stored[(int)$keep->id] ?? null) !== 'kept') {
    $failures[] = 'edition answer dropped the live question';
}

$draft = ReviewLib::reload($form);
$draftIds = [];
foreach ($draft->fields as $field) {
    $draftIds[] = (int)$field->id;
}
if (in_array((int)$removed->id, $draftIds, true)) {
    $failures[] = 'live draft still shows the removed question';
}
$draftSubmit = new SubmitForm();
$draftSubmit->form = $draft;
$draftSubmit->values = [(int)$keep->id => 'draft', (int)$removed->id => 'should-not-store'];
$draftAnswer = $draftSubmit->save(null, true, false, false);
$draftStored = [];
if ($draftAnswer) {
    foreach (ReviewLib::answerRows((int)$draftAnswer->id) as $row) {
        $draftStored[(int)$row['field_id']] = $row['value'];
    }
}
if (array_key_exists((int)$removed->id, $draftStored)) {
    $failures[] = 'draft fill stored the removed question';
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
