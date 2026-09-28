<?php
/**
 * NEW-19. A form that already has a "<" in its CSS can still be saved, and
 * restoring a snapshot strips that character.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\services\FormSnapshotService;

$failures = [];
ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV R19 css save', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
$form->updateAttributes(['custom_css' => '.x { content: "<"; }']);
$form = ReviewLib::reload($form);
$form->title = 'EV R19 css save';
if (!$form->save()) {
    $failures[] = 'existing CSS blocked an unrelated save: ' . json_encode($form->errors);
}

$form->custom_css = '.x { content: "<script>"; }';
if ($form->validate(['custom_css'])) {
    $failures[] = 'a new stylesheet containing < was accepted';
}

$snapshot = (new FormSnapshotService())->export(ReviewLib::reload($form));
$snapshot['meta']['custom_css'] = '.y::before { content: "<b>"; }';
$restored = ReviewLib::reload($form);
if (!(new FormSnapshotService())->import($restored, $snapshot)) {
    $failures[] = 'snapshot restore failed: ' . json_encode($restored->errors);
}
if (str_contains((string)$restored->custom_css, '<')) {
    $failures[] = 'restored CSS still contains <';
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
