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
            <div class="cf-check-setting">
                <label>
                    <?= Html::checkbox('ModuleSettings[display][' . $key . ']', !empty($model->display[$key]), ['value' => 1, 'uncheck' => 0]) ?>
                    <?= Html::encode(DisplaySettings::labels()[$key] ?? $key) ?>
                </label>
                <?= $this->render('@thiscovery-forms/views/form/_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Site default for whether the fill page shows this. Each form can override it under Settings → Participant display.')]) ?>
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

        <hr>
        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Consent') ?></h4>
        <p class="help-block">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Off by default. A form also has to turn consent on. Completing a form no longer records consent by itself.') ?>
        </p>
        <div class="cf-check-setting">
            <label>
                <?= Html::checkbox('ModuleSettings[econsentEnabled]', !empty($model->econsentEnabled), ['value' => 1, 'uncheck' => 0]) ?>
                <?= Html::encode($model->getAttributeLabel('econsentEnabled')) ?>
            </label>
            <?= $this->render('@thiscovery-forms/views/form/_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Lets forms ask for a published information sheet. Each form still has to turn consent on. Completing a form does not record consent by itself.')]) ?>
        </div>

        <hr>
        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Quotas') ?></h4>
        <p class="help-block">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Off by default. A form also has to turn quotas on. A full cell keeps the partial answers.') ?>
        </p>
        <div class="cf-check-setting">
            <label>
                <?= Html::checkbox('ModuleSettings[quotasEnabled]', !empty($model->quotasEnabled), ['value' => 1, 'uncheck' => 0]) ?>
                <?= Html::encode($model->getAttributeLabel('quotasEnabled')) ?>
            </label>
            <?= $this->render('@thiscovery-forms/views/form/_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Lets forms count responses against a target. Each form still has to turn quotas on. A full cell keeps the partial answers.')]) ?>
        </div>

        <hr>
        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Loops') ?></h4>
        <p class="help-block">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Off by default. A form also has to turn loops on. One repeating group can contain one other. Unselected repeats are kept but not shown.') ?>
        </p>
        <div class="cf-check-setting">
            <label>
                <?= Html::checkbox('ModuleSettings[loopsEnabled]', !empty($model->loopsEnabled), ['value' => 1, 'uncheck' => 0]) ?>
                <?= Html::encode($model->getAttributeLabel('loopsEnabled')) ?>
            </label>
            <?= $this->render('@thiscovery-forms/views/form/_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Lets a question group repeat. Each form still has to turn loops on. One repeating group can contain one other. A hidden repeat keeps its answers.')]) ?>
        </div>

        <hr>
        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Randomisation') ?></h4>
        <p class="help-block">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Off by default. A form also has to turn randomisation on before the server shuffles pages, questions, or options, or assigns an arm.') ?>
        </p>
        <div class="cf-check-setting">
            <label>
                <?= Html::checkbox('ModuleSettings[randomisationEnabled]', !empty($model->randomisationEnabled), ['value' => 1, 'uncheck' => 0]) ?>
                <?= Html::encode($model->getAttributeLabel('randomisationEnabled')) ?>
            </label>
            <?= $this->render('@thiscovery-forms/views/form/_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Lets the server shuffle order and assign arms. Each form still has to turn randomisation on. Each response gets its own option order.')]) ?>
        </div>

        <hr>
        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Create from brief / document') ?></h4>
        <p class="help-block">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Lets creators start a Draft survey from a pasted brief or a Word/PDF questionnaire. LLM assist is optional; when enabled, brief text may be sent to the configured provider. Usage is logged with estimated cost (warnings only — no hard spend caps yet).') ?>
        </p>
        <div class="cf-check-setting">
            <label>
                <?= Html::checkbox('ModuleSettings[fromBriefEnabled]', !empty($model->fromBriefEnabled), ['value' => 1, 'uncheck' => 0]) ?>
                <?= Html::encode($model->getAttributeLabel('fromBriefEnabled')) ?>
            </label>
            <?= $this->render('@thiscovery-forms/views/form/_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Shows From a brief on Create, so someone can paste a brief or upload Word or PDF and review proposed questions before a Draft survey is created.')]) ?>
        </div>
        <div class="cf-check-setting">
            <label>
                <?= Html::checkbox('ModuleSettings[fromBriefLlmEnabled]', !empty($model->fromBriefLlmEnabled), ['value' => 1, 'uncheck' => 0]) ?>
                <?= Html::encode($model->getAttributeLabel('fromBriefLlmEnabled')) ?>
            </label>
            <?= $this->render('@thiscovery-forms/views/form/_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Sends brief text to the provider below. Usage is logged with an estimated cost. There is no hard spend cap. Agree this with your organisation before turning it on.')]) ?>
        </div>
        <div class="row">
            <div class="col-md-4">
                <?= $form->field($model, 'llmProvider')->dropDownList(ModuleSettings::llmProviderLabels()) ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($model, 'llmModel')->hint(Yii::t('ThiscoveryFormsModule.base', 'Examples: gpt-4o-mini, claude-sonnet-4-5')) ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($model, 'fromBriefMaxUploadMb')->input('number', ['min' => 1, 'max' => 50]) ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                <?= $form->field($model, 'llmMaxBriefChars')->input('number', ['min' => 2000]) ?>
            </div>
            <div class="col-md-8">
                <?= $form->field($model, 'llmApiBase')->hint(Yii::t('ThiscoveryFormsModule.base', 'Leave blank for the provider default (OpenAI or Anthropic). Use a custom base for Azure or compatible gateways.')) ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-8">
                <?= $form->field($model, 'llmApiKey')->passwordInput([
                    'value' => $model->llmApiKey !== '' ? '********' : '',
                    'autocomplete' => 'new-password',
                    'placeholder' => $model->llmApiKey !== '' ? '********' : '',
                ])->hint(Yii::t('ThiscoveryFormsModule.base', 'Leave unchanged to keep the current key. Paste your OpenAI or Anthropic API key.')) ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                <?= $form->field($model, 'llmCostPer1kInput')->input('number', ['step' => '0.0001', 'min' => 0]) ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($model, 'llmCostPer1kOutput')->input('number', ['step' => '0.0001', 'min' => 0]) ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($model, 'llmWarnMonthlyCost')->input('number', ['step' => '0.01', 'min' => 0])->hint(Yii::t('ThiscoveryFormsModule.base', 'Optional. Shows a warning banner only; does not block LLM use.')) ?>
            </div>
        </div>
        <p>
            <a class="btn btn-default" href="<?= Html::encode(Url::to(['/thiscovery-forms/admin/ai-usage'])) ?>">
                <i class="fa fa-bar-chart" aria-hidden="true"></i>
                <?= Yii::t('ThiscoveryFormsModule.base', 'AI usage and estimated cost') ?>
            </a>
        </p>

        <hr>
        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Administration layout') ?></h4>
        <p class="help-block">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Off by default. When on, the Forms area opens on the full page and the administration menu on the left is hidden. This configuration page keeps that menu.') ?>
        </p>
        <div class="cf-check-setting">
            <label>
                <?= Html::checkbox('ModuleSettings[openWithoutAdminMenu]', !empty($model->openWithoutAdminMenu), ['value' => 1, 'uncheck' => 0]) ?>
                <?= Html::encode($model->getAttributeLabel('openWithoutAdminMenu')) ?>
            </label>
            <?= $this->render('@thiscovery-forms/views/form/_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'The forms list, studio, answers, panels, email templates, and help use the full page. The left administration menu is not shown. This page stays in the menu so you can turn the option off.')]) ?>
        </div>

        <hr>
        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Secure send') ?></h4>
        <p class="help-block">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Off by default. When on, form managers can prepare a file and send a one-time code to a named contact who does not need an account. Turning this off stops every existing link.') ?>
        </p>
        <div class="cf-check-setting">
            <label>
                <?= Html::checkbox('ModuleSettings[secureSendEnabled]', !empty($model->secureSendEnabled), ['value' => 1, 'uncheck' => 0]) ?>
                <?= Html::encode($model->getAttributeLabel('secureSendEnabled')) ?>
            </label>
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
