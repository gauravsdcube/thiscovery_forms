<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormWave;
use humhub\modules\thiscoveryForms\services\PanelService;
use humhub\modules\thiscoveryForms\services\WaveService;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var bool $isNew */

$panelService = new PanelService();
$waveService = new WaveService();
$panel = $isNew ? null : $panelService->getPanel($formModel);
$panels = $isNew ? [] : $panelService->listAvailable($formModel);
$memberCount = $panel ? $panel->getActiveMemberCount() : 0;
$waves = $isNew ? [] : $waveService->listWaves($formModel);
?>

<div class="cf-studio__settings">
    <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Panel') ?></h5>
    <p class="cf-hint text-muted">
        <?= Yii::t('ThiscoveryFormsModule.base', 'Attach a panel for invites, waves, and this survey’s cohort. When someone completes the form with an email (from a question or their account), they are added to this panel.') ?>
    </p>

    <?php if ($isNew): ?>
        <div class="alert alert-info">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Save the form first to attach a panel and set up waves.') ?>
        </div>
    <?php else: ?>
        <?= Html::beginForm(Url::studioAction($formModel, 'panel-save'), 'post', ['id' => 'cf-panel-save']) ?>
            <div class="form-group">
                <div class="cf-label-row">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Use panel') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Create a panel for this form, or attach one that already exists. Completers with an email are added to it.')]) ?>
                </div>
                <select name="panel_id" class="form-control">
                    <option value="0"><?= Yii::t('ThiscoveryFormsModule.base', 'Create a new panel for this form') ?></option>
                    <?php foreach ($panels as $p): ?>
                        <option value="<?= (int)$p->id ?>" <?= $panel && (int)$panel->id === (int)$p->id ? 'selected' : '' ?>>
                            <?= Html::encode($p->title) ?>
                            (<?= (int)$p->getActiveMemberCount() ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <div class="cf-label-row">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Panel name') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Name shown on the Panels screen. People do not see this while they fill the form.')]) ?>
                </div>
                <input type="text" name="panel_title" class="form-control" value="<?= Html::encode($panel->title ?? $formModel->title) ?>">
            </div>
            <div class="form-group">
                <div class="cf-label-row">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Description') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'A note for managers about who this panel is for. It is not shown to respondents.')]) ?>
                </div>
                <textarea name="panel_description" class="form-control" rows="2"><?= Html::encode($panel->description ?? '') ?></textarea>
            </div>
            <div class="cf-check-setting mb-3">
                <label>
                    <?= Html::hiddenInput('email_on_wave_open', '0') ?>
                    <?= Html::checkbox('email_on_wave_open', $formModel->emailsOnWaveOpen(), [
                        'value' => '1',
                        'uncheck' => null,
                    ]) ?>
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Email all members when a later wave opens (Wave 2 onwards)') ?>
                </label>
                <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Sends the wave email when wave 2 or later opens. Wave 1 uses the invite. Choose the template under Settings → Email templates.')]) ?>
            </div>
            <p class="cf-hint text-muted">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Choose invite, wave, reminder, and post-completion templates on the Settings tab.') ?>
                <a href="<?= Html::encode(Url::toEmailTemplateIndex($formModel->isGlobal() ? null : $formModel->content->getContainer())) ?>">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Open email templates') ?>
                </a>
            </p>
            <button type="submit" class="btn btn-primary"><?= Yii::t('ThiscoveryFormsModule.base', 'Save panel') ?></button>
        <?= Html::endForm() ?>

        <p class="mt-3">
            <?php if ($panel): ?>
                <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Manage members ({n})', ['n' => $memberCount]))
                    ->link(Url::toPanelView($panel))
                    ->icon('users')
                    ->loader(false) ?>
            <?php else: ?>
                <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Open panels'))
                    ->link(Url::toPanelIndex($formModel->isGlobal() ? null : $formModel->content->getContainer()))
                    ->icon('users')
                    ->loader(false) ?>
            <?php endif; ?>
        </p>

        <?php if ($panel && $memberCount): ?>
            <?= Html::beginForm(Url::studioAction($formModel, 'panel-invite'), 'post', ['class' => 'mt-2']) ?>
                <button type="submit" class="btn btn-primary">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Email all members') ?>
                </button>
            <?= Html::endForm() ?>
        <?php elseif ($panel): ?>
            <p class="text-muted mt-2"><?= Yii::t('ThiscoveryFormsModule.base', 'No members yet. Add people on the panel screen, or enrol them when they complete a form.') ?></p>
        <?php endif; ?>

        <hr>
        <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Waves') ?></h5>
        <?php if ($formModel->wavesLiveOnPanel()): ?>
            <p class="cf-hint text-muted">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Waves are managed on the panel, so every form that uses this panel shares the same calendar.') ?>
            </p>
            <?php if ($panel): ?>
                <p>
                    <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Open panel waves'))
                        ->link(Url::toPanelView($panel))
                        ->icon('line-chart')
                        ->loader(false) ?>
                </p>
                <?= $this->render('@thiscovery-forms/views/form/_wave_list', [
                    'waves' => $waves,
                    'statusUrl' => Url::toPanelWaveStatus($panel),
                    'canManage' => true,
                ]) ?>
            <?php else: ?>
                <p class="text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'Save the panel first, then add waves on the panel screen.') ?></p>
            <?php endif; ?>
        <?php else: ?>
        <p class="cf-hint text-muted">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Each wave is the same form, answered again. Only one wave can be open at a time.') ?>
        </p>
        <?= Html::beginForm(Url::studioAction($formModel, 'wave-save'), 'post', ['class' => 'cf-inline-form mb-3']) ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <div class="cf-label-row">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Title') ?></label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Name of this wave, for example Wave 2. Respondents see it where the form shows the wave title.')]) ?>
                    </div>
                    <input type="text" name="title" class="form-control" placeholder="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Wave 2')) ?>">
                </div>
                <div class="col-md-3">
                    <div class="cf-label-row">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Opens') ?></label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'When this wave can be filled. Only one wave can be open at a time.')]) ?>
                    </div>
                    <input type="datetime-local" name="opens_at" class="form-control">
                </div>
                <div class="col-md-3">
                    <div class="cf-label-row">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Closes') ?></label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'When filling this wave stops. Leave empty if you will close it yourself.')]) ?>
                    </div>
                    <input type="datetime-local" name="closes_at" class="form-control">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-light"><?= Yii::t('ThiscoveryFormsModule.base', 'Add wave') ?></button>
                </div>
            </div>
        <?= Html::endForm() ?>
        <?= $this->render('@thiscovery-forms/views/form/_wave_list', [
            'waves' => $waves,
            'statusUrl' => Url::studioAction($formModel, 'wave-status'),
            'canManage' => true,
        ]) ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
