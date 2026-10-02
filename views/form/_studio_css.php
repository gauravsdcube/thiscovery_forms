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
    <h3 class="cf-settings-pane__title"><?= Yii::t('ThiscoveryFormsModule.base', 'CSS') ?></h3>
    <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Appearance') ?></h5>
    <p class="cf-hint text-muted">
        <?= Yii::t('ThiscoveryFormsModule.base', 'Pick a shared theme, or Custom to detach and keep local styles only. Values you set below override the selected theme for this form.') ?>
    </p>

    <div class="form-group cf-field">
        <label class="cf-label" for="cf-theme-id"><?= Yii::t('ThiscoveryFormsModule.base', 'Theme') ?></label>
        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'A shared theme from Administration, or Custom to keep styles on this form only. Values you set below override the theme for this form.')]) ?>
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
                                <div class="cf-label-row">
                                    <label class="cf-label" for="<?= Html::encode($id) ?>"><?= Html::encode($field['label']) ?></label>
                                    <?= $this->render('_setting_guide', ['text' => $type === 'color'
                                        ? Yii::t('ThiscoveryFormsModule.base', 'Colour for this part of the fill page. Leave blank to keep the theme. Transparency is allowed.')
                                        : ($type === 'weight'
                                            ? Yii::t('ThiscoveryFormsModule.base', 'Font weight for this part of the fill page. Leave on the theme default unless this form should differ.')
                                            : Yii::t('ThiscoveryFormsModule.base', 'Size or spacing for this part of the fill page. Leave blank to keep the theme.'))]) ?>
                                </div>
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

    <details class="cf-set-acc">
        <summary>
            <span class="cf-set-acc__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Custom CSS') ?></span>
            <span class="cf-set-acc__summary"><?= Yii::t('ThiscoveryFormsModule.base', 'Optional extra CSS for this form only.') ?></span>
        </summary>
        <div class="cf-set-acc__body">
            <div class="cf-label-row">
                <p class="cf-hint text-muted mb-0">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Prefer selectors under #cf-fill.') ?>
                </p>
                <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Optional CSS for this form only. It does not change the studio, the forms list, or dashboards. Prefer selectors under #cf-fill.')]) ?>
            </div>
            <?= Html::activeTextarea($formModel, 'custom_css', [
                'class' => 'form-control cf-css-editor',
                'rows' => 12,
                'spellcheck' => 'false',
                'placeholder' => "#cf-fill .cf-fill-hero__title {\n  letter-spacing: .02em;\n}",
            ]) ?>
        </div>
    </details>
</div>
