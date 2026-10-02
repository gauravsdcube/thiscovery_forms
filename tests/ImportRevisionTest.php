<?php
/**
 * DAT-19. An import records a revision, so "publish latest revision" publishes the imported
 * questions, not the definition from before the import.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\FormVersionAdapter;
use humhub\modules\thiscoveryForms\services\FormVersionService;
use humhub\modules\thiscoveryForms\services\QuestionImportExportService;

$failures = [];
if (!FormVersionService::isAvailable()) {
    echo "SKIP versioning is not enabled\n";
    exit(0);
}
ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV DAT19 import revision', ['allow_anonymous' => 1]);
ReviewLib::clearFields($form);
ReviewLib::field($form, FormField::TYPE_TEXT, 'Before import', ['variable' => 'd19_before']);
$form = ReviewLib::publishOpen($form);

$versions = new FormVersionService();
$revisions = static fn() => (int)\humhub\modules\thiscoveryVersioning\models\VersionRevision::find()
    ->where(['owner_type' => FormVersionAdapter::OWNER_TYPE, 'owner_id' => (int)$form->id])->count();
$before = $revisions();
$error = (new QuestionImportExportService())->appendFieldPayloads(ReviewLib::reload($form), [['type' => 'text', 'label' => 'Imported question', 'variable' => 'd19_imported']]);
if ($error !== null) {
    $failures[] = 'import failed: ' . $error;
}
if ($revisions() <= $before) {
    $failures[] = 'the import did not record a revision';
}
$versions->publish(ReviewLib::reload($form));
$edition = $versions->editionFields(ReviewLib::reload($form), (int)ReviewLib::reload($form)->current_edition_id) ?? [];
if (!in_array('d19_imported', array_map(static fn($f) => (string)$f->variable, $edition), true)) {
    $failures[] = 'publishing the latest revision did not include the imported question';
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
