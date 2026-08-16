<?php

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\services\FormStyleService;
use yii\helpers\Html;

/** @var CustomForm $formModel */

$groups = (new FormStyleService())->groups();
$style = is_array($formModel->style) ? $formModel->style : [];
?>

<div class="cf-studio__settings cf-studio__settings--css">
    <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Appearance') ?></h5>
    <p class="cf-hint text-muted">
        <?= Yii::t('ThiscoveryFormsModule.base', 'Leave fields blank to use the site theme. These styles apply only on the fill page.') ?>
    </p>

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
                            ?>
                            <div class="form-group mb-0">
                                <label class="cf-label" for="<?= Html::encode($id) ?>"><?= Html::encode($field['label']) ?></label>
                                <?php if (($field['type'] ?? 'text') === 'weight'): ?>
                                    <?= Html::dropDownList($name, $value, $field['options'] ?? ['' => ''], [
                                        'id' => $id,
                                        'class' => 'form-control',
                                    ]) ?>
                                <?php elseif (($field['type'] ?? 'text') === 'color'): ?>
                                    <div class="cf-style-color">
                                        <input type="color"
                                               class="cf-style-color__swatch"
                                               value="<?= Html::encode(FormStyleService::swatchHex($value)) ?>"
                                               data-cf-style-swatch
                                               aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Pick colour')) ?>">
                                        <?= Html::textInput($name, $value, [
                                            'id' => $id,
                                            'class' => 'form-control',
                                            'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Theme default'),
                                            'autocomplete' => 'off',
                                            'data-cf-style-color' => true,
                                            'spellcheck' => 'false',
                                        ]) ?>
                                        <button type="button" class="btn btn-light btn-sm" data-cf-style-clear
                                                title="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Use site theme')) ?>">
                                            <?= Yii::t('ThiscoveryFormsModule.base', 'Clear') ?>
                                        </button>
                                    </div>
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
        <?= Yii::t('ThiscoveryFormsModule.base', 'Optional extra CSS. Prefer selectors under #cf-fill (for example #cf-fill .cf-question or #cf-fill .cf-fill-hero__title).') ?>
    </p>
    <?= Html::activeTextarea($formModel, 'custom_css', [
        'class' => 'form-control cf-css-editor',
        'rows' => 12,
        'spellcheck' => 'false',
        'placeholder' => "#cf-fill .cf-fill-hero__title {\n  letter-spacing: .02em;\n}",
    ]) ?>
</div>
