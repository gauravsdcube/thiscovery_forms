<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormRound;
use humhub\modules\thiscoveryForms\services\RoundService;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var bool $isNew */

$rounds = $isNew ? [] : (new RoundService())->listRounds($formModel);
?>

<div class="cf-studio__settings">
    <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Rounds') ?></h5>
    <p class="cf-hint text-muted">
        <?= Yii::t('ThiscoveryFormsModule.base', 'Respondents answer the same questions each round. After a round closes, publish a summary for the next round. Only one round can be open at a time.') ?>
    </p>

    <?php if ($isNew): ?>
        <div class="alert alert-info">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Save the form first to set up rounds.') ?>
        </div>
    <?php else: ?>
        <?= Html::beginForm(Url::studioAction($formModel, 'round-delphi'), 'post', ['class' => 'cf-inline-form mb-3']) ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Delphi preset') ?></label>
                    <input type="number" name="round_count" class="form-control" value="3" min="2" max="8">
                </div>
                <div class="col-md-8">
                    <button type="submit" class="btn btn-light">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Apply Delphi (justification, freeze, N rounds)') ?>
                    </button>
                </div>
            </div>
        <?= Html::endForm() ?>

        <?= Html::beginForm(Url::studioAction($formModel, 'round-save'), 'post', ['class' => 'cf-inline-form mb-3']) ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Title') ?></label>
                    <input type="text" name="title" class="form-control" placeholder="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Round 2')) ?>">
                </div>
                <div class="col-md-6">
                    <button type="submit" class="btn btn-light"><?= Yii::t('ThiscoveryFormsModule.base', 'Add round') ?></button>
                </div>
            </div>
        <?= Html::endForm() ?>

        <?php foreach ($rounds as $round): ?>
            <?php /** @var FormRound $round */ ?>
            <div class="cf-programme-card">
                <div>
                    <strong><?= Html::encode($round->getDisplayTitle()) ?></strong>
                    <span class="cf-answer-card__chip"><?= Html::encode(FormRound::getStatusLabels()[$round->status] ?? $round->status) ?></span>
                    <?php if ($round->hasPublishedSummary()): ?>
                        <span class="cf-answer-card__chip"><?= Yii::t('ThiscoveryFormsModule.base', 'Summary published') ?></span>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if ($round->status !== FormRound::STATUS_OPEN): ?>
                        <?= Html::beginForm(Url::studioAction($formModel, 'round-status'), 'post', ['class' => 'd-inline']) ?>
                            <?= Html::hiddenInput('round_id', $round->id) ?>
                            <?= Html::hiddenInput('status', FormRound::STATUS_OPEN) ?>
                            <button type="submit" class="btn btn-sm btn-primary"><?= Yii::t('ThiscoveryFormsModule.base', 'Open') ?></button>
                        <?= Html::endForm() ?>
                    <?php endif; ?>
                    <?php if ($round->status !== FormRound::STATUS_CLOSED): ?>
                        <?= Html::beginForm(Url::studioAction($formModel, 'round-status'), 'post', ['class' => 'd-inline']) ?>
                            <?= Html::hiddenInput('round_id', $round->id) ?>
                            <?= Html::hiddenInput('status', FormRound::STATUS_CLOSED) ?>
                            <button type="submit" class="btn btn-sm btn-light"><?= Yii::t('ThiscoveryFormsModule.base', 'Close') ?></button>
                        <?= Html::endForm() ?>
                    <?php endif; ?>
                </div>
            </div>
            <?= Html::beginForm(Url::studioAction($formModel, 'round-publish'), 'post', ['class' => 'mb-4']) ?>
                <?= Html::hiddenInput('round_id', $round->id) ?>
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Summary shown at the start of the next round') ?></label>
                <textarea name="summary_html" class="form-control" rows="6"><?= Html::encode((string)$round->summary_html) ?></textarea>
                <p class="cf-hint text-muted">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Leave empty to generate a distribution and anonymised comments from this round.') ?>
                </p>
                <button type="submit" class="btn btn-light"><?= Yii::t('ThiscoveryFormsModule.base', 'Publish summary') ?></button>
            <?= Html::endForm() ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
