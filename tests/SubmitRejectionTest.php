<?php
/**
 * A submit that the server refuses stays on the form and shows the reason.
 * The message is part of the page, beside the button.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\controllers\GlobalController;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use yii\db\Query;
use yii\web\Response;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV submit rejection', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
    'allow_resume' => 0,
]);
$answerIds = (new Query())->select('id')->from('custom_form_answer')->where(['form_id' => (int)$form->id]);
(new Query())->createCommand()->delete('custom_form_answer_field', ['answer_id' => $answerIds])->execute();
(new Query())->createCommand()->delete('custom_form_answer', ['form_id' => (int)$form->id])->execute();
ReviewLib::clearFields($form);
$question = ReviewLib::field($form, FormField::TYPE_TEXT, 'Colour of the door', [
    'variable' => 'door_colour',
    'sort_order' => 1,
    'required' => 1,
]);
$form = ReviewLib::publishOpen($form);

Yii::$app->set('response', new Response());
Yii::$app->assetManager->bundles = false;
Yii::$app->user->switchIdentity(null);
$_SERVER['REQUEST_METHOD'] = 'POST';
Yii::$app->request->setQueryParams(['form_id' => (int)$form->id]);
Yii::$app->request->setBodyParams(['values' => []]);
Yii::$app->request->setUrl('/thiscovery-forms/global/view?form_id=' . (int)$form->id);

$controller = new GlobalController('global', Yii::$app->getModule('thiscovery-forms'));
$controller->layout = false;
$form = CustomForm::findOne((int)$form->id);
$submit = new SubmitForm(['form' => $form]);
$handle = new ReflectionMethod($controller, 'handleSubmit');
$handle->setAccessible(true);
$html = $handle->invoke($controller, $form, $submit, null);

$check(is_string($html), 'the refused submit did not return the form page');
$html = is_string($html) ? $html : '';
$check(str_contains($html, 'data-cf-page-errors'), 'the form page has no place for the submit error');
$check((bool)preg_match('/<div class="cf-fill-errors" data-cf-page-errors/', $html), 'the submit error is hidden');
$check(str_contains($html, 'Colour of the door'), 'the submit error does not name the question');
$check(str_contains($html, 'is required'), 'the submit error does not say the question is required');
$check(!str_contains($html, 'cf-thankyou'), 'a refused submit showed the thank-you page');
$check(
    (int)FormAnswer::find()->where(['form_id' => (int)$form->id, 'status' => FormAnswer::STATUS_COMPLETE])->count() === 0,
    'a refused submit stored a completed response'
);

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
