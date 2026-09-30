<?php
/**
 * Page routing uses one walk. The old 80-step path is not available.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\services\FormPager;
use humhub\modules\thiscoveryForms\services\LogicEngine;

$failures = [];
$module = Yii::$app->getModule('thiscovery-forms');
$previous = (string)$module->settings->get(Module::SETTING_ROUTING_ALIGNMENT, '1');

$field = static function (int $id, string $type, array $extra = []): FormField {
    $f = new FormField();
    $f->id = $id;
    $f->type = $type;
    $f->variable = 'v' . $id;
    $f->label = 'v' . $id;
    if (!empty($extra['pageKey'])) {
        $f->setPageBreakConfig(['pageKey' => $extra['pageKey'], 'title' => $extra['pageKey']]);
    }
    if (!empty($extra['logic'])) {
        $f->setLogic($extra['logic']);
    }
    if (!empty($extra['actions'])) {
        $f->setActions($extra['actions']);
    }
    return $f;
};

$route = static function (array $fields, array $answers): array {
    return array_keys((new FormPager())->visitedFieldIds($fields, $answers));
};

$skipped = [
    $field(1, FormField::TYPE_RADIO),
    $field(2, FormField::TYPE_PAGE_BREAK, ['pageKey' => 'mid']),
    $field(3, FormField::TYPE_TEXT, ['logic' => LogicEngine::fromFormula('[v1] = "no"')]),
    $field(4, FormField::TYPE_PAGE_BREAK, ['pageKey' => 'after', 'logic' => LogicEngine::fromFormula('[v1] = "yes"', 'goto_page', 'land')]),
    $field(5, FormField::TYPE_TEXT),
    $field(6, FormField::TYPE_PAGE_BREAK, ['pageKey' => 'land']),
    $field(7, FormField::TYPE_TEXT),
];
$action = [
    $field(1, FormField::TYPE_RADIO, ['actions' => [['fn' => 'goto_page', 'page_key' => 'target']]]),
    $field(2, FormField::TYPE_PAGE_BREAK, ['pageKey' => 'mid']),
    $field(3, FormField::TYPE_TEXT),
    $field(4, FormField::TYPE_PAGE_BREAK, ['pageKey' => 'target']),
    $field(5, FormField::TYPE_TEXT),
];
$dangling = [
    $field(1, FormField::TYPE_RADIO),
    $field(2, FormField::TYPE_PAGE_BREAK, ['pageKey' => 'next', 'logic' => LogicEngine::fromFormula('[v1] = "yes"', 'goto_page', 'missing')]),
    $field(3, FormField::TYPE_TEXT),
];

try {
    $module->settings->set(Module::SETTING_ROUTING_ALIGNMENT, '1');
    $on = $route($skipped, [1 => 'yes']);
    if (!in_array(7, $on, true) || in_array(5, $on, true)) {
        $failures[] = 'flag on skipped-page goto ' . json_encode($on);
    }
    $onAction = $route($action, [1 => 'yes']);
    if (!in_array(5, $onAction, true) || in_array(3, $onAction, true)) {
        $failures[] = 'flag on action goto ' . json_encode($onAction);
    }
    $onDangle = $route($dangling, [1 => 'yes']);
    if (in_array(3, $onDangle, true)) {
        $failures[] = 'flag on unknown key did not end the form ' . json_encode($onDangle);
    }

    $module->settings->set(Module::SETTING_ROUTING_ALIGNMENT, '0');
    if (!Module::routingAligned()) {
        $failures[] = 'the old page walk can still be turned on';
    }
    $off = $route($skipped, [1 => 'yes']);
    if ($off !== $on) {
        $failures[] = 'turning the old flag off changed the route ' . json_encode($off);
    }
} finally {
    $module->settings->set(Module::SETTING_ROUTING_ALIGNMENT, $previous === '' ? '1' : $previous);
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
