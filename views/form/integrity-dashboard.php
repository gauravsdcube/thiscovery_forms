<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormIntegrityMeta;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var array $stats */
/** @var $contentContainer */
/** @var bool $canManage */

ThiscoveryFormsAsset::register($this);

$card = static function (string $label, $value, ?string $filter = null) use ($formModel) {
    $params = $filter ? ['integrity' => $filter] : [];
    $url = Url::toAnswers($formModel, $params);
    echo '<a class="cf-stat-card cf-stat-card--link" href="' . Html::encode($url) . '">';
    echo '<div class="cf-stat-value">' . Html::encode((string)$value) . '</div>';
    echo '<div class="cf-stat-label">' . Html::encode($label) . '</div>';
    echo '</a>';
};
?>
<div class="cf-dashboard cf-integrity-dash">
    <div class="cf-dash-header">
        <div>
            <div class="cf-dash-kicker"><?= Yii::t('ThiscoveryFormsModule.base', 'Response integrity') ?></div>
            <h1 class="cf-dash-title"><?= Html::encode($formModel->title) ?></h1>
        </div>
        <div class="cf-dash-actions">
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Answers'))->link(Url::toAnswers($formModel))->sm()->loader(false) ?>
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Form dashboard'))->link(Url::toDashboard($formModel))->sm()->loader(false) ?>
            <?= Button::info(Yii::t('ThiscoveryFormsModule.base', 'Export CSV'))->link(Url::toExport($formModel))->sm()->loader(false) ?>
        </div>
    </div>
    <div class="cf-stat-grid">
        <?php $card(Yii::t('ThiscoveryFormsModule.base', 'Total responses'), $stats['total'] ?? 0); ?>
        <?php $card(Yii::t('ThiscoveryFormsModule.base', 'Trusted'), $stats['byStatus'][FormIntegrityMeta::STATUS_TRUSTED] ?? 0, FormIntegrityMeta::STATUS_TRUSTED); ?>
        <?php $card(Yii::t('ThiscoveryFormsModule.base', 'Review required'), $stats['byStatus'][FormIntegrityMeta::STATUS_REVIEW] ?? 0, FormIntegrityMeta::STATUS_REVIEW); ?>
        <?php $card(Yii::t('ThiscoveryFormsModule.base', 'Suspicious'), $stats['byStatus'][FormIntegrityMeta::STATUS_SUSPICIOUS] ?? 0, FormIntegrityMeta::STATUS_SUSPICIOUS); ?>
        <?php $card(Yii::t('ThiscoveryFormsModule.base', 'Excluded'), $stats['byStatus'][FormIntegrityMeta::STATUS_EXCLUDED] ?? 0, FormIntegrityMeta::STATUS_EXCLUDED); ?>
        <div class="cf-stat-card">
            <div class="cf-stat-value"><?= Html::encode((string)($stats['averageScore'] ?? 0)) ?></div>
            <div class="cf-stat-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Average quality score') ?></div>
        </div>
    </div>
    <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Flagged for') ?></h3>
    <div class="cf-stat-grid">
        <?php
        $flagLabels = [
            'speed' => Yii::t('ThiscoveryFormsModule.base', 'Speeding'),
            'duplicate' => Yii::t('ThiscoveryFormsModule.base', 'Duplicate activity'),
            'straightline' => Yii::t('ThiscoveryFormsModule.base', 'Straight-lining'),
            'attention' => Yii::t('ThiscoveryFormsModule.base', 'Failed attention checks'),
            'consistency' => Yii::t('ThiscoveryFormsModule.base', 'Logical inconsistencies'),
            'freetext' => Yii::t('ThiscoveryFormsModule.base', 'Poor-quality free text'),
            'bot' => Yii::t('ThiscoveryFormsModule.base', 'Bot activity'),
            'similarity' => Yii::t('ThiscoveryFormsModule.base', 'Response similarity'),
        ];
        foreach ($flagLabels as $key => $label) {
            if ($key === 'bot' && empty($canManage)) {
                continue;
            }
            $card($label, $stats['flags'][$key] ?? 0, 'flag_' . $key);
        }
        ?>
    </div>
    <p class="help-block">
        <?= Yii::t('ThiscoveryFormsModule.base', 'Click a figure to open the matching responses. Statuses are screening aids, not a finding of misconduct. Excluded responses are kept and can be reinstated from the answer review.') ?>
    </p>
    <?php if (!empty($stats['clusters'])): ?>
        <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Similar response groups') ?></h3>
        <p class="help-block">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Responses linked by high similarity. Groups are screening aids — shared wording alone is not proof of collusion.') ?>
        </p>
        <div class="cf-integrity-clusters">
            <?php foreach ($stats['clusters'] as $i => $cluster): ?>
                <div class="cf-integrity-cluster">
                    <strong><?= Yii::t('ThiscoveryFormsModule.base', 'Group {n} ({count} responses)', [
                        'n' => $i + 1,
                        'count' => (int)($cluster['size'] ?? count($cluster['ids'] ?? [])),
                    ]) ?></strong>
                    <p class="mb-0">
                        <?php foreach (($cluster['ids'] ?? []) as $sid): ?>
                            <a href="<?= Html::encode(Url::toAnswers($formModel, ['answer' => $sid])) ?>">#<?= (int)$sid ?></a>
                        <?php endforeach; ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
