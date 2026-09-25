<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\services\ExportSettings;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var array $exportParams */
/** @var string $style info|primary */
/** @var bool $showIcon */

$exportParams = $exportParams ?? [];
$style = $style ?? 'info';
$showIcon = $showIcon ?? false;
$scrub = ExportSettings::isPiiScrub($formModel);
$label = $scrub
    ? Yii::t('ThiscoveryFormsModule.base', 'Export CSV (PII scrubbed)')
    : Yii::t('ThiscoveryFormsModule.base', 'Export CSV');
$btn = $style === 'primary' ? Button::primary($label) : Button::info($label);
$btn = $btn->link(Url::toExport($formModel, $exportParams))->sm()->loader(false);
if ($showIcon) {
    $btn = $btn->icon('download');
}
echo $btn;
if ($scrub) {
    $settingsUrl = Url::toEdit($formModel, ['tab' => 'settings', 'section' => 'export']);
    echo ' ' . Html::a(
        Yii::t('ThiscoveryFormsModule.base', 'Export settings'),
        $settingsUrl,
        ['class' => 'btn btn-link btn-sm']
    );
}
