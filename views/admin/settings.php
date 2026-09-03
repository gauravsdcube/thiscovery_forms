<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\ModuleSettings;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var ModuleSettings $model */

ThiscoveryFormsAsset::register($this);
$this->title = Yii::t('ThiscoveryFormsModule.base', 'Thiscovery Forms');
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('ThiscoveryFormsModule.base', '<strong>Thiscovery Forms</strong> module configuration') ?>
        <span class="pull-right">
            <a href="<?= Html::encode(Url::toHelp(null, 'admins')) ?>">
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

        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Waves') ?></h4>
        <p class="help-block">
            <?= Yii::t('ThiscoveryFormsModule.base', 'EQ-5D surveys and longitudinal surveys always use waves. Ordinary surveys only get a Panel & waves tab when the option below is on, and the survey itself has Use waves ticked.') ?>
        </p>
        <?= $form->field($model, 'wavesForSurveys')->checkbox() ?>
        <?= $form->field($model, 'waveScope')->radioList(ModuleSettings::waveScopeLabels()) ?>
        <p class="help-block">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Per survey: open and close waves on each form. Per panel: the panel has one calendar; every attached form uses the currently open wave. You still send invite emails from each form.') ?>
        </p>

        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Response integrity') ?></h4>
        <p class="help-block">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Site-wide defaults for access, CAPTCHA, and quality scoring. CAPTCHA can be turned on without enabling integrity scoring. Each survey can inherit or override these on its Response integrity tab. Cloudflare Turnstile keys are only set here.') ?>
            <a href="<?= Html::encode(Url::toHelp(null, 'creators-response-integrity')) ?>">
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
    </div>
</div>
