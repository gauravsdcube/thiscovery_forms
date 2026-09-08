<?php

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormTheme;
use humhub\modules\thiscoveryForms\services\FormStyleService;
use yii\helpers\Html;

/** @var CustomForm $formModel */

$groups = (new FormStyleService())->groups();
$style = is_array($formModel->style) ? $formModel->style : [];
$themeOptions = FormTheme::dropdownOptions(true);
$defaultTheme = FormTheme::findDefault();
$defaultStyleJson = $defaultTheme
    ? json_encode($defaultTheme->getStyle(), JSON_UNESCAPED_UNICODE)
    : '{}';
$defaultCss = $defaultTheme ? (string)$defaultTheme->custom_css : '';
?>

<div class="cf-studio__settings cf-studio__settings--css">
    <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Appearance') ?></h5>
    <p class="cf-hint text-muted">
        <?= Yii::t('ThiscoveryFormsModule.base', 'Pick a shared theme, or Custom to detach and keep local styles only. Values you set below override the selected theme for this form.') ?>
    </p>

    <div class="form-group cf-field">
        <label class="cf-label" for="cf-theme-id"><?= Yii::t('ThiscoveryFormsModule.base', 'Theme') ?></label>
        <?= Html::activeDropDownList($formModel, 'theme_id', $themeOptions, [
            'id' => 'cf-theme-id',
            'class' => 'form-control',
            'data-cf-theme-select' => true,
            'data-cf-default-theme-id' => $defaultTheme ? (string)$defaultTheme->id : '',
            'data-cf-default-theme-style' => $defaultStyleJson,
            'data-cf-default-theme-css' => $defaultCss,
        ]) ?>
    </div>

    <div class="cf-style-accs">
        <?php foreach ($groups as $group): ?>
            <?php $values = is_array($style[$group['id']] ?? null) ? $style[$group['id']] : []; ?>
            <details class="cf-style-acc">
                <summary><?= Html::encode($group['label']) ?></summary>
                <div class="cf-style-acc__body">
                    <div class="cf-style-grid">
                        <?php foreach ($group['fields'] as $field): ?>
                            <?php
                            $name = 'CustomForm[style][' . $group['id'] . '][' . $field['name'] . ']';
                            $id = 'cf-style-' . $group['id'] . '-' . $field['name'];
                            $value = (string)($values[$field['name']] ?? '');
                            $type = (string)($field['type'] ?? 'text');
                            $itemClass = 'form-group mb-0' . ($type === 'color' ? ' cf-style-grid__item--color' : '');
                            ?>
                            <div class="<?= $itemClass ?>">
                                <label class="cf-label" for="<?= Html::encode($id) ?>"><?= Html::encode($field['label']) ?></label>
                                <?php if ($type === 'weight'): ?>
                                    <?= Html::dropDownList($name, $value, $field['options'] ?? ['' => ''], [
                                        'id' => $id,
                                        'class' => 'form-control',
                                    ]) ?>
                                <?php elseif ($type === 'color'): ?>
                                    <?= $this->render('_style_color_field', [
                                        'name' => $name,
                                        'id' => $id,
                                        'value' => $value,
                                    ]) ?>
                                <?php else: ?>
                                    <?= Html::textInput($name, $value, [
                                        'id' => $id,
                                        'class' => 'form-control',
                                        'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Theme default'),
                                        'autocomplete' => 'off',
                                    ]) ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>
    </div>

    <h5 class="cf-section__title mt-4"><?= Yii::t('ThiscoveryFormsModule.base', 'Custom CSS') ?></h5>
    <p class="cf-hint text-muted">
        <?= Yii::t('ThiscoveryFormsModule.base', 'Optional extra CSS for this form only. Prefer selectors under #cf-fill.') ?>
    </p>
    <?= Html::activeTextarea($formModel, 'custom_css', [
        'class' => 'form-control cf-css-editor',
        'rows' => 12,
        'spellcheck' => 'false',
        'placeholder' => "#cf-fill .cf-fill-hero__title {\n  letter-spacing: .02em;\n}",
    ]) ?>
</div>
