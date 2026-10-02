<?php
/**
 * V3-51. Rate limits per address and session (not /24), legacy speeding values, chance-
 * adjusted similarity, and loop attention checks that need every repeat to pass.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\services\integrity\IntegrityService;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};
$svc = new IntegrityService();
$call = static function (string $method, array $args) use ($svc) {
    $m = new ReflectionMethod(IntegrityService::class, $method);
    $m->setAccessible(true);
    return $m->invokeArgs($svc, $args);
};

// Speeding: 15 is the old default (2 s/question); over 20 is an old total.
$check($call('secondsPerQuestionFloor', [['speed_min_seconds' => 15], 10]) === 2, 'the old default 15 was not 2 seconds per question');
$check($call('secondsPerQuestionFloor', [['speed_min_seconds' => 60], 10]) === 6, 'a legacy 60 was read as 60 seconds per question');
$check($call('secondsPerQuestionFloor', [['speed_min_seconds' => 3], 10]) === 3, 'a per-question value was changed');

ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();

// Similarity: five shared common answers are chance, not copying.
$simForm = ReviewLib::form($space, 'EV V3-51 similarity', ['allow_anonymous' => 0, 'allow_multiple' => 1]);
ReviewLib::clearFields($simForm);
$ids = [];
for ($i = 1; $i <= 5; $i++) {
    $ids[] = (int)ReviewLib::field($simForm, FormField::TYPE_RADIO, 'S' . $i, ['variable' => 's51_' . $i, 'options' => ['1', '2', '3', '4', '5']])->id;
}
$simForm = ReviewLib::reload($simForm);
$neutral = array_fill_keys($ids, '3');
$pool = array_fill(0, 20, $neutral);
$dist = $call('answerDistributions', [$simForm, $pool]);
$check($svc->choiceSimilarity($simForm, $neutral, $neutral, $dist) < 50, 'five shared "Neutral" answers scored as copying');
$varied = [];
foreach (range(0, 19) as $k) {
    $row = [];
    foreach ($ids as $j => $id) {
        $row[$id] = (string)((($k + $j) % 5) + 1);
    }
    $varied[] = $row;
}
$distVaried = $call('answerDistributions', [$simForm, $varied]);
$check($svc->choiceSimilarity($simForm, $varied[0], $varied[0], $distVaried) > 90, 'identical answers in a varied pool were not flagged');

// INT-6: identical grid answers are flagged only when the grid has reverse-keyed rows.
$gridForm = ReviewLib::form($space, 'EV INT-6 grid', ['allow_anonymous' => 0, 'allow_multiple' => 1]);
ReviewLib::clearFields($gridForm);
$grid = ReviewLib::field($gridForm, FormField::TYPE_GRID_SINGLE, 'Symptoms', ['variable' => 'int6', 'options' => json_encode([
    'rows' => "r1\nr2\nr3\nr4\nr5", 'columns' => "Not at all\nSometimes\nOften",
])]);
$gridForm = ReviewLib::reload($gridForm);
$grid = FormField::findOne((int)$grid->id);
$same = [(int)$grid->id => ['r1' => 'Not at all', 'r2' => 'Not at all', 'r3' => 'Not at all', 'r4' => 'Not at all', 'r5' => 'Not at all']];
[, $plainFlags] = $call('analyseStraightline', [$gridForm, $same, ['straightline_min_items' => 5], [(int)$grid->id => true]]);
$check(array_filter($plainFlags, static fn($f) => ($f['code'] ?? '') === 'identical_grid') === [], '"Not at all" to every symptom row was flagged');
$grid->setStraightlineConfig(false, false, ['r5']);
$grid->save(false);
[, $reverseFlags] = $call('analyseStraightline', [ReviewLib::reload($gridForm), $same, ['straightline_min_items' => 5], [(int)$grid->id => true]]);
$check(array_filter($reverseFlags, static fn($f) => ($f['code'] ?? '') === 'identical_grid') !== [], 'identical answers across a grid with a reverse-keyed row were not flagged');

// Loop attention check: one failing repeat fails the check.
$module = Yii::$app->getModule('thiscovery-forms');
$module->settings->set(Module::SETTING_LOOPS, '1');
try {
    $loopForm = ReviewLib::form($space, 'EV V3-51 loop attention', ['allow_anonymous' => 0, 'allow_multiple' => 1, 'identity_mode' => CustomForm::IDENTITY_IDENTIFIED]);
    $loopForm->setSetting('loops_enabled', '1');
    $loopForm->save(false);
    ReviewLib::clearFields($loopForm);
    ReviewLib::field($loopForm, FormField::TYPE_QUESTION_GROUP, 'Each', ['variable' => 'each51', 'options' => ['loop' => [
        'source' => 'fixed', 'field_key' => '', 'max' => 0, 'min' => 0, 'randomise' => false, 'show' => null,
        'items' => [['code' => 'a', 'label' => 'A'], ['code' => 'b', 'label' => 'B']],
    ]]]);
    $att = ReviewLib::field($loopForm, FormField::TYPE_RADIO, 'Pick blue', ['variable' => 'att51', 'options' => ['blue', 'red']]);
    $att->setAttentionCheck(true, 'blue');
    $att->save(false);
    ReviewLib::field($loopForm, FormField::TYPE_GROUP_END, 'End each');
    $loopForm = ReviewLib::reload($loopForm);
    [, $flags] = $call('analyseAttention', [$loopForm, [(int)$att->id => ['a' => 'blue', 'b' => 'red']], [(int)$att->id => true]]);
    $failed = array_filter($flags, static fn($f) => ($f['code'] ?? '') === 'failed');
    $check($failed !== [], 'a loop attention check passed with one failing repeat');
} finally {
    $module->settings->set(Module::SETTING_LOOPS, '0');
}

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
