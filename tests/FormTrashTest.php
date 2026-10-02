<?php
/**
 * GOV-7. Deleting a form moves it to the trash (answers kept, logged, restorable); only a
 * permanent delete from the trash removes it.
 */
require __DIR__ . '/support/bootstrap.php';
require_once dirname(__DIR__) . '/migrations/m261004_100000_form_lifecycle_log.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};
(new m261004_100000_form_lifecycle_log())->safeUp();

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV GOV-7 trash ' . uniqid(), ['allow_anonymous' => 1, 'allow_multiple' => 1]);
ReviewLib::clearFields($form);
$q = ReviewLib::field($form, FormField::TYPE_TEXT, 'Q', ['variable' => 'gov7']);
$form = ReviewLib::publishOpen($form);
$answer = ReviewLib::submit($form, [(int)$q->id => 'kept']);

$check($form->moveToTrash('Created by mistake'), 'the form did not move to the trash');
$form = ReviewLib::reload($form);
$check($form->isTrashed(), 'the form is not in the trash');
$check((int)$form->status === CustomForm::STATUS_CLOSED, 'a trashed form stayed open');
$check(!CustomForm::findLive()->andWhere(['custom_form.id' => (int)$form->id])->exists(), 'a trashed form is still listed');
$check($answer && FormAnswer::findOne((int)$answer->id) !== null, 'trashing deleted the answers');
$thrown = false;
try {
    CustomForm::assertNotTrashed($form);
} catch (\yii\web\NotFoundHttpException $e) {
    $thrown = true;
}
$check($thrown, 'a trashed form could still be opened');

$check($form->restoreFromTrash(), 'the form could not be restored');
$form = ReviewLib::reload($form);
$check(!$form->isTrashed(), 'the restored form is still in the trash');

$check(!$form->purge(), 'a form outside the trash was deleted permanently');
$form->moveToTrash();
$form = ReviewLib::reload($form);
$formId = (int)$form->id;
$check($form->purge('Test clean-up'), 'the permanent delete failed: ' . implode(' ', $form->getErrorSummary(true)));
$check(CustomForm::findOne($formId) === null, 'the purged form still exists');
$actions = array_column(CustomForm::lifecycleLog($formId), 'action');
$check($actions === ['purge', 'trash', 'restore', 'trash'], 'the lifecycle log is ' . json_encode($actions));

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
