<?php
/**
 * INT-2, INT-4, INT-5. Score only questions on the route. Speeding is seconds
 * per question shown, and the browser cannot set the start time.
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
$space = review_space();
$form = ReviewLib::form($space, 'EV INT route score', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
ReviewLib::clearFields($form);
$gate = ReviewLib::field($form, FormField::TYPE_RADIO, 'Continue', [
    'variable' => 'int_gate',
    'required' => 0,
    'sort_order' => 1,
    'options' => ['yes', 'no'],
    'actions' => [['fn' => 'goto_page', 'page_key' => 'land']],
]);
ReviewLib::field($form, FormField::TYPE_PAGE_BREAK, 'Mid', [
    'variable' => 'int_mid',
    'sort_order' => 2,
    'options' => ['__type' => 'page_break', 'pageKey' => 'mid', 'title' => 'Mid'],
]);
$hidden = ReviewLib::field($form, FormField::TYPE_RADIO, 'Attention', [
    'variable' => 'int_attention',
    'required' => 0,
    'sort_order' => 3,
    'options' => ['blue', 'red'],
]);
$hidden->setAttentionCheck(true, 'blue');
$hidden->save(false);
ReviewLib::field($form, FormField::TYPE_PAGE_BREAK, 'Land', [
    'variable' => 'int_land',
    'sort_order' => 4,
    'options' => ['__type' => 'page_break', 'pageKey' => 'land', 'title' => 'Land'],
]);
$shown = ReviewLib::field($form, FormField::TYPE_TEXT, 'Shown', [
    'variable' => 'int_shown',
    'required' => 0,
    'sort_order' => 5,
]);
$form = ReviewLib::publishOpen($form);

$cfg = IntegritySettings::defaults();
$cfg['enabled'] = 1;
$cfg['captcha'] = 0;
$cfg['speed_detection'] = 1;
$cfg['attention_checks'] = 1;
$cfg['straightline_detection'] = 0;
$cfg['freetext_checks'] = 0;
$cfg['speed_min_seconds'] = 15;
$form->setSetting(IntegritySettings::SETTING_KEY, $cfg);
$form->save(false);

$svc = new IntegrityService();
$ids = $svc->shownFieldIds($form, [(int)$gate->id => 'yes', (int)$shown->id => 'ok']);
$check(isset($ids[(int)$shown->id]), 'the landing question was not on the route');
$check(!isset($ids[(int)$hidden->id]), 'a skipped attention check was still on the route');

$answer = new FormAnswer();
$answer->form_id = (int)$form->id;
$answer->status = FormAnswer::STATUS_COMPLETE;
$answer->is_test = 0;
$answer->submitted_at = date('Y-m-d H:i:s');
$answer->save(false);
Yii::$app->db->createCommand()->insert('custom_form_answer_field', [
    'answer_id' => (int)$answer->id,
    'field_id' => (int)$gate->id,
    'value' => 'yes',
])->execute();
Yii::$app->db->createCommand()->insert('custom_form_answer_field', [
    'answer_id' => (int)$answer->id,
    'field_id' => (int)$shown->id,
    'value' => 'ok',
])->execute();

Yii::$app->session->set(IntegrityService::START_PREFIX . (int)$form->id, date('Y-m-d H:i:s', time() - 12));
$meta = $svc->onComplete($form, $answer, [
    IntegrityService::TIMING_NAME => json_encode(['startedAt' => (time() - 86400) * 1000]),
], new FillContext($form));
$check($meta instanceof FormIntegrityMeta, 'completion did not store integrity metadata');
if ($meta) {
    $codes = array_map(static fn($flag) => ($flag['category'] ?? '') . ':' . ($flag['code'] ?? ''), $meta->getFlags());
    $check(!in_array('attention:failed', $codes, true), 'a skipped attention check was scored: ' . implode(',', $codes));
    $check(!in_array('speed:absolute', $codes, true), 'a short route was judged on total seconds: ' . implode(',', $codes));
    $started = strtotime((string)$meta->started_at);
    $check($started > time() - 120, 'the browser start time replaced the server time');
}

$fast = new FormAnswer();
$fast->form_id = (int)$form->id;
$fast->status = FormAnswer::STATUS_COMPLETE;
$fast->is_test = 0;
$fast->submitted_at = date('Y-m-d H:i:s');
$fast->save(false);
Yii::$app->db->createCommand()->insert('custom_form_answer_field', [
    'answer_id' => (int)$fast->id,
    'field_id' => (int)$gate->id,
    'value' => 'yes',
])->execute();
Yii::$app->session->set(IntegrityService::START_PREFIX . (int)$form->id, date('Y-m-d H:i:s', time() - 2));
$fastMeta = $svc->onComplete($form, $fast, [], new FillContext($form));
$fastCodes = $fastMeta ? array_map(static fn($flag) => ($flag['category'] ?? '') . ':' . ($flag['code'] ?? ''), $fastMeta->getFlags()) : [];
$check(in_array('speed:absolute', $fastCodes, true), 'one second per question was not flagged');

foreach ([1, 2, 3] as $n) {
    $prior = new FormAnswer();
    $prior->form_id = (int)$form->id;
    $prior->status = FormAnswer::STATUS_COMPLETE;
    $prior->is_test = 0;
    $prior->save(false);
    $row = new FormIntegrityMeta();
    $row->answer_id = (int)$prior->id;
    $row->form_id = (int)$form->id;
    $row->duration_seconds = 100;
    $row->shown_question_count = 10;
    $row->speed_score = 0;
    $row->integrity_status = FormIntegrityMeta::STATUS_TRUSTED;
    $row->analysis_status = FormIntegrityMeta::ANALYSIS_INCLUDED;
    $row->started_at = date('Y-m-d H:i:s', time() - 100);
    $row->completed_at = date('Y-m-d H:i:s');
    $check($row->save(false), 'could not store a prior completion');
}

$relative = new FormAnswer();
$relative->form_id = (int)$form->id;
$relative->status = FormAnswer::STATUS_COMPLETE;
$relative->is_test = 0;
$relative->submitted_at = date('Y-m-d H:i:s');
$relative->save(false);
Yii::$app->db->createCommand()->insert('custom_form_answer_field', [
    'answer_id' => (int)$relative->id,
    'field_id' => (int)$gate->id,
    'value' => 'yes',
])->execute();
Yii::$app->session->set(IntegrityService::START_PREFIX . (int)$form->id, date('Y-m-d H:i:s', time() - 6));
$relativeMeta = $svc->onComplete($form, $relative, [], new FillContext($form));
$relativeCodes = $relativeMeta ? array_map(static fn($flag) => ($flag['category'] ?? '') . ':' . ($flag['code'] ?? ''), $relativeMeta->getFlags()) : [];
$check(in_array('speed:relative', $relativeCodes, true), 'seconds per question were not compared with the median');
$check(!in_array('speed:absolute', $relativeCodes, true), 'a moderate short route was flagged as under the absolute floor');

$progress = new FormAnswer();
$progress->form_id = (int)$form->id;
$progress->status = FormAnswer::STATUS_IN_PROGRESS;
$progress->is_test = 0;
$progress->save(false);
$svc->onProgress($form, $progress, [
    IntegrityService::TIMING_NAME => json_encode(['startedAt' => (time() - 86400) * 1000]),
]);
$progressMeta = FormIntegrityMeta::findOne(['answer_id' => (int)$progress->id]);
$progressStart = $progressMeta ? strtotime((string)$progressMeta->started_at) : 0;
$check($progressStart > time() - 120, 'progress kept the browser start time');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
