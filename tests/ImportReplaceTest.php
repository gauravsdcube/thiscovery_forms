<?php
/**
 * Replacing a form from JSON updates the live question that already has each variable
 * name. It must not refuse the import because those questions are about to be removed.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\QuestionImportExportService;

$failures = [];
ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV replace import names', ['allow_anonymous' => 1]);
ReviewLib::clearFields($form);

$service = new QuestionImportExportService();
$payloads = [
    ['type' => FormField::TYPE_RICH_TEXT, 'label' => 'About this survey', 'variable' => 'intro_about', 'rich_content' => '<p>First</p>'],
    ['type' => FormField::TYPE_TEXT, 'label' => 'Kept aside', 'variable' => 'kept_aside'],
];
$error = $service->appendFieldPayloads(ReviewLib::reload($form), $payloads, true);
if ($error !== null) {
    $failures[] = 'first import failed: ' . $error;
}
$form = ReviewLib::reload($form);
$about = FormField::find()->where(['form_id' => (int)$form->id, 'variable' => 'intro_about', 'deleted_at' => null])->one();
if (!$about) {
    $failures[] = 'intro_about was not created';
}
$aboutId = $about ? (int)$about->id : 0;

$again = [
    ['type' => FormField::TYPE_RICH_TEXT, 'label' => 'About this survey', 'variable' => 'intro_about', 'rich_content' => '<p>Updated</p>'],
    ['type' => FormField::TYPE_TEXT, 'label' => 'Added later', 'variable' => 'added_later'],
];
$error = $service->appendFieldPayloads(ReviewLib::reload($form), $again, true);
if ($error !== null) {
    $failures[] = 'replace import failed: ' . $error;
}
$form = ReviewLib::reload($form);
$live = FormField::find()->where(['form_id' => (int)$form->id, 'deleted_at' => null])->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])->all();
$byVar = [];
foreach ($live as $field) {
    $byVar[(string)$field->variable] = $field;
}
if (!isset($byVar['intro_about']) || (int)$byVar['intro_about']->id !== $aboutId) {
    $failures[] = 'intro_about was not updated in place: ' . json_encode(array_keys($byVar));
} elseif (!str_contains((string)$byVar['intro_about']->getRichTextContent(), 'Updated')) {
    $failures[] = 'intro_about content was not replaced';
}
if (isset($byVar['kept_aside'])) {
    $failures[] = 'a question missing from the file was kept';
}
if (!isset($byVar['added_later'])) {
    $failures[] = 'a new question in the file was not added';
}
if (count($byVar) !== 2) {
    $failures[] = 'live questions after replace: ' . json_encode(array_keys($byVar));
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
