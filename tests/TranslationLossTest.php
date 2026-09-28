<?php
/**
 * NEW-3. Count a French complete answer whose required choice was never stored,
 * and a stored choice code that is not an option. A correct French answer is not counted.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\commands\DetectTranslationLossController;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormFieldI18n;
use yii\db\Query;

$failures = [];
ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV R3 translation loss', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
$form->source_language = 'en';
$form->enabled_languages = ['en', 'fr'];
$form->save(false);
(new Query())->createCommand()->delete('custom_form_answer_field', [
    'answer_id' => (new Query())->select('id')->from('custom_form_answer')->where(['form_id' => (int)$form->id]),
])->execute();
(new Query())->createCommand()->delete('custom_form_answer', ['form_id' => (int)$form->id])->execute();
ReviewLib::clearFields($form);
$choice = ReviewLib::field($form, FormField::TYPE_RADIO, 'Yes or no', [
    'variable' => 'r3_choice',
    'required' => 1,
    'sort_order' => 1,
]);
$choice->setOptionsFromText("1 | Yes\n0 | No", true);
$choice->save(false);
$i18n = new FormFieldI18n();
$i18n->field_id = (int)$choice->id;
$i18n->language = 'fr';
$i18n->options_json = json_encode(['options' => ['Oui', 'Non']]);
$i18n->save(false);
$form = ReviewLib::publishOpen($form);

$make = static function (int $formId, string $lang) use ($choice): FormAnswer {
    $answer = new FormAnswer();
    $answer->form_id = $formId;
    $answer->status = FormAnswer::STATUS_COMPLETE;
    $answer->is_test = 0;
    $answer->submitted_at = date('Y-m-d H:i:s');
    $answer->save(false);
    $answer->setVars(['response_language' => $lang]);
    $answer->updateAttributes(['vars_json' => $answer->vars_json]);
    return $answer;
};

$lost = $make((int)$form->id, 'fr');
$good = $make((int)$form->id, 'fr');
$cell = new FormAnswerField();
$cell->answer_id = (int)$good->id;
$cell->field_id = (int)$choice->id;
$cell->value = '1';
$cell->save(false);
$bad = $make((int)$form->id, 'fr');
$badCell = new FormAnswerField();
$badCell->answer_id = (int)$bad->id;
$badCell->field_id = (int)$choice->id;
$badCell->value = 'oui';
$badCell->save(false);
$englishGap = $make((int)$form->id, 'en');

$detector = new DetectTranslationLossController('detect-translation-loss', Yii::$app->getModule('thiscovery-forms'));
foreach ([$lost, $good, $bad, $englishGap] as $answer) {
    $answer->refresh();
}
if (!$detector->isTranslationLoss($lost)) {
    $failures[] = 'missing French choice was not counted';
}
if ($detector->isTranslationLoss($good)) {
    $failures[] = 'a correct French answer was counted';
}
if (!$detector->isTranslationLoss($bad)) {
    $failures[] = 'an unknown choice code was not counted';
}
if ($detector->isTranslationLoss($englishGap)) {
    $failures[] = 'an English gap was counted as translation loss';
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
