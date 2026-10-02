<?php
/**
 * DAT-12. Saving a draft keeps an answer that logic now hides; the final submit removes it.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\LogicEngine;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV DAT-12 draft hidden', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
ReviewLib::clearFields($form);
$gate = ReviewLib::field($form, FormField::TYPE_RADIO, 'Smoker?', ['variable' => 'dat12_gate', 'options' => ['yes', 'no']]);
$detail = ReviewLib::field($form, FormField::TYPE_TEXT, 'How many?', ['variable' => 'dat12_detail', 'logic' => LogicEngine::fromFormula('[dat12_gate] = "yes"')]);
$form = ReviewLib::publishOpen($form);
$cell = static fn(FormAnswer $a) => FormAnswerField::find()->where(['answer_id' => (int)$a->id, 'field_id' => (int)$detail->id])->one();

$draft = ReviewLib::submit($form, [(int)$gate->id => 'yes', (int)$detail->id => '10 a day'], true);
$check($draft && $cell($draft), 'the first draft did not store the follow-up');
ReviewLib::submit($form, [(int)$gate->id => 'no'], true, FormAnswer::findOne((int)$draft->id));
$check($cell($draft) !== null, 'a draft save deleted the hidden follow-up');
ReviewLib::submit($form, [(int)$gate->id => 'no'], false, FormAnswer::findOne((int)$draft->id));
$check($cell($draft) === null, 'the final submit kept a hidden answer');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
