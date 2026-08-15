<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use humhub\modules\thiscoveryForms\models\FormWave;
use humhub\modules\thiscoveryForms\services\PanelService;
use humhub\modules\thiscoveryForms\services\WaveService;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var bool $isNew */

$panelService = new PanelService();
$waveService = new WaveService();
$panel = $isNew ? null : $panelService->getPanel($formModel);
$panels = $isNew ? [] : $panelService->listAvailable($formModel);
$members = $panel ? $panel->getActiveMembers()->all() : [];
$waves = $isNew ? [] : $waveService->listWaves($formModel);
?>

<div class="cf-studio__settings">
    <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Panel') ?></h5>
    <p class="cf-hint text-muted">
        <?= Yii::t('ThiscoveryFormsModule.base', 'Invite the same people to each wave. Members can be signed-in users or email-only guests with a personal link.') ?>
    </p>

    <?php if ($isNew): ?>
        <div class="alert alert-info">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Save the form first to set up the panel and waves.') ?>
        </div>
    <?php else: ?>
        <?= Html::beginForm(Url::studioAction($formModel, 'panel-save'), 'post', ['id' => 'cf-panel-save']) ?>
            <div class="form-group">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Use panel') ?></label>
                <select name="panel_id" class="form-control">
                    <option value="0"><?= Yii::t('ThiscoveryFormsModule.base', 'Create a new panel for this form') ?></option>
                    <?php foreach ($panels as $p): ?>
                        <option value="<?= (int)$p->id ?>" <?= $panel && (int)$panel->id === (int)$p->id ? 'selected' : '' ?>>
                            <?= Html::encode($p->title) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Panel name') ?></label>
                <input type="text" name="panel_title" class="form-control" value="<?= Html::encode($panel->title ?? $formModel->title) ?>">
            </div>
            <div class="form-group">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Description') ?></label>
                <textarea name="panel_description" class="form-control" rows="2"><?= Html::encode($panel->description ?? '') ?></textarea>
            </div>
            <div class="cf-checks mb-3">
                <label>
                    <?= Html::hiddenInput('email_on_wave_open', '0') ?>
                    <?= Html::checkbox('email_on_wave_open', $formModel->emailsOnWaveOpen(), [
                        'value' => '1',
                        'uncheck' => null,
                    ]) ?>
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Email all members when a later wave opens (Wave 2 onwards)') ?>
                </label>
            </div>
            <button type="submit" class="btn btn-primary"><?= Yii::t('ThiscoveryFormsModule.base', 'Save panel') ?></button>
        <?= Html::endForm() ?>

        <hr>
        <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Members') ?></h5>
        <?= Html::beginForm(Url::studioAction($formModel, 'panel-add-member'), 'post', ['class' => 'cf-inline-form']) ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Username or email') ?></label>
                    <input type="text" name="member" class="form-control" required placeholder="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'name@nhs.net')) ?>">
                </div>
                <div class="col-md-2">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Weight') ?></label>
                    <input type="number" name="weight" class="form-control" value="1" min="0" step="0.1">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-light"><?= Yii::t('ThiscoveryFormsModule.base', 'Add member') ?></button>
                </div>
            </div>
        <?= Html::endForm() ?>

        <?php if ($members): ?>
            <table class="table cf-panel-table mt-3">
                <thead>
                <tr>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Member') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Weight') ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($members as $member): ?>
                    <?php /** @var FormPanelMember $member */ ?>
                    <tr>
                        <td>
                            <strong><?= Html::encode($member->getDisplayLabel()) ?></strong>
                            <div class="text-muted small">
                                <?= $member->user_id
                                    ? Yii::t('ThiscoveryFormsModule.base', 'Signed-in user')
                                    : Yii::t('ThiscoveryFormsModule.base', 'Email invite') ?>
                                <?php if ($member->consent_at): ?>
                                    · <?= Yii::t('ThiscoveryFormsModule.base', 'Consent recorded') ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= Html::encode((string)$member->weight) ?></td>
                        <td class="text-end">
                            <?= Html::beginForm(Url::studioAction($formModel, 'panel-invite'), 'post', ['class' => 'd-inline']) ?>
                                <?= Html::hiddenInput('member_id', $member->id) ?>
                                <button type="submit" class="btn btn-sm btn-light"><?= Yii::t('ThiscoveryFormsModule.base', 'Email invite') ?></button>
                            <?= Html::endForm() ?>
                            <?= Html::beginForm(Url::studioAction($formModel, 'panel-remove-member'), 'post', ['class' => 'd-inline']) ?>
                                <?= Html::hiddenInput('member_id', $member->id) ?>
                                <button type="submit" class="btn btn-sm btn-danger"><?= Yii::t('ThiscoveryFormsModule.base', 'Remove') ?></button>
                            <?= Html::endForm() ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?= Html::beginForm(Url::studioAction($formModel, 'panel-invite'), 'post') ?>
                <button type="submit" class="btn btn-primary">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Email all members') ?>
                </button>
            <?= Html::endForm() ?>
        <?php else: ?>
            <p class="text-muted mt-2"><?= Yii::t('ThiscoveryFormsModule.base', 'No members yet.') ?></p>
        <?php endif; ?>

        <hr>
        <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Waves') ?></h5>
        <p class="cf-hint text-muted">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Each wave is the same form, answered again. Only one wave can be open at a time.') ?>
        </p>
        <?= Html::beginForm(Url::studioAction($formModel, 'wave-save'), 'post', ['class' => 'cf-inline-form mb-3']) ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Title') ?></label>
                    <input type="text" name="title" class="form-control" placeholder="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Wave 2')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Opens') ?></label>
                    <input type="datetime-local" name="opens_at" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Closes') ?></label>
                    <input type="datetime-local" name="closes_at" class="form-control">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-light"><?= Yii::t('ThiscoveryFormsModule.base', 'Add wave') ?></button>
                </div>
            </div>
        <?= Html::endForm() ?>

        <?php foreach ($waves as $wave): ?>
            <?php /** @var FormWave $wave */ ?>
            <div class="cf-programme-card">
                <div>
                    <strong><?= Html::encode($wave->getDisplayTitle()) ?></strong>
                    <span class="cf-answer-card__chip"><?= Html::encode(FormWave::getStatusLabels()[$wave->status] ?? $wave->status) ?></span>
                    <?php if ($wave->opens_at || $wave->closes_at): ?>
                        <div class="text-muted small">
                            <?= Html::encode(trim(($wave->opens_at ?: '—') . ' → ' . ($wave->closes_at ?: '—'))) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if ($wave->status !== FormWave::STATUS_OPEN): ?>
                        <?= Html::beginForm(Url::studioAction($formModel, 'wave-status'), 'post', ['class' => 'd-inline']) ?>
                            <?= Html::hiddenInput('wave_id', $wave->id) ?>
                            <?= Html::hiddenInput('status', FormWave::STATUS_OPEN) ?>
                            <button type="submit" class="btn btn-sm btn-primary"><?= Yii::t('ThiscoveryFormsModule.base', 'Open') ?></button>
                        <?= Html::endForm() ?>
                    <?php endif; ?>
                    <?php if ($wave->status !== FormWave::STATUS_CLOSED): ?>
                        <?= Html::beginForm(Url::studioAction($formModel, 'wave-status'), 'post', ['class' => 'd-inline']) ?>
                            <?= Html::hiddenInput('wave_id', $wave->id) ?>
                            <?= Html::hiddenInput('status', FormWave::STATUS_CLOSED) ?>
                            <button type="submit" class="btn btn-sm btn-light"><?= Yii::t('ThiscoveryFormsModule.base', 'Close') ?></button>
                        <?= Html::endForm() ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
