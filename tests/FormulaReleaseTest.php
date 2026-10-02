<?php
/**
 * Formula release work that was not an original finding: label to code,
 * anonymous publish, limits, and the quota editor.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\formula\Context;
use humhub\modules\thiscoveryForms\services\formula\Evaluator;
use humhub\modules\thiscoveryForms\services\formula\FormulaException;
use humhub\modules\thiscoveryForms\services\formula\FormulaPolicy;
use humhub\modules\thiscoveryForms\services\formula\Limits;
use humhub\modules\thiscoveryForms\services\formula\Parser;
use humhub\modules\thiscoveryForms\services\LogicEngine;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();

$form = ReviewLib::form($space, 'EV formula label to code', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
ReviewLib::clearFields($form);
$saved = $form->saveFieldsFromPost([
    'mood' => [
        'type' => FormField::TYPE_RADIO,
        'label' => 'Mood',
        'variable' => 'mood',
        'sort_order' => 1,
        'options' => "y | Yes\nn | No",
    ],
    'gate' => [
        'type' => FormField::TYPE_TEXT,
        'label' => 'Follow up',
        'variable' => 'follow',
        'sort_order' => 2,
        'logic_formula' => '[mood] = "Yes"',
    ],
]);
$check($saved === true, 'a known label was rejected');
$gate = FormField::find()->where(['form_id' => $form->id, 'variable' => 'follow'])->one();
$check($gate && str_contains((string)$gate->getLogic()['text'], '"y"'), 'the label was not rewritten to the code');
$notice = implode(' ', (array)Yii::$app->session->getFlash('info', []));
$check(str_contains($notice, 'Yes'), 'the save did not say the label was rewritten');

$rejected = $form->saveFieldsFromPost([
    'mood' => [
        'id' => FormField::find()->where(['form_id' => $form->id, 'variable' => 'mood'])->select('id')->scalar(),
        'type' => FormField::TYPE_RADIO,
        'label' => 'Mood',
        'variable' => 'mood',
        'sort_order' => 1,
        'options' => "y | Yes\nn | No",
    ],
    'gate' => [
        'id' => $gate ? $gate->id : null,
        'type' => FormField::TYPE_TEXT,
        'label' => 'Follow up',
        'variable' => 'follow',
        'sort_order' => 2,
        'logic_formula' => '[mood] = "Maybe"',
    ],
]);
$check($rejected === false, 'an unknown label was saved');
$check(str_contains((string)Yii::$app->session->getFlash('error'), 'not an option'), 'the rejection did not name the option');

$anon = ReviewLib::form($space, 'EV formula anonymous panel', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
    'identity_mode' => CustomForm::IDENTITY_FULLY_ANONYMOUS,
]);
ReviewLib::clearFields($anon);
$panel = ReviewLib::field($anon, FormField::TYPE_TEXT, 'Site rule', ['variable' => 'site_rule']);
$panel->setLogic(LogicEngine::fromFormula('[panel:site] = "north"'));
$panel->save(false);
$panelErrors = FormulaPolicy::authoringErrors(ReviewLib::reload($anon));
$check((bool)array_filter($panelErrors, static fn($message) => str_contains($message, 'panel')), 'a fully anonymous form was allowed a panel formula');
$anon->status = CustomForm::STATUS_OPEN;
$check(!$anon->save(), 'a fully anonymous form with a panel formula was opened');

$named = ReviewLib::form($space, 'EV formula meta block', ['allow_anonymous' => 0, 'allow_multiple' => 1]);
ReviewLib::clearFields($named);
$ip = ReviewLib::field($named, FormField::TYPE_TEXT, 'Address rule', ['variable' => 'address_rule']);
$ip->setLogic(LogicEngine::fromFormula('[meta:ip] = "203.0.113.5"'));
$ip->save(false);
$ipErrors = FormulaPolicy::authoringErrors(ReviewLib::reload($named));
$check((bool)array_filter($ipErrors, static fn($message) => str_contains($message, 'meta:ip')), 'meta:ip was allowed');

$language = ReviewLib::form($space, 'EV formula meta language', ['allow_anonymous' => 0, 'allow_multiple' => 1]);
ReviewLib::clearFields($language);
$lang = ReviewLib::field($language, FormField::TYPE_TEXT, 'Language rule', ['variable' => 'language_rule']);
$lang->setLogic(LogicEngine::fromFormula('[meta:language] = "en"'));
$lang->save(false);
$check(FormulaPolicy::authoringErrors(ReviewLib::reload($language)) === [], 'language was blocked');

$deep = '1';
for ($i = 0; $i < 33; $i++) {
    $deep = 'if(' . $deep . ', 1, 1)';
}
$depthFailed = false;
try {
    (new Parser())->parse($deep);
} catch (FormulaException $e) {
    $depthFailed = str_contains($e->getMessage(), '32');
}
$check($depthFailed, 'a formula deeper than 32 levels was accepted');
$lengthFailed = false;
try {
    (new Parser())->parse(str_repeat('1', 4001));
} catch (FormulaException $e) {
    $lengthFailed = str_contains($e->getMessage(), '4000');
}
$check($lengthFailed, 'a formula longer than 4000 characters was accepted');
$context = new Context();
$context->steps = Limits::STEPS;
$step = (new Evaluator($context))->evaluate(['op' => 'lit', 'lit' => 'number', 'v' => '1']);
$check($step->isEmpty(), 'the step limit did not stop evaluation');

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
ob_start();
$formModel = $form;
$quota = null;
$quotas = [];
include dirname(__DIR__) . '/views/quota/edit.php';
$quotaHtml = (string)ob_get_clean();
$check(str_contains($quotaHtml, '[arm] = "pictogram"'), 'the quota editor does not describe a formula');
$check(!str_contains($quotaHtml, 'fieldKey'), 'the quota editor still describes the old rule');

if ($failures) {
    echo count($failures) . " failed\n";
    exit(1);
}
echo "OK\n";
