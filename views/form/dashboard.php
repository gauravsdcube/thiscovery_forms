<?php

use humhub\modules\thiscoveryForms\assets\ChartAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var array $stats */
/** @var $contentContainer */

ChartAsset::register($this);
$this->registerJsConfig('thiscoveryForms.dashboard', [
    'submissions' => Yii::t('ThiscoveryFormsModule.base', 'Submissions'),
    'timeline' => $stats['timeline'],
    'overview' => [],
    'structured' => $stats['structured'],
]);
$this->registerJs(<<<'JS'
(function() {
    var tryInit = function(attempt) {
        try {
            if (window.Chart && humhub && humhub.require) {
                humhub.require('thiscoveryForms.dashboard').init('#cf-dashboard');
                return;
            }
        } catch (e) {}
        if (attempt < 40) {
            setTimeout(function() { tryInit(attempt + 1); }, 50);
        }
    };
    tryInit(0);
})();
JS
, \yii\web\View::POS_READY);
?>

<div class="cf-dashboard" id="cf-dashboard">
    <div class="cf-dash-header">
        <div>
            <div class="cf-dash-kicker"><?= Yii::t('ThiscoveryFormsModule.base', 'Form dashboard') ?></div>
            <h1 class="cf-dash-title"><?= Html::encode($formModel->title) ?></h1>
        </div>
        <div class="cf-dash-actions">
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Open form'))->link(Url::toView($formModel))->sm()->loader(false) ?>
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Answers'))->link(Url::toAnswers($formModel))->sm()->loader(false) ?>
            <?= Button::info(Yii::t('ThiscoveryFormsModule.base', 'Export CSV'))->link(Url::toExport($formModel))->sm()->loader(false) ?>
        </div>
    </div>

    <div class="cf-stat-grid">
        <div class="cf-stat-card">
            <div class="cf-stat-value"><?= (int)$stats['totalAnswers'] ?></div>
            <div class="cf-stat-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Total submissions') ?></div>
        </div>
        <div class="cf-stat-card">
            <div class="cf-stat-value"><?= (int)$stats['uniqueRespondents'] ?></div>
            <div class="cf-stat-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Unique respondents') ?></div>
        </div>
        <div class="cf-stat-card">
            <div class="cf-stat-value"><?= (int)$stats['answersLast7'] ?></div>
            <div class="cf-stat-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Last 7 days') ?></div>
        </div>
        <div class="cf-stat-card">
            <div class="cf-stat-value"><?= (int)$stats['completionRate'] ?>%</div>
            <div class="cf-stat-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Avg. field completion') ?></div>
        </div>
    </div>

    <div class="cf-dash-panel">
        <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Submissions over time') ?></h3>
        <div class="cf-chart-wrap cf-chart-wrap--timeline">
            <canvas data-cf-chart="timeline"></canvas>
        </div>
    </div>

    <?php if (!empty($stats['fieldResponseRates'])): ?>
        <div class="cf-dash-panel">
            <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Field response rates') ?></h3>
            <div class="cf-rate-list">
                <?php foreach ($stats['fieldResponseRates'] as $rate): ?>
                    <div class="cf-rate-row">
                        <div class="cf-rate-label">
                            <span><?= Html::encode($rate['label']) ?></span>
                            <strong><?= (int)$rate['rate'] ?>%</strong>
                        </div>
                        <div class="cf-rate-bar">
                            <span style="width:<?= (int)$rate['rate'] ?>%"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($stats['structured'])): ?>
        <div class="cf-dash-panel">
            <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Structured question breakdown') ?></h3>
            <p class="cf-dash-help"><?= Yii::t('ThiscoveryFormsModule.base', 'Pie charts for single-choice fields; bars for multi-select checkboxes.') ?></p>
            <div class="cf-chart-grid">
                <?php foreach ($stats['structured'] as $chart): ?>
                    <div class="cf-chart-card">
                        <div class="cf-chart-card__title"><?= Html::encode($chart['label']) ?></div>
                        <div class="cf-chart-card__meta">
                            <?= Html::encode(ucfirst($chart['type'])) ?> · <?= (int)$chart['total'] ?>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'responses') ?>
                        </div>
                        <div class="cf-chart-wrap cf-chart-wrap--pie">
                            <canvas data-cf-chart="structured"></canvas>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php elseif ($stats['totalAnswers'] > 0): ?>
        <div class="cf-dash-panel">
            <p class="text-muted mb-0"><?= Yii::t('ThiscoveryFormsModule.base', 'No dropdown, radio, or checkbox fields to chart yet.') ?></p>
        </div>
    <?php else: ?>
        <div class="cf-dash-panel">
            <p class="text-muted mb-0"><?= Yii::t('ThiscoveryFormsModule.base', 'No submissions yet.') ?></p>
        </div>
    <?php endif; ?>
</div>
