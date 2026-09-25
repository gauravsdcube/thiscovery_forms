<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var $contentContainer */
/** @var int $folderId */
/** @var bool $llmEnabled */
/** @var string|null $costWarning */
/** @var int $maxUploadMb */

ThiscoveryFormsAsset::register($this);
$folderId = (int)($folderId ?? 0);
?>

<div class="cf-create-wizard">
    <div class="cf-list-header">
        <div>
            <h1 class="cf-list-title"><?= Yii::t('ThiscoveryFormsModule.base', 'Create from brief or document') ?></h1>
            <p class="cf-list-sub">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Paste a research brief or upload a Word/PDF questionnaire. You will review the proposed questions before a Draft survey is created.') ?>
            </p>
        </div>
        <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Back'))
            ->link(Url::toCreate($contentContainer, $folderId ? ['folder' => $folderId] : []))
            ->icon('arrow-left')
            ->loader(false) ?>
    </div>

    <?php if ($costWarning): ?>
        <div class="alert alert-warning"><?= Html::encode($costWarning) ?></div>
    <?php endif; ?>

    <?php if ($llmEnabled): ?>
        <div class="alert alert-info">
            <?= Yii::t('ThiscoveryFormsModule.base', 'LLM assist is on. Brief text may be sent to the configured AI provider. Usage and estimated cost are logged. After upload you can chat to refine the brief.') ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Using rules-based mapping (LLM assist is off). An administrator can enable AI assist in module configuration.') ?>
        </div>
    <?php endif; ?>

    <?= Html::beginForm(Url::toCreateFromBrief($contentContainer, $folderId ? ['folder' => $folderId] : []), 'post', [
        'enctype' => 'multipart/form-data',
    ]) ?>
        <?php if ($folderId): ?>
            <?= Html::hiddenInput('folder', $folderId) ?>
        <?php endif; ?>

        <div class="form-group">
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Working title') ?>
                <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
            </label>
            <?= Html::textInput('title', '', ['class' => 'form-control', 'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Survey title')]) ?>
        </div>

        <div class="form-group">
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Brief or questionnaire text') ?></label>
            <?= Html::textarea('brief', '', [
                'class' => 'form-control',
                'rows' => 12,
                'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Paste section headings and numbered questions here…'),
            ]) ?>
        </div>

        <div class="form-group">
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Or upload Word / PDF') ?></label>
            <input type="file" name="document" class="form-control" accept=".docx,.pdf,.txt,.md,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain">
            <p class="help-block">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Max {n} MB. Scanned image PDFs are not supported yet.', ['n' => $maxUploadMb]) ?>
            </p>
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="fa fa-magic" aria-hidden="true"></i>
            <?= Yii::t('ThiscoveryFormsModule.base', 'Extract and propose questions') ?>
        </button>
    <?= Html::endForm() ?>
</div>
