<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\models\FormTheme;
use humhub\modules\thiscoveryForms\services\FormStyleService;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var FormTheme $theme */
/** @var array $groups */
/** @var array $style */

ThiscoveryFormsAsset::register($this);
$this->title = $theme->isNewRecord
    ? Yii::t('ThiscoveryFormsModule.base', 'New theme')
    : Yii::t('ThiscoveryFormsModule.base', 'Edit theme');
$this->registerJs('humhub.require("thiscoveryForms").initStyleColors("#cf-theme-edit");', \yii\web\View::POS_READY);
$this->registerJs('humhub.require("thiscoveryForms").initGuides("#cf-theme-edit");', \yii\web\View::POS_READY);
?>

<div class="panel panel-default" id="cf-theme-edit">
    <div class="panel-heading">
        <?= Html::encode($this->title) ?>
        <span class="pull-right">
            <a href="<?= Html::encode(Url::to(['/thiscovery-forms/admin/settings'])) ?>">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Back to module settings') ?>
            </a>
        </span>
    </div>
    <div class="panel-body">
        <?= Html::beginForm('', 'post', ['class' => 'cf-theme-form']) ?>
            <div class="form-group">
                <label class="control-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Theme name') ?></label>
                <?= Html::textInput('name', $theme->name, ['class' => 'form-control', 'required' => true]) ?>
            </div>
            <div class="checkbox">
                <label>
                    <?= Html::checkbox('is_default', !empty($theme->is_default), ['value' => 1]) ?>
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Make this the default theme') ?>
                </label>
            </div>

            <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Appearance') ?></h4>
            <p class="help-block">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Leave fields blank to use the site HumHub theme. Forms can still override individual values.') ?>
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
                                    $name = 'style[' . $group['id'] . '][' . $field['name'] . ']';
                                    $id = 'cf-theme-' . $group['id'] . '-' . $field['name'];
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
                                            <?= $this->render('@thiscovery-forms/views/form/_style_color_field', [
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

            <h4 class="mt-4"><?= Yii::t('ThiscoveryFormsModule.base', 'Custom CSS') ?></h4>
            <?= Html::textarea('custom_css', $theme->custom_css, [
                'class' => 'form-control cf-css-editor',
                'rows' => 10,
                'spellcheck' => 'false',
            ]) ?>

            <div class="mt-3">
                <?= Html::submitButton(Yii::t('ThiscoveryFormsModule.base', 'Save theme'), ['class' => 'btn btn-primary']) ?>
                <a class="btn btn-default" href="<?= Html::encode(Url::to(['/thiscovery-forms/admin/settings'])) ?>">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Cancel') ?>
                </a>
            </div>
        <?= Html::endForm() ?>
    </div>
</div>
