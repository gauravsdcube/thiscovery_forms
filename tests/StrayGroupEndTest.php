<?php
/**
 * DAT-18. A group end with no open group is dropped at save instead of closing an outer group
 * early; a group with its own end keeps it.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;

$failures = [];
ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV DAT18 stray group end', ['allow_anonymous' => 1]);
ReviewLib::clearFields($form);
$saved = $form->saveFieldsFromPost([
    'a' => ['type' => FormField::TYPE_TEXT, 'label' => 'Before', 'variable' => 'd18_before', 'sort_order' => 1],
    'stray' => ['type' => FormField::TYPE_GROUP_END, 'label' => '', 'sort_order' => 2],
    'g' => ['type' => FormField::TYPE_QUESTION_GROUP, 'label' => 'Group', 'variable' => 'd18_group', 'sort_order' => 3],
    'in' => ['type' => FormField::TYPE_TEXT, 'label' => 'Inside', 'variable' => 'd18_inside', 'sort_order' => 4],
    'end' => ['type' => FormField::TYPE_GROUP_END, 'label' => '', 'sort_order' => 5],
]);
if (!$saved) {
    $failures[] = 'save failed: ' . json_encode($form->getErrors());
}
$types = array_map(static fn(FormField $f) => $f->type, ReviewLib::reload($form)->getFields()->all());
if ($types !== [FormField::TYPE_TEXT, FormField::TYPE_QUESTION_GROUP, FormField::TYPE_TEXT, FormField::TYPE_GROUP_END]) {
    $failures[] = 'the stray group end was kept, or the real one lost: ' . json_encode($types);
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
