<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url as FormsUrl;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormTheme;
use humhub\modules\thiscoveryForms\models\ModuleSettings;
use humhub\modules\thiscoveryForms\services\DisplaySettings;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var ModuleSettings $model */
/** @var FormTheme[] $themes */

ThiscoveryFormsAsset::register($this);
$this->title = Yii::t('ThiscoveryFormsModule.base', 'Thiscovery Forms');
$themes = $themes ?? FormTheme::find()->orderBy(['is_default' => SORT_DESC, 'name' => SORT_ASC])->all();
?>

<div class="panel panel-default" id="cf-admin-settings">
    <div class="panel-heading">
        <?= Yii::t('ThiscoveryFormsModule.base', '<strong>Thiscovery Forms</strong> module configuration') ?>
        <span class="pull-right">
            <a href="<?= Html::encode(FormsUrl::toHelp(null, 'admins')) ?>">
                <i class="fa fa-question-circle" aria-hidden="true"></i>
                <?= Yii::t('ThiscoveryFormsModule.base', 'Help') ?>
            </a>
        </span>
    </div>
    <div class="panel-body">
        <?php $form = ActiveForm::begin(); ?>

        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Form types') ?></h4>
        <p class="help-block">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Choose which form types people can create. Existing forms of a disabled type stay available; new ones cannot be created.') ?>
        </p>
        <?= $form->field($model, 'enabledKinds')->checkboxList(CustomForm::getKindLabels(), [
            'itemOptions' => ['labelOptions' => ['class' => 'checkbox']],
            'separator' => '',
        ])->label(false) ?>

        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Fill page display') ?></h4>
        <p class="help-block">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Site defaults for what participants see. Each form can inherit or override these on its Settings tab.') ?>
        </p>
        <?php foreach (DisplaySettings::KEYS as $key): ?>
            <div class="checkbox">
                <label>
                    <?= Html::checkbox('ModuleSettings[display][' . $key . ']', !empty($model->display[$key]), ['value' => 1, 'uncheck' => 0]) ?>
                    <?= Html::encode(DisplaySettings::labels()[$key] ?? $key) ?>
                </label>
            </div>
        <?php endforeach; ?>

        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Response integrity') ?></h4>
        <p class="help-block">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Site-wide defaults for access, CAPTCHA, and quality scoring. CAPTCHA can be turned on without enabling integrity scoring. Each survey can inherit or override these on its Response integrity tab. Cloudflare Turnstile keys are only set here.') ?>
            <a href="<?= Html::encode(FormsUrl::toHelp(null, 'creators-response-integrity')) ?>">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Response integrity help') ?>
            </a>
        </p>
        <div data-cf-integrity-settings>
        <?= $this->render('@thiscovery-forms/views/form/_integrity_settings_fields', [
            'namePrefix' => 'integrity',
            'values' => $integrity ?? \humhub\modules\thiscoveryForms\services\integrity\IntegritySettings::global(),
            'defaults' => \humhub\modules\thiscoveryForms\services\integrity\IntegritySettings::defaults(),
            'allowInherit' => false,
        ]) ?>
        </div>

        <?= Html::submitButton(Yii::t('ThiscoveryFormsModule.base', 'Save'), ['class' => 'btn btn-primary']) ?>
        <?php ActiveForm::end(); ?>

        <hr>
        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Appearance themes') ?></h4>
        <p class="help-block">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Named themes can be applied on any form. Updating a theme updates every form that uses it (form-level overrides still win).') ?>
        </p>
        <p>
            <a class="btn btn-default" href="<?= Html::encode(Url::to(['/thiscovery-forms/admin/theme-edit'])) ?>">
                <i class="fa fa-plus" aria-hidden="true"></i>
                <?= Yii::t('ThiscoveryFormsModule.base', 'New theme') ?>
            </a>
            <a class="btn btn-default" href="<?= Html::encode(Url::to(['/thiscovery-forms/admin/theme-import'])) ?>">
                <i class="fa fa-upload" aria-hidden="true"></i>
                <?= Yii::t('ThiscoveryFormsModule.base', 'Import theme') ?>
            </a>
        </p>
        <?php if (!$themes): ?>
            <p class="text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'No themes yet.') ?></p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Name') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Default') ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($themes as $theme): ?>
                    <tr>
                        <td><?= Html::encode($theme->name) ?></td>
                        <td><?= $theme->is_default ? Yii::t('ThiscoveryFormsModule.base', 'Yes') : '' ?></td>
                        <td class="text-right">
                            <a href="<?= Html::encode(Url::to(['/thiscovery-forms/admin/theme-edit', 'id' => $theme->id])) ?>">
                                <?= Yii::t('ThiscoveryFormsModule.base', 'Edit') ?>
                            </a>
                            ·
                            <a href="<?= Html::encode(Url::to(['/thiscovery-forms/admin/theme-export', 'id' => $theme->id])) ?>">
                                <?= Yii::t('ThiscoveryFormsModule.base', 'Export') ?>
                            </a>
                            <?php if (!$theme->is_default): ?>
                                ·
                                <?= Html::beginForm(Url::to(['/thiscovery-forms/admin/theme-delete', 'id' => $theme->id]), 'post', ['style' => 'display:inline']) ?>
                                    <?= Html::submitButton(Yii::t('ThiscoveryFormsModule.base', 'Delete'), [
                                        'class' => 'btn btn-link btn-sm text-danger',
                                        'onclick' => 'return confirm(' . json_encode(Yii::t('ThiscoveryFormsModule.base', 'Delete this theme?')) . ');',
                                    ]) ?>
                                <?= Html::endForm() ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
