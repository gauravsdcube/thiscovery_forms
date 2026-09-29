<?php
/**
 * F2. Consent is a versioned record. Refusal is not a complete.
 * A fully anonymous record is not linked to the answer.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormPanel;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\services\ConsentService;
use humhub\modules\thiscoveryForms\services\TranslationImportExportService;
use yii\db\Query;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

$svc = new ConsentService();
$items = [
    ['code' => 'take_part', 'label' => 'I agree to take part', 'required' => 1, 'input' => 'yes_no'],
    ['code' => 'linkage', 'label' => 'You may link my data', 'required' => 0, 'input' => 'checkbox'],
];
$hash = $svc->contentHash('<p>Sheet</p>', $items);
$check($hash === $svc->contentHash('<p>Sheet</p>', $items), 'the content hash was not stable');
$check(strlen($hash) === 64, 'the content hash was not sha-256');

$module = Yii::$app->getModule('thiscovery-forms');
$module->settings->set(Module::SETTING_ECONSENT, '0');
ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();

$off = ReviewLib::form($space, 'EV F2 flag off', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
$panel = FormPanel::findOne(['title' => 'EV F2 panel']) ?: new FormPanel();
$panel->title = 'EV F2 panel';
$panel->contentcontainer_id = (int)$space->contentcontainer_id;
$panel->save(false);
$member = FormPanelMember::findOne(['token' => 'f2member']) ?: new FormPanelMember();
$member->panel_id = (int)$panel->id;
$member->first_name = 'Ada';
$member->email = 'ada-f2@example.test';
$member->token = 'f2member';
$member->status = FormPanelMember::STATUS_ACTIVE;
$member->consent_at = '2020-01-01 00:00:00';
$member->save(false);
$before = (string)$member->consent_at;
ReviewLib::clearFields($off);
ReviewLib::field($off, FormField::TYPE_TEXT, 'Name', ['variable' => 'f2_name']);
$off = ReviewLib::publishOpen($off);
$savedOff = ReviewLib::submit($off, []);
$member->refresh();
$check($savedOff instanceof FormAnswer, 'flag-off submit did not save');
$check((string)$member->consent_at === $before, 'flag-off completion changed consent_at');
$member->markConsent();
$member->refresh();
$check((string)$member->consent_at === $before, 'markConsent without a record changed consent_at');

$module->settings->set(Module::SETTING_ECONSENT, '1');
$form = ReviewLib::form($space, 'EV F2 consent', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
$form->setSetting('econsent_enabled', '1');
$form->setSetting('panel_id', (int)$panel->id);
$form->setSetting('reconsent', 'next_visit');
$form->setSetting('not_consented_message', 'You have not consented.');
$form->save(false);
(new Query())->createCommand()->delete('custom_form_answer_field', [
    'answer_id' => (new Query())->select('id')->from('custom_form_answer')->where(['form_id' => (int)$form->id]),
])->execute();
(new Query())->createCommand()->delete('custom_form_consent_record', ['form_id' => (int)$form->id])->execute();
(new Query())->createCommand()->delete('custom_form_answer', ['form_id' => (int)$form->id])->execute();
ReviewLib::clearFields($form);
$draft = $svc->saveDraft($form, null, [
    'title' => 'Taking part',
    'body_html' => '<p>Sheet</p><script>alert(1)</script>',
    'approval_reference' => 'REC 26/EE/0000',
    'items' => $items,
]);
$check($draft && !str_contains((string)$draft['body_html'], '<script'), 'the information sheet kept a script');
$errors = $svc->publish($form, (int)$draft['id']);
$check($errors === [], 'publish failed: ' . implode(' ', $errors));
$field = ReviewLib::field($form, FormField::TYPE_CONSENT, 'Consent', [
    'variable' => 'f2_consent',
    'options' => json_encode(['document_id' => (int)$draft['id'], 'signature' => 'typed']),
]);
$form = ReviewLib::publishOpen($form);

$post = static function (array $itemsPosted, string $name = 'Ada Lovelace') use ($field): void {
    Yii::$app->request->setBodyParams([
        'consent' => [
            (int)$field->id => [
                'items' => $itemsPosted,
                'signature_method' => 'typed',
                'signature_name' => $name,
                'scrolled_to_end' => '1',
            ],
        ],
    ]);
};
$submitAs = static function (CustomForm $form, int $memberId) use ($field): ?FormAnswer {
    $form = ReviewLib::reload($form);
    $submit = new SubmitForm();
    $submit->form = $form;
    $submit->values = [];
    $submit->panelMemberId = $memberId;
    return $submit->save(null, false, false, false);
};

$post(['take_part' => 'no', 'linkage' => 'no']);
$refused = $submitAs($form, (int)$member->id);
$check($refused instanceof FormAnswer, 'refusal did not save');
if ($refused) {
    $refused->refresh();
    $check((string)$refused->outcome === FormAnswer::OUTCOME_NOT_CONSENTED, 'refusal was not not_consented');
    $check(!$refused->countsAsComplete(), 'a refusal counted as complete');
}

$post(['take_part' => 'yes', 'linkage' => 'no']);
Yii::$app->session->remove('cf-consent-withdraw');
$agreed = $submitAs($form, (int)$member->id);
$check($agreed instanceof FormAnswer && $agreed->countsAsComplete(), 'agreeing did not count as complete');
$token = (string)Yii::$app->session->get('cf-consent-withdraw');
$record = (new Query())->from('custom_form_consent_record')->where(['answer_id' => (int)$agreed->id])->one();
$check($record && (string)$record['signature_method'] === 'typed', 'the agreed record was not stored');
$decoded = json_decode((string)($record['items_json'] ?? ''), true);
$check(is_array($decoded) && ($decoded['take_part'] ?? '') === 'yes' && ($decoded['linkage'] ?? '') === 'no', 'optional refusal was not stored as no');
$agreedDoc = (new Query())->from('custom_form_consent_document')->where(['id' => (int)$record['document_id']])->one();
$html = $svc->certificateHtml(ReviewLib::reload($form), $record);
$check($agreedDoc && str_contains($html, 'version ' . (int)$agreedDoc['version']) && str_contains($html, (string)$record['content_hash']) && str_contains($html, 'take_part'), 'the certificate missed version, hash, or items');
$check(!str_contains($html, 'ip_hash') && !str_contains(strtolower($html), 'user-agent'), 'the certificate included a client hash');

$v2 = $svc->newVersion(ReviewLib::reload($form), (int)$draft['id']);
$check($v2 > 0, 'the next version was not created');
$check($svc->publish(ReviewLib::reload($form), (int)$v2) === [], 'version 2 did not publish');
$again = $svc->resolveDocument(ReviewLib::reload($form), FormField::findOne((int)$field->id), null);
$check($again && (int)$again['version'] > (int)$agreedDoc['version'], 'next visit did not show the newer version');
$v1still = (new Query())->from('custom_form_consent_record')->where(['answer_id' => (int)$agreed->id])->exists();
$check($v1still, 'the version 1 record was removed');
$post(['take_part' => 'yes', 'linkage' => 'yes']);
$second = $submitAs($form, (int)$member->id);
$req = (new Query())->from('custom_form_consent_requirement')->where([
    'form_id' => (int)$form->id,
    'panel_member_id' => (int)$member->id,
])->orderBy(['id' => SORT_DESC])->one();
$check($second && $req && (int)$req['satisfied_record_id'] > 0, 'the requirement was not updated');

$check($svc->withdrawByToken($token, 'delete_requested', 'changed my mind'), 'the withdrawal token did not work');
$check(!$svc->withdrawByToken($token, 'stop_contact', 'again'), 'the withdrawal token worked twice');
$member->refresh();
$check((string)$member->status === FormPanelMember::STATUS_INACTIVE, 'withdrawal did not stop contact');
$check($svc->blocksContact((int)$member->id), 'reminders would still reach a withdrawn member');
$task = (new Query())->from('custom_form_admin_task')->where(['form_id' => (int)$form->id, 'kind' => 'erasure', 'member_id' => (int)$member->id])->one();
$check((bool)$task && (string)$task['status'] === 'open', 'delete my data did not open an admin task');
$still = FormAnswer::findOne((int)$agreed->id);
$check($still && $still->countsAsComplete(), 'withdrawal deleted the answers');

$anon = ReviewLib::form($space, 'EV F2 anonymous consent', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
    'identity_mode' => CustomForm::IDENTITY_FULLY_ANONYMOUS,
]);
$anon->setSetting('econsent_enabled', '1');
$anon->save(false);
ReviewLib::clearFields($anon);
$anonDraft = $svc->saveDraft($anon, null, [
    'title' => 'Anonymous sheet',
    'body_html' => '<p>Anon</p>',
    'items' => [$items[0]],
]);
$svc->publish($anon, (int)$anonDraft['id']);
$anonField = ReviewLib::field($anon, FormField::TYPE_CONSENT, 'Consent', [
    'variable' => 'f2_anon',
    'options' => json_encode(['document_id' => (int)$anonDraft['id'], 'signature' => 'checkbox']),
]);
$anon = ReviewLib::publishOpen($anon);
Yii::$app->request->setBodyParams([
    'consent' => [
        (int)$anonField->id => [
            'items' => ['take_part' => 'yes'],
            'signature_method' => 'checkbox',
            'attestation' => '1',
        ],
    ],
]);
$anonAnswer = ReviewLib::submit($anon, []);
$check($anonAnswer instanceof FormAnswer, 'anonymous consent did not save');
if ($anonAnswer) {
    $anonAnswer->refresh();
    $check($anonAnswer->consent_version === null || (int)$anonAnswer->consent_version === 0, 'anonymous answer stored a consent version');
    $anonRecord = (new Query())->from('custom_form_consent_record')->where(['form_id' => (int)$anon->id])->orderBy(['id' => SORT_DESC])->one();
    $check($anonRecord && $anonRecord['answer_id'] === null && $anonRecord['user_id'] === null && $anonRecord['panel_member_id'] === null, 'anonymous consent was linked to the answer');
}

$sourceVersion = (int)$agreedDoc['version'];
$svc->saveTranslation($form, $sourceVersion, 'body', 'cy', '<p>Taflen</p>');
$svc->saveTranslation($form, $sourceVersion, 'take_part', 'cy', 'Rwyf yn cytuno');
$exported = (new TranslationImportExportService())->exportJson(ReviewLib::reload($form));
$keys = array_column($exported['strings'], 'key');
$check(in_array('consent.' . $sourceVersion . '.body', $keys, true) && in_array('consent.' . $sourceVersion . '.take_part', $keys, true), 'translation export missed the consent text');
$imported = (new TranslationImportExportService())->importJson(ReviewLib::reload($form), json_encode([
    'strings' => [[
        'key' => 'consent.' . $sourceVersion . '.linkage',
        'translations' => ['cy' => 'Gallwch gysylltu'],
    ]],
]));
$check($imported === null, 'translation import failed');
$check($svc->translated(ReviewLib::reload($form), $sourceVersion, 'cy', 'linkage') === 'Gallwch gysylltu', 'translation import did not round-trip');

$member->consent_at = '2020-01-02 00:00:00';
$member->save(false, ['consent_at']);
$added = $svc->importLegacyForForm(ReviewLib::reload($form));
$legacy = (new Query())->from('custom_form_consent_record')->where([
    'form_id' => (int)$form->id,
    'panel_member_id' => (int)$member->id,
    'signature_method' => 'legacy',
])->one();
$check($added >= 1 && $legacy && !$svc->legacySatisfies((int)$legacy['id']), 'legacy consent satisfied re-consent');

$module->settings->set(Module::SETTING_ECONSENT, '0');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
