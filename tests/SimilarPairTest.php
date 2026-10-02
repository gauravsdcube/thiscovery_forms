<?php
/**
 * INT-3. When a response is found to be nearly identical to an earlier one, the earlier one is
 * flagged and re-scored too: it was scored before its twin existed.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormIntegrityMeta;
use humhub\modules\thiscoveryForms\services\FillContext;
use humhub\modules\thiscoveryForms\services\integrity\IntegrityService;
use humhub\modules\thiscoveryForms\services\integrity\IntegritySettings;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV INT3 similar pair', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
ReviewLib::clearFields($form);
$questions = [];
for ($i = 1; $i <= 8; $i++) {
    $questions[] = ReviewLib::field($form, FormField::TYPE_RADIO, 'Item ' . $i, [
        'variable' => 'int3_q' . $i,
        'sort_order' => $i,
        'options' => ['1', '2', '3', '4', '5'],
    ]);
}
$form = ReviewLib::publishOpen($form);
$cfg = IntegritySettings::defaults();
$cfg['enabled'] = 1;
$cfg['captcha'] = 0;
$cfg['similarity_detection'] = 1;
$cfg['speed_detection'] = 0;
$cfg['straightline_detection'] = 0;
$cfg['duplicate_detection'] = 0;
$form->setSetting(IntegritySettings::SETTING_KEY, $cfg);
$form->save(false);

$svc = new IntegrityService();
$complete = static function (array $codes) use ($form, $questions, $svc): FormAnswer {
    $answer = new FormAnswer();
    $answer->form_id = (int)$form->id;
    $answer->status = FormAnswer::STATUS_COMPLETE;
    $answer->is_test = 0;
    $answer->submitted_at = date('Y-m-d H:i:s');
    $answer->save(false);
    foreach ($questions as $i => $q) {
        Yii::$app->db->createCommand()->insert('custom_form_answer_field', [
            'answer_id' => (int)$answer->id,
            'field_id' => (int)$q->id,
            'value' => (string)$codes[$i],
        ])->execute();
    }
    $svc->onComplete(ReviewLib::reload($form), $answer, [], new FillContext($form));
    return $answer;
};

// Varied answers so agreement by chance is low, then a twin of the first.
$first = $complete([1, 5, 2, 4, 3, 1, 5, 2]);
foreach ([[3, 3, 4, 2, 5, 4, 1, 3], [2, 1, 5, 3, 1, 2, 4, 5], [4, 2, 1, 5, 2, 3, 3, 1], [5, 4, 3, 1, 4, 5, 2, 4]] as $noise) {
    $complete($noise);
}
$twin = $complete([1, 5, 2, 4, 3, 1, 5, 2]);

$twinMeta = FormIntegrityMeta::findOne(['answer_id' => (int)$twin->id]);
$firstMeta = FormIntegrityMeta::findOne(['answer_id' => (int)$first->id]);
$check($twinMeta && in_array((int)$first->id, $twinMeta->getSimilarAnswerIds(), true), 'the later response was not matched to the first');
$check($firstMeta && in_array((int)$twin->id, $firstMeta->getSimilarAnswerIds(), true), 'the first response of the pair was not flagged');
$check($firstMeta && (float)$firstMeta->similarity_score > 0, 'the first response was not re-scored');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
