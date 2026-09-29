<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\services\ExportSettings;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var array $exportParams */
/** @var string $style info|primary */
/** @var bool $showIcon */
/** @var string|null $label */

$exportParams = $exportParams ?? [];
$style = $style ?? 'info';
$showIcon = $showIcon ?? false;
$scrub = ExportSettings::isPiiScrub($formModel);
$label = $label ?? ($scrub
    ? Yii::t('ThiscoveryFormsModule.base', 'Export CSV (PII scrubbed)')
    : Yii::t('ThiscoveryFormsModule.base', 'Export CSV'));
$btnClass = 'btn btn-sm ' . ($style === 'primary' ? 'btn-primary' : 'btn-info');
echo Html::beginForm(Url::toExport($formModel), 'post', ['class' => 'd-inline']);
foreach ($exportParams as $key => $value) {
    echo Html::hiddenInput((string)$key, (string)$value);
}
echo Html::submitButton($label, ['class' => $btnClass]);
echo Html::endForm();
if ($scrub) {
    $settingsUrl = Url::toEdit($formModel, ['tab' => 'settings', 'section' => 'export']);
    echo ' ' . Html::a(
        Yii::t('ThiscoveryFormsModule.base', 'Export settings'),
        $settingsUrl,
        ['class' => 'btn btn-link btn-sm']
    );
}
