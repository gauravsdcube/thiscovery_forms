<?php
/**
 * HB-6. Posted values for frozen, hidden, and panel-attribute fields
 * are replaced with the server's own values.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormPanel;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\thiscoveryForms\services\PanelFieldService;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();
$form = ReviewLib::form($space, 'EV HB6 server fields', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
ReviewLib::clearFields($form);
$hidden = ReviewLib::field($form, FormField::TYPE_TEXT, 'Hidden', ['variable' => 'hid']);
$hidden->setHiddenFromRespondent(true);
$hidden->setDefaultValue('server-default');
$hidden->save(false);
$open = ReviewLib::field($form, FormField::TYPE_TEXT, 'Open', ['variable' => 'open']);
$frozen = ReviewLib::field($form, FormField::TYPE_TEXT, 'Frozen', ['variable' => 'frozen']);
$panelField = ReviewLib::field($form, FormField::TYPE_PANEL_ATTR, 'First', ['variable' => 'first']);
$panelField->setPanelAttrKey(PanelFieldService::KEY_FIRST);
$panelField->save(false);

$panel = new FormPanel();
$panel->title = 'HB6 panel';
$panel->contentcontainer_id = (int)$space->contentcontainer_id;
$panel->save(false);
$member = new FormPanelMember();
$member->panel_id = (int)$panel->id;
$member->first_name = 'Ada';
$member->email = 'ada@example.test';
$member->token = 'hb6token';
$member->status = FormPanelMember::STATUS_ACTIVE;
$member->save(false);

$form = ReviewLib::reload($form);
$submit = new SubmitForm(['form' => $form]);
$submit->panelMemberId = (int)$member->id;
$submit->frozenFieldIds = [(int)$frozen->id];
$submit->previousValues = [(int)$frozen->id => 'kept'];
$submit->loadValuesFromRequest([
    'values' => [
        (string)$hidden->id => 'hacked-hidden',
        (string)$open->id => 'typed',
        (string)$frozen->id => 'hacked-frozen',
        (string)$panelField->id => 'Mallory',
    ],
]);
$submit->applyServerOwnedValues();

$check((string)($submit->values[$hidden->id] ?? '') === 'server-default', 'hidden field kept the posted value');
$check((string)($submit->values[$open->id] ?? '') === 'typed', 'open field was overwritten');
$check((string)($submit->values[$frozen->id] ?? '') === 'kept', 'frozen field kept the posted value');
$check((string)($submit->values[$panelField->id] ?? '') === 'Ada', 'panel field kept the posted value');

$member->delete();
$panel->delete();

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
