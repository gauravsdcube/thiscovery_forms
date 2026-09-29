<?php
/**
 * HB-1. A panel, folder, email template, or library item from another
 * container is rejected. settings_json is not mass-assignable.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\space\models\Space;
use humhub\modules\thiscoveryForms\models\FormEmailTemplate;
use humhub\modules\thiscoveryForms\models\FormFolder;
use humhub\modules\thiscoveryForms\models\FormLibraryItem;
use humhub\modules\thiscoveryForms\models\FormPanel;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();
$other = Space::find()->andWhere(['not', ['id' => (int)$space->id]])->one();
if (!$other) {
    echo "FAIL no second space\n";
    exit(1);
}

$form = ReviewLib::form($space, 'EV HB1 container', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
$form = ReviewLib::publishOpen($form);

$before = (string)$form->settings_json;
$form->load(['CustomForm' => ['settings_json' => '{"panel_id":999999}']]);
$check($form->settings_json === $before, 'posted settings_json was applied');

$panel = new FormPanel();
$panel->title = 'HB1 other panel';
$panel->contentcontainer_id = (int)$other->contentcontainer_id;
$panel->save(false);

$form->enrol_panel_id = (int)$panel->id;
$check(!$form->validate(['enrol_panel_id', 'title']), 'foreign panel validated');
$form->save(false);
$form->refresh();
$check((int)$form->getSetting('enrol_panel_id', 0) !== (int)$panel->id, 'foreign panel was stored');

$folder = new FormFolder();
$folder->name = 'HB1 other folder';
$folder->contentcontainer_id = (int)$other->contentcontainer_id;
$folder->save(false);
$form->folder_id = (int)$folder->id;
$check(!$form->validate(['folder_id', 'title']), 'foreign folder validated');

$template = new FormEmailTemplate();
$template->title = 'HB1 other template';
$template->subject = 'Hello';
$template->contentcontainer_id = (int)$other->contentcontainer_id;
$template->save(false);
$form->invite_email_template_id = (int)$template->id;
$check(!$form->validate(['invite_email_template_id', 'title']), 'foreign template validated');
$form->save(false);
$form->refresh();
$check((int)$form->getSetting('invite_email_template_id', 0) !== (int)$template->id, 'foreign template was stored');

$item = new FormLibraryItem();
$item->type = FormLibraryItem::TYPE_QUESTION;
$item->title = 'HB1 other library';
$item->contentcontainer_id = (int)$other->contentcontainer_id;
$item->setPayload(['fields' => []]);
$item->save(false);
$check(!$item->isAvailableIn((int)$space->contentcontainer_id), 'foreign library item is available');
$check($item->isAvailableIn((int)$other->contentcontainer_id), 'own library item is hidden');

$panel->delete();
$folder->delete();
$template->delete();
$item->delete();

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
