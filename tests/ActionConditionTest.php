<?php
/**
 * LOG-11. An action runs only when its condition holds, an invalid condition is refused at
 * save and never runs, a go-to action's condition is honoured by the route, and emails are
 * not sent from the per-field (on change) trigger. SEC-5: a guest's page-exit emails wait
 * for submission.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormEmailSend;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\FormActionService;
use humhub\modules\thiscoveryForms\services\FormPager;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV LOG11 conditions', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
ReviewLib::clearFields($form);
$q = ReviewLib::field($form, FormField::TYPE_RADIO, 'Q5', ['variable' => 'q5', 'sort_order' => 1, 'options' => ['yes | Yes', 'no | No']]);
$form = ReviewLib::reload($form);

$list = FormActionService::normalizeList([
    ['fn' => 'set_variable', 'name' => 'flag', 'value' => 'on', 'condition' => '[q5] = "yes"'],
    ['fn' => 'set_variable', 'name' => 'broken', 'value' => 'on', 'condition' => '[q5] = = "yes"'],
]);
$check(is_array($list[0]['when']), 'a valid condition was not parsed');
$check($list[1]['when'] === false, 'an invalid condition was not marked as never running');
$check(FormActionService::conditionErrors([['condition' => '[q5] = = "yes"']]) !== [], 'an invalid condition was not refused');
$check(FormActionService::conditionErrors([['condition' => '[q5] = "yes"'], ['condition' => '']]) === [], 'a valid or empty condition was refused');

$svc = new FormActionService();
$yes = $svc->run($form, $list, [(int)$q->id => 'yes'], [], null, null, null, true);
$no = $svc->run($form, $list, [(int)$q->id => 'no'], [], null, null, null, true);
$check(($yes['vars']['flag'] ?? '') === 'on', 'the action did not run when its condition held');
$check(!isset($no['vars']['flag']) || $no['vars']['flag'] === '', 'the action ran when its condition was false');
$check(!isset($yes['vars']['broken']) || $yes['vars']['broken'] === '', 'an action with an invalid condition ran');

// A go-to action with a condition only routes when it holds.
$q->setActions([['fn' => 'goto_page', 'page_key' => 'last', 'condition' => '[q5] = "yes"']]);
$q->save(false);
$b1 = ReviewLib::field($form, FormField::TYPE_PAGE_BREAK, 'B1', ['sort_order' => 2]);
$b1->setPageBreakConfig(['pageKey' => 'mid', 'title' => 'Mid']);
$b1->save(false);
ReviewLib::field($form, FormField::TYPE_TEXT, 'Mid q', ['variable' => 'midq', 'sort_order' => 3]);
$b2 = ReviewLib::field($form, FormField::TYPE_PAGE_BREAK, 'B2', ['sort_order' => 4]);
$b2->setPageBreakConfig(['pageKey' => 'last', 'title' => 'Last']);
$b2->save(false);
ReviewLib::field($form, FormField::TYPE_TEXT, 'Last q', ['variable' => 'lastq', 'sort_order' => 5]);
$form = ReviewLib::reload($form);
$fields = $form->getFields()->all();
$built = (new FormPager())->buildPages($fields);
$pager = new FormPager();
$check($pager->resolveNextPage($built['pages'], $built['pageKeyIndex'], 0, [(int)$q->id => 'yes'], $fields) === (int)$built['pageKeyIndex']['last'], 'a go-to action did not route when its condition held');
$check($pager->resolveNextPage($built['pages'], $built['pageKeyIndex'], 0, [(int)$q->id => 'no'], $fields) === (int)$built['pageKeyIndex']['mid'], 'a go-to action routed when its condition was false');

// An email action is not sent from the field trigger (side effects off).
$before = (int)FormEmailSend::find()->where(['form_id' => (int)$form->id])->count();
$svc->run($form, [['fn' => 'send_email', 'template_id' => 1]], [(int)$q->id => 'yes'], [], null, null, $q, false, false);
$after = (int)FormEmailSend::find()->where(['form_id' => (int)$form->id])->count();
$check($after === $before, 'an email action was sent from the field trigger');

// SEC-5: a guest's page-exit email is queued, not sent, until the response is submitted.
$guestRunner = new FormActionService();
$guestRunner->deferEmails = true;
$before = (int)FormEmailSend::find()->where(['form_id' => (int)$form->id])->count();
$guestRunner->run($form, [['fn' => 'send_email', 'template_id' => 1]], [(int)$q->id => 'yes'], [], null, null, $q, false, true);
$check(count($guestRunner->deferred) === 1, 'a guest page-exit email was not queued');
$check((int)FormEmailSend::find()->where(['form_id' => (int)$form->id])->count() === $before, 'a guest page-exit email was sent before submission');
FormActionService::rememberDeferred((int)$form->id, $guestRunner->deferred);
FormActionService::rememberDeferred((int)$form->id, $guestRunner->deferred);
$queued = Yii::$app->session->get('cf-deferred-emails-' . (int)$form->id, []);
$check(count($queued) === 1, 'leaving a page twice queued the email twice');
Yii::$app->session->remove('cf-deferred-emails-' . (int)$form->id);

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
