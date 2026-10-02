<?php
/**
 * SCO-5. Delphi consensus adds the agree band. It does not freeze the single
 * most common code, and excluded codes are left out of the share. Disagreement is
 * consensus out (reported, not frozen); a question can set its own bands, excluded codes and
 * IQR limit; median and IQR are reported. SCO-9: a closed round freezes without a summary.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormRound;
use humhub\modules\thiscoveryForms\services\RoundService;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV SCO bands', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
    'kind' => 'consensus',
    'consensus_threshold' => 70,
]);
ReviewLib::clearFields($form);
$field = ReviewLib::field($form, FormField::TYPE_RADIO, 'Score', [
    'variable' => 'sco_score',
    'required' => 0,
    'options' => ['1', '2', '3', '7', '8', '9', '99'],
]);
$form = ReviewLib::publishOpen($form);
$form->consensus_threshold = 70;
$form->consensus_agree_from = '';
$form->consensus_agree_to = '';
$form->consensus_disagree_from = '';
$form->consensus_disagree_to = '';
$form->consensus_exclude_codes = '';
$form->save(false);
$form = ReviewLib::reload($form);

$round = FormRound::find()->where(['form_id' => (int)$form->id])->orderBy(['round_number' => SORT_DESC])->one();
if (!$round) {
    $round = new FormRound();
    $round->form_id = (int)$form->id;
    $round->round_number = 1;
    $round->title = 'Round 1';
}
$round->status = FormRound::STATUS_CLOSED;
$check($round->save(), 'could not save the round ' . json_encode($round->errors));

$store = static function (int $formId, int $roundId, int $fieldId, string $value): void {
    $answer = new FormAnswer();
    $answer->form_id = $formId;
    $answer->round_id = $roundId;
    $answer->status = FormAnswer::STATUS_COMPLETE;
    $answer->is_test = 0;
    $answer->weight = 1;
    $answer->save(false);
    Yii::$app->db->createCommand()->insert('custom_form_answer_field', [
        'answer_id' => (int)$answer->id,
        'field_id' => $fieldId,
        'value' => $value,
    ])->execute();
};

foreach (['7', '8', '9'] as $value) {
    $store((int)$form->id, (int)$round->id, (int)$field->id, $value);
}

$svc = new RoundService();
$without = $svc->computeFrozenFieldIds($form, $round);
$check(!in_array((int)$field->id, $without, true), 'a 7/8/9 split froze on the single most common code');

$form->consensus_agree_from = '7';
$form->consensus_agree_to = '9';
$form->consensus_disagree_from = '1';
$form->consensus_disagree_to = '3';
$form->save(false);
$form = ReviewLib::reload($form);
$with = $svc->computeFrozenFieldIds($form, $round);
$check(in_array((int)$field->id, $with, true), 'the agree band did not combine 7, 8 and 9');

$previousIds = FormAnswer::find()->select('id')->where(['form_id' => (int)$form->id])->column();
if ($previousIds) {
    Yii::$app->db->createCommand()->delete('custom_form_answer_field', ['answer_id' => $previousIds])->execute();
    FormAnswer::deleteAll(['id' => $previousIds]);
}
foreach (['1', '1', '9'] as $value) {
    $store((int)$form->id, (int)$round->id, (int)$field->id, $value);
}
$form->consensus_threshold = 60;
$form->consensus_agree_from = '7';
$form->consensus_agree_to = '9';
$form->consensus_disagree_from = '1';
$form->consensus_disagree_to = '3';
$form->save(false);
$form = ReviewLib::reload($form);
$disagree = $svc->computeFrozenFieldIds($form, $round);
$check(!in_array((int)$field->id, $disagree, true), 'the disagree band still froze the most common code');

$rows = static fn(array $values): array => array_map(static fn($v) => ['value' => $v, 'weight' => 1], $values);
$out = $svc->itemConsensus($form, $field, $rows(['1', '1', '9']));
$check($out['status'] === RoundService::CONSENSUS_OUT, 'a disagree majority was not consensus out: ' . json_encode($out));

// The question's own bands win over the form's, and its excluded code leaves the denominator.
$field = \humhub\modules\thiscoveryForms\models\FormField::findOne((int)$field->id);
$field->setValidation(['consensus_agree_from' => '1', 'consensus_agree_to' => '3', 'consensus_exclude' => '99']);
$field->save(false);
$own = $svc->itemConsensus($form, $field, $rows(['1', '2', '99', '99']));
$check($own['status'] === RoundService::CONSENSUS_IN && (float)$own['agree'] === 100.0, 'the question\'s own band or exclusion was ignored: ' . json_encode($own));

// Median and IQR on a numeric scale; an IQR limit can hold consensus back.
$spread = $svc->itemConsensus($form, $field, $rows(['1', '2', '3', '3']));
$check($spread['median'] !== null && $spread['iqr'] !== null, 'median or IQR missing: ' . json_encode($spread));
$field->setValidation(['consensus_agree_from' => '1', 'consensus_agree_to' => '3', 'consensus_iqr_max' => '0.5']);
$field->save(false);
$held = $svc->itemConsensus($form, $field, $rows(['1', '3', '1', '3']));
$check($held['status'] === RoundService::CONSENSUS_NONE, 'consensus was reached with an IQR above the limit: ' . json_encode($held));

// SCO-9: a closed round freezes its consensus items without a published summary.
$previousIds = FormAnswer::find()->select('id')->where(['form_id' => (int)$form->id])->column();
if ($previousIds) {
    Yii::$app->db->createCommand()->delete('custom_form_answer_field', ['answer_id' => $previousIds])->execute();
    FormAnswer::deleteAll(['id' => $previousIds]);
}
foreach (['7', '8', '9'] as $value) {
    $store((int)$form->id, (int)$round->id, (int)$field->id, $value);
}
$field->setValidation([]);
$field->save(false);
$form->freeze_on_consensus = 1;
$form->consensus_threshold = 70;
$form->save(false);
$form = ReviewLib::reload($form);
$round->summary_html = null;
$round->published_at = null;
$round->frozen_field_ids_json = null;
$round->save(false);
$unpublished = $svc->frozenFor($form, $round);
$check(in_array((int)$field->id, $unpublished, true), 'an unpublished closed round froze nothing');
$check(FormRound::findOne((int)$round->id)->frozen_field_ids_json !== null, 'the frozen list was not stored');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
