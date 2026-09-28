<?php
/**
 * HF-3. Translated choice labels must keep their codes.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormFieldI18n;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\thiscoveryForms\services\TranslationImportExportService;
use humhub\modules\thiscoveryForms\services\TranslationService;

$failures = [];
$admin = review_user('review_netadmin');
ReviewLib::asUser($admin);
$space = review_space();
$form = ReviewLib::form($space, 'EV HF3 translation codes', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
(new yii\db\Query())->createCommand()->delete('custom_form_answer_field', ['answer_id' => (new yii\db\Query())->select('id')->from('custom_form_answer')->where(['form_id' => (int)$form->id])])->execute();
(new yii\db\Query())->createCommand()->delete('custom_form_answer', ['form_id' => (int)$form->id])->execute();
ReviewLib::clearFields($form);

$radio = ReviewLib::field($form, FormField::TYPE_RADIO, 'Choice', [
    'variable' => 'choice',
    'required' => 1,
    'sort_order' => 1,
    'options' => ['yes | Yes', 'no | No'],
]);
$follow = ReviewLib::field($form, FormField::TYPE_TEXT, 'Because', [
    'variable' => 'because',
    'required' => 0,
    'sort_order' => 2,
    'logic' => [
        'action' => 'show',
        'combinator' => 'and',
        'rules' => [[
            'fieldKey' => (string)$radio->id,
            'operator' => 'equals',
            'value' => 'yes',
        ]],
    ],
]);
$form = ReviewLib::publishOpen($form);

$i18n = new FormFieldI18n();
$i18n->field_id = (int)$radio->id;
$i18n->language = 'fr';
$i18n->label = 'Choix';
$i18n->options_json = json_encode(['options' => ['Oui', 'Non']], JSON_UNESCAPED_UNICODE);
$i18n->save(false);

$form = ReviewLib::reload($form);
(new TranslationService())->overlay($form, 'fr');
$radioFr = null;
foreach ($form->fields as $field) {
    if ((int)$field->id === (int)$radio->id) {
        $radioFr = $field;
    }
}
$pairs = $radioFr ? $radioFr->getChoicePairs() : [];
$codes = array_column($pairs, 'code');
$labels = array_column($pairs, 'label');
if ($codes !== ['yes', 'no']) {
    $failures[] = 'codes became ' . json_encode($codes);
}
if ($labels !== ['Oui', 'Non']) {
    $failures[] = 'labels became ' . json_encode($labels);
}

$visible = false;
foreach ($form->fields as $field) {
    if ((int)$field->id === (int)$follow->id) {
        $visible = $field->isVisible([(int)$radio->id => 'yes'], $form->fields);
    }
}
if (!$visible) {
    $failures[] = 'logic against code yes did not show the follow-up';
}

ReviewLib::asUser(null);
$submit = new SubmitForm();
$submit->form = $form;
$submit->values = [(int)$radio->id => 'yes', (int)$follow->id => 'because'];
$answer = $submit->save(null, true, false, false);
$stored = $answer ? (new yii\db\Query())->from('custom_form_answer_field')->where([
    'answer_id' => (int)$answer->id,
    'field_id' => (int)$radio->id,
])->one() : null;
if (!$stored || (string)$stored['value'] !== 'yes') {
    $failures[] = 'stored choice ' . json_encode($stored['value'] ?? null);
}

$svc = new TranslationService();
$svc->saveFieldStrings($radio, 'cy', [
    'label' => 'Dewis',
    'options' => "Ie\nNa",
]);
$saved = FormFieldI18n::findOne(['field_id' => (int)$radio->id, 'language' => 'cy']);
$overlay = $saved ? $saved->getOptionsOverlay() : [];
if (($overlay['options']['yes'] ?? '') !== 'Ie' || ($overlay['options']['no'] ?? '') !== 'Na') {
    $failures[] = 'cy translation was not stored by code';
}

$export = (new TranslationImportExportService())->exportJson($form);
$blob = json_encode($export);
if (!str_contains($blob, 'option.yes') || !str_contains($blob, 'Ie')) {
    $failures[] = 'export did not key the translation by code';
}
$again = (new TranslationImportExportService())->importJson($form, json_encode($export));
if ($again !== null) {
    $failures[] = 'import round trip: ' . $again;
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
