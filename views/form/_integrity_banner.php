<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormIntegrityMeta;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var FormAnswer $answer */
/** @var bool $canManage */
/** @var bool $canDecideAnalysis */

$canManage = !empty($canManage);
$canDecideAnalysis = !empty($canDecideAnalysis) || $canManage || $formModel->canDecideAnalysis();
$meta = $answer->integrityMeta;
$band = $meta ? $meta->getScoreBand() : 'review';
$labels = FormIntegrityMeta::componentLabels();
?>
<div class="cf-integrity-banner cf-integrity-banner--<?= Html::encode($band) ?>">
    <?php if (!$meta): ?>
        <p class="help-block mb-0"><?= Yii::t('ThiscoveryFormsModule.base', 'No quality score yet. Complete responses are scored when Response integrity is on.') ?></p>
    <?php else: ?>
        <div class="cf-integrity-banner__top">
            <div class="cf-integrity-banner__score" title="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Quality score')) ?>">
                <span class="cf-integrity-banner__num"><?= Html::encode(number_format((float)$meta->overall_score, 0)) ?></span>
                <span class="cf-integrity-banner__out">/ 100</span>
            </div>
            <div class="cf-integrity-banner__meta">
                <div class="cf-integrity-banner__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Response integrity') ?></div>
                <div class="cf-integrity-banner__chips">
                    <span class="cf-form-row__chip"><?= Html::encode($meta->getStatusLabel()) ?></span>
                    <span class="cf-form-row__chip cf-form-row__chip--analysis"><?= Html::encode($meta->getAnalysisLabel()) ?></span>
                    <?php if ($meta->status_override): ?>
                        <span class="cf-form-row__chip"><?= Yii::t('ThiscoveryFormsModule.base', 'Manually overridden') ?></span>
                    <?php endif; ?>
                </div>
                <p class="help-block mb-0"><?= Yii::t('ThiscoveryFormsModule.base', 'Screening aid only — not a finding of misconduct. Use Include in analysis when you have reviewed this response.') ?></p>
            </div>
        </div>
        <?php
        $components = [];
        foreach ($meta->getComponentScoresForViewer($canManage) as $key => $score) {
            if ((float)$score > 0) {
                $components[] = ($labels[$key] ?? $key) . ' ' . number_format((float)$score, 0);
            }
        }
        $flagMessages = [];
        foreach ($meta->getFlagsForViewer($canManage) as $flag) {
            if (($flag['category'] ?? '') === 'attention' && ($flag['code'] ?? '') !== 'failed') {
                continue;
            }
            $msg = trim((string)($flag['message'] ?? ''));
            if ($msg !== '') {
                $flagMessages[] = $msg;
            }
        }
        ?>
        <?php if ($components): ?>
            <div class="cf-integrity-banner__components">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Penalty') ?>:
                <?= Html::encode(implode(' · ', $components)) ?>
            </div>
        <?php endif; ?>
        <?php if ($flagMessages): ?>
            <ul class="cf-integrity-banner__flags">
                <?php foreach (array_slice($flagMessages, 0, 6) as $msg): ?>
                    <li><?= Html::encode($msg) ?></li>
                <?php endforeach; ?>
                <?php if (count($flagMessages) > 6): ?>
                    <li><?= Yii::t('ThiscoveryFormsModule.base', 'Further flags are listed below the answers.') ?></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
        <?php if ($canDecideAnalysis): ?>
            <?= Html::beginForm(Url::toIntegrityStatus($formModel, (int)$answer->id), 'post', ['class' => 'cf-integrity-banner__form']) ?>
                <?php if ($canManage): ?>
                    <div class="cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Integrity status') ?></label>
                        <?= Html::dropDownList('integrity_status', $meta->getEffectiveStatus(), FormIntegrityMeta::statusLabels(), ['class' => 'form-control']) ?>
                    </div>
                <?php else: ?>
                    <?= Html::hiddenInput('integrity_status', $meta->getEffectiveStatus()) ?>
                <?php endif; ?>
                <div class="cf-field">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Include in analysis') ?></label>
                    <?= Html::dropDownList('analysis_status', $meta->analysis_status, FormIntegrityMeta::analysisDecisionLabels(), ['class' => 'form-control']) ?>
                </div>
                <div class="cf-field cf-integrity-banner__reason">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Reason') ?></label>
                    <?= Html::textInput('reason', $meta->exclusion_reason, [
                        'class' => 'form-control',
                        'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Required if you exclude this response'),
                    ]) ?>
                </div>
                <div class="cf-integrity-banner__save">
                    <button class="btn btn-sm btn-primary" type="submit"><?= Yii::t('ThiscoveryFormsModule.base', 'Save decision') ?></button>
                </div>
            <?= Html::endForm() ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
