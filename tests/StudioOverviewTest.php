<?php
/**
 * Variables list and flow chart reflect the saved form, including its revision.
 * A form that has not been saved yet tells the author to save first.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\FormVersionService;
use humhub\modules\thiscoveryForms\services\LogicEngine;

$failures = [];
ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();
$form = ReviewLib::form($space, 'EV studio overview', ['allow_anonymous' => 1]);
ReviewLib::clearFields($form);
ReviewLib::field($form, FormField::TYPE_TEXT, 'Age', ['variable' => 'age', 'sort_order' => 1]);
ReviewLib::field($form, FormField::TYPE_TEXT, 'About this survey', [
    'variable' => 'intro_about',
    'sort_order' => 2,
    'logic' => LogicEngine::fromFormula('[age] = "No"'),
]);
ReviewLib::field($form, FormField::TYPE_TEXT, 'Leave', [
    'variable' => 'leave_now',
    'sort_order' => 3,
    'logic' => LogicEngine::fromFormula('[age] = "No"', LogicEngine::ACTION_GOTO_END),
]);
if (FormVersionService::isAvailable()) {
    (new FormVersionService())->recordSave(ReviewLib::reload($form));
}
$form = ReviewLib::reload($form);
$view = Yii::$app->view;
$vars = $view->renderFile(Yii::getAlias('@thiscovery-forms/views/form/_studio_variables.php'), [
    'formModel' => $form,
    'isNew' => false,
    'fieldList' => $form->getFields()->all(),
]);
foreach (['age', 'intro_about', 'About this survey', 'Variables'] as $needle) {
    if (!str_contains($vars, $needle)) {
        $failures[] = 'variable list missing ' . $needle;
    }
}
if (FormVersionService::isAvailable() && !preg_match('/Revision #(\d+)/', $vars, $m)) {
    $failures[] = 'variable list has no revision number';
}
$flow = $view->renderFile(Yii::getAlias('@thiscovery-forms/views/form/_studio_route.php'), [
    'formModel' => $form,
    'isNew' => false,
]);
foreach (['cf-flow', 'Age', 'About this survey', 'Show this question if', 'Go to end if', 'End of survey', 'If no branch matches, continue to'] as $needle) {
    if (!str_contains($flow, $needle)) {
        $failures[] = 'flow chart missing ' . $needle;
    }
}

$blank = new CustomForm($space);
$blank->kind = CustomForm::KIND_SURVEY;
$unsavedVars = $view->renderFile(Yii::getAlias('@thiscovery-forms/views/form/_studio_variables.php'), [
    'formModel' => $blank,
    'isNew' => true,
    'fieldList' => [],
]);
$unsavedFlow = $view->renderFile(Yii::getAlias('@thiscovery-forms/views/form/_studio_route.php'), [
    'formModel' => $blank,
    'isNew' => true,
]);
if (!str_contains($unsavedVars, 'Save the form first') || !str_contains($unsavedFlow, 'Save the form first')) {
    $failures[] = 'an unsaved form did not ask for a save before the variable list or flow chart';
}

try {
    $assetDir = sys_get_temp_dir() . '/cf-studio-assets';
    if (!is_dir($assetDir)) {
        mkdir($assetDir, 0777, true);
    }
    Yii::$app->assetManager->basePath = $assetDir;
    Yii::$app->assetManager->baseUrl = '/assets';
    $basics = $view->renderFile(Yii::getAlias('@thiscovery-forms/views/form/_studio_settings.php'), [
        'formModel' => $blank,
        'isNew' => true,
        'contentContainer' => $space,
        'isPoll' => false,
        'fieldList' => [],
        'emailTemplateOptions' => ['' => 'Default'],
        'enrolPanelOptions' => ['' => 'Choose a panel'],
        'activeSection' => 'basics',
    ]);
    if (!str_contains($basics, 'Start by naming the form.') || !str_contains($basics, 'data-cf-settings-pane="basics"') || !str_contains($basics, 'autofocus')) {
        $failures[] = 'a new form does not open Basics with the save-first instruction';
    }
    if (!str_contains($basics, 'data-cf-settings-pane="variables"')) {
        $failures[] = 'the variable section is missing from settings';
    }
} catch (\Throwable $e) {
    $failures[] = 'basics render failed: ' . $e->getMessage();
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
