<?php
/**
 * Start a new response, then Next, on a form that assigns a group at the start.
 * The follow-up load keeps that response and the address no longer has start=new.
 * The same continue load does not drop a response whose group is assigned on a later page.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\controllers\GlobalController;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\thiscoveryForms\services\RandomisationService;
use yii\db\Query;
use yii\web\Response;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

$followUp = Url::withoutQueryParam(
    '/thiscovery-forms/global/view?t=TOK_EN&start=new&cfcontinue=1&resume=ABCD-2345',
    'start'
);
$check(!str_contains($followUp, 'start='), 'the follow-up URL still has start');
$check(str_contains($followUp, 't=TOK_EN'), 'the follow-up URL lost the form token');
$check(str_contains($followUp, 'cfcontinue=1'), 'the follow-up URL lost cfcontinue');
$check(str_contains($followUp, 'resume=ABCD-2345'), 'the follow-up URL dropped a resume code that was still needed');
$check(
    Url::withoutQueryParam('/thiscovery-forms/global/view?t=TOK_EN', 'start') === '/thiscovery-forms/global/view?t=TOK_EN',
    'a URL without start was rewritten'
);

$js = (string)file_get_contents(dirname(__DIR__) . '/resources/js/humhub.thiscoveryForms.js');
$deleteAt = strpos($js, "nextUrl.searchParams.delete('start')");
$setAt = strpos($js, "nextUrl.searchParams.set('cfcontinue', '1')");
$check($deleteAt !== false && $setAt !== false && $deleteAt < $setAt, 'the allocation reload does not strip start before adding cfcontinue');

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV start-new allocation', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
    'allow_resume' => 1,
]);
$answerIds = (new Query())->select('id')->from('custom_form_answer')->where(['form_id' => (int)$form->id]);
(new Query())->createCommand()->delete('custom_form_arm_assignment', ['answer_id' => $answerIds])->execute();
(new Query())->createCommand()->delete('custom_form_presentation', ['answer_id' => $answerIds])->execute();
(new Query())->createCommand()->delete('custom_form_arm_allocation', ['form_id' => (int)$form->id])->execute();
(new Query())->createCommand()->delete('custom_form_answer_field', ['answer_id' => $answerIds])->execute();
(new Query())->createCommand()->delete('custom_form_answer', ['form_id' => (int)$form->id])->execute();
ReviewLib::clearFields($form);

$question = ReviewLib::field($form, FormField::TYPE_TEXT, 'Page one', [
    'variable' => 'sn_page1',
    'sort_order' => 1,
    'required' => 1,
]);
$break = ReviewLib::field($form, FormField::TYPE_PAGE_BREAK, 'Later', [
    'variable' => 'sn_later_page',
    'sort_order' => 2,
]);
$break->setPageBreakConfig(['pageKey' => 'later', 'title' => 'Later']);
$break->save(false);
ReviewLib::field($form, FormField::TYPE_TEXT, 'Page two', [
    'variable' => 'sn_page2',
    'sort_order' => 3,
]);

$rand = new RandomisationService();
$rand->saveConfig($form, [
    'enabled' => '1',
    'assign' => 'start',
    'method' => 'simple',
    'arms' => [
        ['code' => 'g01', 'name' => 'Group 1', 'weight' => 1],
        ['code' => 'g02', 'name' => 'Group 2', 'weight' => 1],
    ],
]);
$form = ReviewLib::publishOpen($form);

Yii::$app->set('response', new Response());
$controller = new GlobalController('global', Yii::$app->getModule('thiscovery-forms'));
$resolve = new ReflectionMethod($controller, 'resolveFillExisting');
$resolve->setAccessible(true);
$redirect = new ReflectionMethod($controller, 'redirectIfStartConsumed');
$redirect->setAccessible(true);
$sessionKey = 'cf_partial_answer_' . (int)$form->id;

$open = static function (array $query, string $url) use ($controller, $resolve, $redirect): array {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    Yii::$app->request->setBodyParams([]);
    Yii::$app->request->setQueryParams($query);
    Yii::$app->request->setUrl($url);
    $form = \humhub\modules\thiscoveryForms\models\CustomForm::findOne((int)$query['form_id']);
    $submit = new SubmitForm(['form' => $form]);
    $existing = $resolve->invoke($controller, $form, $submit, true);
    $response = $redirect->invoke($controller, $existing);
    return [$existing, $response, $submit];
};

Yii::$app->user->switchIdentity(null);
$_SERVER['REQUEST_METHOD'] = 'GET';
Yii::$app->request->setQueryParams(['start' => 'new', 'form_id' => (int)$form->id]);
Yii::$app->request->setBodyParams([]);
$before = (int)FormAnswer::find()->where(['form_id' => (int)$form->id])->count();
$first = ReviewLib::submit($form, [(int)$question->id => 'kept'], true);
$check($first instanceof FormAnswer && $first->isInProgress(), 'the first Next did not save a response');
$check((int)FormAnswer::find()->where(['form_id' => (int)$form->id])->count() === $before + 1, 'the first Next did not create exactly one response');
$arm = $rand->assignment($first);
$check(is_array($arm) && in_array((string)$arm['arm_code'], ['g01', 'g02'], true), 'the group was not allocated on the first Next');
Yii::$app->session->set($sessionKey, (int)$first->id);

[$kept, $response] = $open(
    ['t' => 'TOK_EN', 'start' => 'new', 'cfcontinue' => '1', 'form_id' => (int)$form->id],
    '/thiscovery-forms/global/view?t=TOK_EN&start=new&cfcontinue=1'
);
$check($kept instanceof FormAnswer && (int)$kept->id === (int)$first->id, 'start=new with cfcontinue dropped the saved response');
$check((int)FormAnswer::find()->where(['form_id' => (int)$form->id])->count() === $before + 1, 'the continue load created another response');
$keptArm = $rand->assignment($kept);
$check(is_array($keptArm) && (string)$keptArm['arm_code'] === (string)$arm['arm_code'], 'the continue load lost the allocated group');
$check($response instanceof Response, 'the continue load did not redirect off start=new');
$location = $response instanceof Response ? (string)$response->headers->get('Location') : '';
$check($location !== '' && !str_contains($location, 'start='), 'the redirect still has start: ' . $location);
$check(str_contains($location, 'cfcontinue=1') && str_contains($location, 't=TOK_EN'), 'the redirect lost the token or cfcontinue: ' . $location);

[$refreshed] = $open(
    ['t' => 'TOK_EN', 'continue' => '1', 'form_id' => (int)$form->id],
    '/thiscovery-forms/global/view?t=TOK_EN&continue=1'
);
$check($refreshed instanceof FormAnswer && (int)$refreshed->id === (int)$first->id, 'a refresh after allocation did not keep the same response');
$refreshArm = $refreshed instanceof FormAnswer ? $rand->assignment($refreshed) : null;
$check(is_array($refreshArm) && (string)$refreshArm['arm_code'] === (string)$arm['arm_code'], 'a refresh after allocation lost the group');

[$fresh] = $open(
    ['t' => 'TOK_EN', 'start' => 'new', 'form_id' => (int)$form->id],
    '/thiscovery-forms/global/view?t=TOK_EN&start=new'
);
$check($fresh === null, 'start=new loaded the earlier response');
$check((int)Yii::$app->session->get($sessionKey, 0) === 0, 'start=new kept the earlier response in this browser');
$still = FormAnswer::findOne((int)$first->id);
$check($still && $still->isInProgress(), 'start=new removed the earlier saved response');

$second = ReviewLib::submit($form, [(int)$question->id => 'other'], true);
$check($second instanceof FormAnswer && (int)$second->id !== (int)$first->id, 'the new response overwrote the earlier one');
$check((int)FormAnswer::find()->where(['form_id' => (int)$form->id])->count() === $before + 2, 'starting again did not create exactly one new response');

$rand->saveConfig($form, [
    'enabled' => '1',
    'assign' => 'after_page',
    'assign_page' => 'later',
    'method' => 'simple',
    'arms' => [
        ['code' => 'g01', 'name' => 'Group 1', 'weight' => 1],
        ['code' => 'g02', 'name' => 'Group 2', 'weight' => 1],
    ],
]);
$form->save(false);
$form = ReviewLib::reload($form);
$check($rand->config($form)['assign'] === 'after_page' && $rand->config($form)['assign_page'] === 'later', 'the later-page setting was not saved');
Yii::$app->request->setBodyParams(['current_page' => 0]);
$later = ReviewLib::submit($form, [(int)$question->id => 'waiting'], true);
$check($later instanceof FormAnswer, 'a later-page form did not save the response');
$check($rand->assignment($later) === null, 'the group was allocated before the later page');
Yii::$app->session->set($sessionKey, (int)$later->id);
[$laterKept] = $open(
    ['t' => 'TOK_EN', 'start' => 'new', 'cfcontinue' => '1', 'form_id' => (int)$form->id],
    '/thiscovery-forms/global/view?t=TOK_EN&start=new&cfcontinue=1'
);
$check($laterKept instanceof FormAnswer && (int)$laterKept->id === (int)$later->id, 'a later-page allocation reload dropped the saved response');
$check($rand->assignment($laterKept) === null, 'the continue load allocated the group before the later page');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
