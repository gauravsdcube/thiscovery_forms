<?php

use humhub\modules\thiscoveryForms\assets\ChartAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var array $stats */
/** @var $contentContainer */
/** @var bool $canCreate */

ChartAsset::register($this);
$this->registerJsConfig('thiscoveryForms.dashboard', [
    'submissions' => Yii::t('ThiscoveryFormsModule.base', 'Submissions'),
    'timeline' => $stats['timeline'],
    'overview' => array_slice($stats['perForm'], 0, 8),
    'structured' => [],
]);
$this->registerJs(<<<'JS'
(function() {
    var tryInit = function(attempt) {
        try {
            if (window.Chart && humhub && humhub.require) {
                humhub.require('thiscoveryForms.dashboard').init('#cf-overview');
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

$statusLabels = CustomForm::getStatusLabels();
?>

<div class="cf-dashboard" id="cf-overview">
    <div class="cf-dash-header">
        <div>
            <div class="cf-dash-kicker"><?= Yii::t('ThiscoveryFormsModule.base', 'Forms overview') ?></div>
            <h1 class="cf-dash-title"><?= Yii::t('ThiscoveryFormsModule.base', 'Dashboard') ?></h1>
        </div>
        <div class="cf-dash-actions">
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'All forms'))->link(Url::toManageIndex($contentContainer))->sm()->loader(false) ?>
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Help'))->link(Url::toHelp($contentContainer))->sm()->icon('question-circle')->loader(false) ?>
            <?php if ($canCreate): ?>
                <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Create form'))->link(Url::toCreate($contentContainer))->sm()->loader(false) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="cf-stat-grid">
        <div class="cf-stat-card">
            <div class="cf-stat-value"><?= (int)$stats['totalForms'] ?></div>
            <div class="cf-stat-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Forms') ?></div>
        </div>
        <div class="cf-stat-card">
            <div class="cf-stat-value"><?= (int)$stats['openForms'] ?></div>
            <div class="cf-stat-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Open forms') ?></div>
        </div>
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
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="cf-dash-panel">
                <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Submissions over time') ?></h3>
                <div class="cf-chart-wrap cf-chart-wrap--timeline">
                    <canvas data-cf-chart="timeline"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="cf-dash-panel">
                <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Submissions by form') ?></h3>
                <?php if (!empty($stats['perForm'])): ?>
                    <div class="cf-chart-wrap cf-chart-wrap--overview">
                        <canvas data-cf-chart="overview"></canvas>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0"><?= Yii::t('ThiscoveryFormsModule.base', 'No forms yet.') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="cf-dash-panel">
        <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Forms') ?></h3>
        <?php if (empty($stats['perForm'])): ?>
            <p class="text-muted mb-0"><?= Yii::t('ThiscoveryFormsModule.base', 'No forms yet.') ?></p>
        <?php else: ?>
            <div class="cf-table-wrap">
                <table class="table cf-table">
                    <thead>
                    <tr>
                        <th><?= Yii::t('ThiscoveryFormsModule.base', 'Title') ?></th>
                        <th><?= Yii::t('ThiscoveryFormsModule.base', 'Status') ?></th>
                        <th><?= Yii::t('ThiscoveryFormsModule.base', 'Submissions') ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($stats['perForm'] as $row): ?>
                        <tr>
                            <td><?= Html::encode($row['title']) ?></td>
                            <td><?= Html::encode($statusLabels[$row['status']] ?? '') ?></td>
                            <td><strong><?= (int)$row['answers'] ?></strong></td>
                            <td class="text-end">
                                <?= Html::a(Yii::t('ThiscoveryFormsModule.base', 'Dashboard'), $row['dashboardUrl'], ['class' => 'btn btn-sm btn-light']) ?>
                                <?= Html::a(Yii::t('ThiscoveryFormsModule.base', 'Open form'), $row['url'], ['class' => 'btn btn-sm btn-light']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
