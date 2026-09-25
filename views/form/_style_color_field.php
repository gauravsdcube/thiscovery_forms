<?php

use humhub\modules\thiscoveryForms\services\FormStyleService;
use yii\helpers\Html;

/**
 * Compact colour field with picker panel (hue, opacity, transparent).
 *
 * @var string $name
 * @var string $id
 * @var string $value
 * @var string|null $placeholder
 */

$placeholder = $placeholder ?? Yii::t('ThiscoveryFormsModule.base', 'Theme default');
$value = (string)$value;
$swatch = FormStyleService::swatchHex($value);
$isTransparent = strcasecmp(trim($value), 'transparent') === 0;
$previewStyle = $isTransparent
    ? 'background-image: linear-gradient(45deg, #cbd5e1 25%, transparent 25%), linear-gradient(-45deg, #cbd5e1 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #cbd5e1 75%), linear-gradient(-45deg, transparent 75%, #cbd5e1 75%); background-size: 10px 10px; background-position: 0 0, 0 5px, 5px -5px, -5px 0; background-color: #fff;'
    : ('background-color: ' . Html::encode($value !== '' ? $value : '#ffffff') . ';');
?>
<div class="cf-style-color" data-cf-style-color-wrap>
    <button type="button"
            class="cf-style-color__trigger"
            data-cf-style-picker-toggle
            aria-expanded="false"
            aria-haspopup="dialog"
            title="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Open colour picker')) ?>">
        <span class="cf-style-color__preview" data-cf-style-preview style="<?= $previewStyle ?>"></span>
    </button>
    <?= Html::textInput($name, $value, [
        'id' => $id,
        'class' => 'form-control',
        'placeholder' => $placeholder,
        'autocomplete' => 'off',
        'data-cf-style-color' => true,
        'spellcheck' => 'false',
    ]) ?>
    <button type="button"
            class="btn btn-light btn-sm cf-style-color__clear"
            data-cf-style-clear
            title="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Use theme / site default')) ?>">
        <?= Yii::t('ThiscoveryFormsModule.base', 'Clear') ?>
    </button>

    <div class="cf-style-color__panel" data-cf-style-picker-panel hidden>
        <div class="cf-style-color__panel-title"><?= Yii::t('ThiscoveryFormsModule.base', 'Colour') ?></div>
        <div class="cf-style-color__panel-row">
            <label class="cf-style-color__swatch-wrap">
                <span class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Hue') ?></span>
                <input type="color"
                       class="cf-style-color__swatch"
                       value="<?= Html::encode($swatch) ?>"
                       data-cf-style-swatch
                       <?= $isTransparent ? 'disabled' : '' ?>
                       aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Pick colour')) ?>">
            </label>
            <label class="cf-style-color__alpha-wrap">
                <span class="cf-label">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Opacity') ?>
                    <span data-cf-style-alpha-label>100%</span>
                </span>
                <input type="range"
                       class="cf-style-color__alpha"
                       min="0"
                       max="100"
                       step="1"
                       value="100"
                       data-cf-style-alpha-slider
                       <?= $isTransparent ? 'disabled' : '' ?>
                       aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Opacity')) ?>">
            </label>
        </div>
        <label class="cf-style-color__transparent">
            <input type="checkbox"
                   value="1"
                   data-cf-style-transparent
                   <?= $isTransparent ? 'checked' : '' ?>>
            <?= Yii::t('ThiscoveryFormsModule.base', 'Transparent') ?>
        </label>
        <p class="cf-hint text-muted mb-0">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Opacity writes an rgba() value. You can also type hex, rgb, or rgba in the field.') ?>
        </p>
    </div>
</div>
