<?php

use humhub\modules\thiscoveryForms\assets\ChartAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var array $stats */
/** @var $contentContainer */
/** @var bool $isPublic */

$isPublic = !empty($isPublic);

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
            <?php if (empty($isPublic)): ?>
                <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Back to forms'))
                    ->link(Url::toManageIndex($contentContainer ?? null))
                    ->sm()
                    ->icon('arrow-left')
                    ->loader(false) ?>
                <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Open form'))->link(Url::toView($formModel))->pjax(!$formModel->hidesHumhubHeader())->sm()->loader(false) ?>
                <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Answers'))->link(Url::toAnswers($formModel))->sm()->loader(false) ?>
                <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Response integrity'))->link(Url::toIntegrity($formModel))->sm()->icon('shield')->loader(false) ?>
                <?= $this->render('_export_csv_button', [
                    'formModel' => $formModel,
                    'exportParams' => [],
                    'style' => 'info',
                    'showIcon' => false,
                ]) ?>
                <?= $this->render('_export_csv_button', [
                    'formModel' => $formModel,
                    'exportParams' => ['codebook' => 1],
                    'style' => 'info',
                    'showIcon' => false,
                    'label' => Yii::t('ThiscoveryFormsModule.base', 'Codebook'),
                ]) ?>
                <?= $this->render('_export_csv_button', [
                    'formModel' => $formModel,
                    'exportParams' => ['allocation' => 1],
                    'style' => 'info',
                    'showIcon' => false,
                    'label' => Yii::t('ThiscoveryFormsModule.base', 'Allocation log'),
                ]) ?>
            <?php else: ?>
                <span class="text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'Shared dashboard') ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="cf-stat-grid">
        <div class="cf-stat-card">
            <div class="cf-stat-value"><?= (int)$stats['totalAnswers'] ?></div>
            <div class="cf-stat-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Total submissions') ?></div>
            <?php if (!empty($stats['excludedFromAnalysis'])): ?>
                <div class="cf-stat-hint text-muted">
                    <?= Yii::t('ThiscoveryFormsModule.base', '{n,plural,=1{1 excluded from analysis} other{# excluded from analysis}}', [
                        'n' => (int)$stats['excludedFromAnalysis'],
                    ]) ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="cf-stat-card">
            <div class="cf-stat-value"><?= (int)($stats['inProgress'] ?? 0) ?></div>
            <div class="cf-stat-label"><?= Yii::t('ThiscoveryFormsModule.base', 'In progress') ?></div>
        </div>
        <?php if (!empty($stats['showUniqueRespondents'])): ?>
        <div class="cf-stat-card">
            <div class="cf-stat-value"><?= (int)$stats['uniqueRespondents'] ?></div>
            <div class="cf-stat-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Unique respondents') ?></div>
        </div>
        <?php else: ?>
        <div class="cf-stat-card">
            <div class="cf-stat-value">—</div>
            <div class="cf-stat-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Unique respondents not available') ?></div>
        </div>
        <?php endif; ?>
        <div class="cf-stat-card">
            <div class="cf-stat-value"><?= (int)$stats['answersLast7'] ?></div>
            <div class="cf-stat-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Last 7 days') ?></div>
        </div>
        <div class="cf-stat-card">
            <div class="cf-stat-value"><?= (int)$stats['completionRate'] ?>%</div>
            <div class="cf-stat-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Avg. field completion') ?></div>
        </div>
    </div>

    <?php if (!empty($stats['arms'])): ?>
        <div class="cf-dash-panel">
            <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Arms') ?></h3>
            <table class="table">
                <thead>
                <tr>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Arm') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Stratum') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Assigned') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Completed') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($stats['arms'] as $arm): ?>
                    <tr>
                        <td><?= Html::encode($arm['arm_name'] !== '' ? $arm['arm_name'] : $arm['arm_code']) ?></td>
                        <td><?= Html::encode((string)$arm['stratum_key']) ?></td>
                        <td><?= (int)$arm['assigned'] ?></td>
                        <td><?= (int)$arm['completed'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($stats['quotas'])): ?>
        <div class="cf-dash-panel">
            <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Quotas') ?></h3>
            <table class="table">
                <thead>
                <tr>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Quota') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Target') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Accepted') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Reserved') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Remaining') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Fill') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Status') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($stats['quotas'] as $quota): ?>
                    <tr>
                        <td><?= Html::encode((string)$quota['name']) ?></td>
                        <td><?= (int)$quota['target'] ?></td>
                        <td><?= (int)$quota['accepted'] ?></td>
                        <td><?= (int)$quota['reserved'] ?></td>
                        <td><?= (int)$quota['remaining'] ?></td>
                        <td><?= (int)$quota['fill_percent'] ?>%</td>
                        <td><?= Html::encode((string)$quota['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($stats['waves'])): ?>
        <div class="cf-dash-panel">
            <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Waves') ?></h3>
            <table class="table">
                <thead>
                <tr>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Wave') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Completed') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Panel') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Completion') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Drop-off vs previous') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($stats['waves'] as $wave): ?>
                    <tr>
                        <td><?= Html::encode($wave['title']) ?></td>
                        <td><?= (int)$wave['completed'] ?></td>
                        <td><?= (int)$wave['memberCount'] ?></td>
                        <td><?= (int)$wave['rate'] ?>%</td>
                        <td><?= $wave['dropOff'] === null ? '—' : ((int)$wave['dropOff'] . '%') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($stats['rounds'])): ?>
        <div class="cf-dash-panel">
            <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Rounds') ?></h3>
            <table class="table">
                <thead>
                <tr>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Round') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Responses') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Summary') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($stats['rounds'] as $round): ?>
                    <tr>
                        <td><?= Html::encode($round['title']) ?></td>
                        <td><?= (int)$round['completed'] ?></td>
                        <td><?= !empty($round['published'])
                            ? Yii::t('ThiscoveryFormsModule.base', 'Published')
                            : Yii::t('ThiscoveryFormsModule.base', 'Not published') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="cf-dash-panel">
        <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Submissions over time') ?></h3>
        <?php if (!empty($stats['excludedFromAnalysis'])): ?>
            <p class="text-muted small">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Charts and totals omit responses marked Excluded from analysis. Open Response integrity or Answers to review them.') ?>
            </p>
        <?php endif; ?>
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
            <p class="cf-dash-help"><?= Yii::t('ThiscoveryFormsModule.base', 'Charts for choice, grid, ranking, MaxDiff, drill-down, and image-area questions.') ?></p>
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
            <p class="text-muted mb-0"><?= Yii::t('ThiscoveryFormsModule.base', 'No structured questions to chart yet.') ?></p>
        </div>
    <?php else: ?>
        <div class="cf-dash-panel">
            <p class="text-muted mb-0"><?= Yii::t('ThiscoveryFormsModule.base', 'No submissions yet.') ?></p>
        </div>
    <?php endif; ?>
</div>
