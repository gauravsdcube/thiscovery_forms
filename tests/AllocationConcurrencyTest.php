<?php
/**
 * V3-6. Parallel starts on a block-randomised form: no rollbacks, every slot used once,
 * and the arms stay exactly balanced (lock-then-read allocation).
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\services\RandomisationService;
use yii\db\Query;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

$module = Yii::$app->getModule('thiscovery-forms');
ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV V3-6 allocation race', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
$answers = (new Query())->select('id')->from('custom_form_answer')->where(['form_id' => (int)$form->id]);
(new Query())->createCommand()->delete('custom_form_arm_assignment', ['answer_id' => $answers])->execute();
(new Query())->createCommand()->delete('custom_form_presentation', ['answer_id' => $answers])->execute();
(new Query())->createCommand()->delete('custom_form_arm_allocation', ['form_id' => (int)$form->id])->execute();
(new Query())->createCommand()->delete('custom_form_answer', ['form_id' => (int)$form->id])->execute();

(new RandomisationService())->saveConfig($form, [
    'enabled' => '1',
    'method' => 'block',
    'block_size' => 4,
    'arms' => "usual|Usual|1\nnew|New|1",
    'assign' => 'start',
]);
$form->save(false);
$module->settings->set(Module::SETTING_RANDOMISATION, '1');

$workers = 10;
$perWorker = 8;
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/support/arm_worker.php') . ' ' . (int)$form->id . ' ' . $perWorker;
$procs = [];
for ($i = 0; $i < $workers; $i++) {
    $pipes = [];
    $procs[] = [proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['THISCOVERY_FORMS_TEST_DB' => '1']), $pipes];
}
$errors = 0;
foreach ($procs as [$proc, $pipes]) {
    $err = trim(str_replace(
        'Cannot load Zend OPcache - it was already loaded',
        '',
        (string)stream_get_contents($pipes[2])
    ));
    if (proc_close($proc) !== 0 || $err !== '') {
        $errors++;
    }
}
$check($errors === 0, "{$errors} worker(s) failed or rolled back");

$counts = (new Query())
    ->select(['n' => 'COUNT(*)'])
    ->from(['g' => 'custom_form_arm_assignment'])
    ->innerJoin(['a' => 'custom_form_answer'], 'a.id = g.answer_id')
    ->where(['a.form_id' => (int)$form->id])
    ->groupBy('g.arm_code')
    ->indexBy('arm_code')
    ->column();
$total = array_sum(array_map('intval', $counts));
$check($total === $workers * $perWorker, "expected " . ($workers * $perWorker) . " assignments, got {$total}");
$check((int)($counts['usual'] ?? 0) === (int)($counts['new'] ?? 0), 'arms are not balanced: ' . json_encode($counts));

$module->settings->set(Module::SETTING_RANDOMISATION, '0');
if ($failures) {
    exit(1);
}
echo "PASS\n";
