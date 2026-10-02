<?php
/**
 * V3-26 / V3-27 / V3-28. The server enforces the configured signature, witness and
 * must-read rules; the record hashes what was shown; fully anonymous records hold
 * nothing that identifies the person or times the submit.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\services\ConsentService;
use yii\db\Query;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

$module = Yii::$app->getModule('thiscovery-forms');
$module->settings->set(Module::SETTING_ECONSENT, '1');
ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();
$svc = new ConsentService();
$items = [['code' => 'take_part', 'label' => 'I agree', 'required' => 1, 'input' => 'yes_no']];

$build = static function (string $title, array $formAttrs, array $options) use ($space, $svc, $items): array {
    $form = ReviewLib::form($space, $title, $formAttrs + ['allow_anonymous' => 1, 'allow_multiple' => 1]);
    $form->setSetting('econsent_enabled', '1');
    $form->save(false);
    ReviewLib::clearFields($form);
    $doc = $svc->saveDraft($form, null, ['title' => $title . ' sheet', 'body_html' => '<p>Sheet</p>', 'items' => $items]);
    $svc->publish($form, (int)$doc['id']);
    $field = ReviewLib::field($form, FormField::TYPE_CONSENT, 'Consent', [
        'variable' => 'hc_' . substr(md5($title), 0, 6),
        'options' => json_encode(['document_id' => (int)$doc['id']] + $options),
    ]);
    return [ReviewLib::publishOpen($form), $field, $doc];
};
$attempt = static function (CustomForm $form, FormField $field, array $bag): array {
    Yii::$app->request->setBodyParams(['consent' => [(int)$field->id => $bag + ['items' => ['take_part' => 'yes']]]]);
    $submit = new SubmitForm();
    $submit->form = ReviewLib::reload($form);
    $submit->values = [];
    $saved = $submit->save(null, true, false, false);
    return [$saved, implode(' ', $submit->getErrorSummary(true))];
};

// V3-28: a typed field cannot be satisfied by posting checkbox + attestation.
[$typed, $typedField] = $build('HC typed', [], ['signature' => 'typed']);
[$saved, $errors] = $attempt($typed, $typedField, ['signature_method' => 'checkbox', 'attestation' => '1']);
$check(!$saved && str_contains($errors, 'Type your name'), 'checkbox attestation passed on a typed field');

// Drawn: a name or a real PNG is required; junk image data is not a signature.
[$drawn, $drawnField] = $build('HC drawn', [], ['signature' => 'drawn']);
[$saved] = $attempt($drawn, $drawnField, ['signature_image' => 'data:image/png;base64,' . base64_encode('not a png')]);
$check(!$saved, 'junk image data passed as a drawn signature');

// Witness details are required when configured.
[$witnessed, $witnessField] = $build('HC witness', [], ['signature' => 'typed', 'witness' => 1]);
[$saved, $errors] = $attempt($witnessed, $witnessField, ['signature_name' => 'Ada']);
$check(!$saved && str_contains($errors, 'witness'), 'a witnessed consent saved without witness details');
[$saved] = $attempt($witnessed, $witnessField, ['signature_name' => 'Ada', 'witness_name' => 'Bea', 'witness_role' => 'Nurse']);
$check($saved instanceof FormAnswer, 'a witnessed consent with details did not save');

// Must-read is enforced on the server.
[$mustRead, $mustField] = $build('HC must read', [], ['signature' => 'typed', 'must_read' => 1]);
[$saved] = $attempt($mustRead, $mustField, ['signature_name' => 'Ada', 'scrolled_to_end' => '0']);
$check(!$saved, 'must-read passed without reading to the end');
[$saved] = $attempt($mustRead, $mustField, ['signature_name' => 'Ada', 'scrolled_to_end' => '1']);
$check($saved instanceof FormAnswer, 'must-read with read-to-end did not save');

// V3-27: the record hash is the hash of the rendered presentation; a stale hash is refused.
$shown = $svc->presentation(ReviewLib::reload($typed), $svc->latestPublished(ReviewLib::reload($typed)));
[$saved, $errors] = $attempt($typed, $typedField, ['signature_name' => 'Ada', 'shown_hash' => str_repeat('0', 64)]);
$check(!$saved && str_contains($errors, 'changed'), 'a stale shown hash was accepted');
[$saved] = $attempt($typed, $typedField, ['signature_name' => 'Ada', 'shown_hash' => $shown['hash']]);
$check($saved instanceof FormAnswer, 'the matching shown hash was refused');
if ($saved) {
    $rec = (new Query())->from('custom_form_consent_record')->where(['form_id' => (int)$typed->id])->orderBy(['id' => SORT_DESC])->one();
    $check($rec && (string)$rec['content_hash'] === $shown['hash'], 'the record did not hash what was shown');
}

// V3-26: fully anonymous records drop names, witness, client hashes, files and time.
[$anon, $anonField] = $build('HC anonymous', ['identity_mode' => CustomForm::IDENTITY_FULLY_ANONYMOUS], ['signature' => 'typed', 'witness' => 1]);
$anon->setSetting('consent_store_client_hashes', '1');
$anon->save(false);
$check($svc->effectiveSignature(ReviewLib::reload($anon), 'typed') === 'checkbox', 'an anonymous form did not force checkbox attestation');
[$saved] = $attempt($anon, $anonField, ['signature_name' => 'Ada Lovelace', 'witness_name' => 'Bea', 'witness_role' => 'Nurse']);
$check(!$saved, 'an anonymous consent saved without attestation');
[$saved] = $attempt($anon, $anonField, ['attestation' => '1', 'signature_name' => 'Ada Lovelace', 'witness_name' => 'Bea']);
$check($saved instanceof FormAnswer, 'an anonymous attestation did not save');
$rec = (new Query())->from('custom_form_consent_record')->where(['form_id' => (int)$anon->id])->orderBy(['id' => SORT_DESC])->one();
$check($rec && $rec['answer_id'] === null && $rec['signature_name'] === null && $rec['witness_name'] === null
    && $rec['ip_hash'] === null && $rec['ua_hash'] === null && $rec['signature_file_id'] === null
    && (string)$rec['signature_method'] === 'checkbox' && str_ends_with((string)$rec['signed_at'], '00:00:00'),
    'an anonymous consent record kept identifying detail');
$audit = $rec ? (new Query())->from('custom_form_consent_audit')->where(['record_id' => (int)$rec['id']])->one() : null;
$check($audit && str_ends_with((string)$audit['created_at'], '00:00:00') && $audit['actor_id'] === null, 'the anonymous audit row was timed or attributed');

// V3-43: a required tick box left unticked posts nothing (a missing answer, not a refusal);
// an optional one still posts "no". Radios are grouped and named by the statement.
$partial = dirname(__DIR__) . '/views/form/_consent_item.php';
$render = static fn(array $item): string => Yii::$app->view->renderFile($partial, ['item' => $item, 'nameBase' => 'consent[9]', 'idBase' => 'cf-consent-9']);
$requiredBox = $render(['code' => 'agree', 'label' => 'I agree', 'required' => 1, 'input' => 'checkbox']);
$optionalBox = $render(['code' => 'contact', 'label' => 'Contact me', 'required' => 0, 'input' => 'checkbox']);
$radios = $render(['code' => 'store', 'label' => 'Store samples', 'required' => 1, 'input' => 'yes_no']);
$check(!str_contains($requiredBox, 'value="no"'), 'an unticked required box would post a refusal');
$check(str_contains($optionalBox, 'value="no"'), 'an unticked optional box no longer posts no');
$check(str_contains($requiredBox, 'aria-labelledby="cf-consent-9-agree"'), 'the consent box has no accessible name');
$check(str_contains($radios, 'role="radiogroup"') && str_contains($radios, 'aria-labelledby="cf-consent-9-store"'), 'the Yes/No radios are not grouped');
[$boxForm, $boxField] = (static function () use ($space, $svc) {
    $form = ReviewLib::form($space, 'HC required box', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
    $form->setSetting('econsent_enabled', '1');
    $form->save(false);
    ReviewLib::clearFields($form);
    $doc = $svc->saveDraft($form, null, ['title' => 'Box sheet', 'body_html' => '<p>Sheet</p>', 'items' => [
        ['code' => 'agree', 'label' => 'I agree', 'required' => 1, 'input' => 'checkbox'],
    ]]);
    $svc->publish($form, (int)$doc['id']);
    $field = ReviewLib::field($form, FormField::TYPE_CONSENT, 'Consent', [
        'variable' => 'hc_box', 'options' => json_encode(['document_id' => (int)$doc['id'], 'signature' => 'typed']),
    ]);
    return [ReviewLib::publishOpen($form), $field];
})();
Yii::$app->request->setBodyParams(['consent' => [(int)$boxField->id => ['items' => [], 'signature_name' => 'Ada']]]);
$missed = new SubmitForm();
$missed->form = ReviewLib::reload($boxForm);
$missed->values = [];
$check($missed->save(null, true, false, false) === null && str_contains(implode(' ', $missed->getErrorSummary(true)), 'needs an answer'),
    'an unticked required box was not reported as a missing answer');

$module->settings->set(Module::SETTING_ECONSENT, '0');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
