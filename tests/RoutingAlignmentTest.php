<?php
/**
 * NEW-8. routing_alignment off restores the 1.28.2 page walk.
 * On, the 1.28.3 route is unchanged.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\services\FormPager;

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
    $field(3, FormField::TYPE_TEXT, ['logic' => ['action' => 'show', 'combinator' => 'and', 'rules' => [['fieldKey' => '1', 'operator' => 'equals', 'value' => 'no']]]]),
    $field(4, FormField::TYPE_PAGE_BREAK, ['pageKey' => 'after', 'logic' => ['action' => 'goto_page', 'combinator' => 'and', 'gotoPageKey' => 'land', 'rules' => [['fieldKey' => '1', 'operator' => 'equals', 'value' => 'yes']]]]),
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
    $field(2, FormField::TYPE_PAGE_BREAK, ['pageKey' => 'next', 'logic' => ['action' => 'goto_page', 'combinator' => 'and', 'gotoPageKey' => 'missing', 'rules' => [['fieldKey' => '1', 'operator' => 'equals', 'value' => 'yes']]]]),
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
    if (Module::routingAligned()) {
        $failures[] = 'flag did not turn off';
    }
    $off = $route($skipped, [1 => 'yes']);
    if (!in_array(5, $off, true)) {
        $failures[] = 'flag off dropped the page the old walk still visits ' . json_encode($off);
    }
    $offAction = $route($action, [1 => 'yes']);
    if (!in_array(3, $offAction, true)) {
        $failures[] = 'flag off still applied the field action ' . json_encode($offAction);
    }
    $offDangle = $route($dangling, [1 => 'yes']);
    if (!in_array(3, $offDangle, true)) {
        $failures[] = 'flag off unknown key ended the form ' . json_encode($offDangle);
    }
} finally {
    $module->settings->set(Module::SETTING_ROUTING_ALIGNMENT, $previous === '' ? '1' : $previous);
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
