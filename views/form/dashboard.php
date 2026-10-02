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
            if (humhub && humhub.require) {
                var dashboard = humhub.require('thiscoveryForms.dashboard');
                if (dashboard.paintLoops) {
                    dashboard.paintLoops('#cf-dashboard');
                }
                if (window.Chart) {
                    dashboard.init('#cf-dashboard');
                    return;
                }
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
                <?php if (\humhub\modules\thiscoveryForms\services\LoopService::active($formModel)): ?>
                    <?= $this->render('_export_csv_button', [
                        'formModel' => $formModel,
                        'exportParams' => ['long' => 1],
                        'style' => 'info',
                        'showIcon' => false,
                        'label' => Yii::t('ThiscoveryFormsModule.base', 'Loops and rosters (one row per repeat)'),
                    ]) ?>
                <?php endif; ?>
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

    <?php if (empty($isPublic) && !empty($stats['loops']['questions'])): ?>
        <div class="cf-dash-panel" data-cf-loop-split>
            <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Repeats') ?></h3>
            <p class="text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'All repeats are added together. Choose one repeat to see only that label.') ?></p>
            <?php $loopKey = trim((string)Yii::$app->request->get('loop_instance', '*')); ?>
            <?php if ($loopKey === '') { $loopKey = '*'; } ?>
            <form method="get" class="form-inline">
                <input type="hidden" name="id" value="<?= (int)$formModel->id ?>">
                <div class="form-group">
                    <label for="cf-loop-instance"><?= Yii::t('ThiscoveryFormsModule.base', 'Show') ?></label>
                    <select id="cf-loop-instance" class="form-control" name="loop_instance" data-cf-loop-instance style="max-width: 16rem;">
                        <option value="*"<?= $loopKey === '*' ? ' selected' : '' ?>><?= Yii::t('ThiscoveryFormsModule.base', 'All repeats') ?></option>
                        <?php foreach ($stats['loops']['instances'] as $instance): ?>
                            <option value="<?= Html::encode((string)$instance['code']) ?>"<?= $loopKey === (string)$instance['code'] ? ' selected' : '' ?>><?= Html::encode((string)$instance['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-default" data-cf-loop-show><?= Yii::t('ThiscoveryFormsModule.base', 'Show') ?></button>
            </form>
            <table class="table">
                <thead>
                <tr>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Question') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Answers') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Values') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($stats['loops']['questions'] as $question): ?>
                    <tr data-cf-loop-row
                        data-cf-loop-counts="<?= Html::encode(json_encode($question['counts'], JSON_UNESCAPED_UNICODE)) ?>"
                        data-cf-loop-values="<?= Html::encode(json_encode($question['values'], JSON_UNESCAPED_UNICODE)) ?>">
                        <td><?= Html::encode((string)$question['label']) ?></td>
                        <td data-cf-loop-count><?= (int)($question['counts'][$loopKey] ?? 0) ?></td>
                        <td data-cf-loop-values-cell><?= Html::encode(implode(', ', array_map(
                            static fn($name, $n) => $name . ' (' . $n . ')',
                            array_keys($question['values'][$loopKey] ?? []),
                            array_values($question['values'][$loopKey] ?? [])
                        ))) ?></td>
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
                            <?php if (!empty($chart['multi'])): ?>
                                · <?= Yii::t('ThiscoveryFormsModule.base', 'people could choose more than one, so percentages can add up to more than 100%') ?>
                            <?php endif; ?>
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
    <?php if (!empty($stats['numeric'])): ?>
        <div class="cf-dash-panel">
            <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Number questions') ?></h3>
            <table class="table table-condensed">
                <thead><tr>
                    <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Question') ?></th>
                    <th scope="col">n</th>
                    <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Mean') ?></th>
                    <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Median') ?></th>
                    <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Quartiles') ?></th>
                    <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Range') ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($stats['numeric'] as $row): ?>
                    <tr>
                        <th scope="row"><?= Html::encode((string)$row['label']) ?></th>
                        <td><?= (int)$row['n'] ?></td>
                        <td><?= Html::encode((string)$row['mean']) ?></td>
                        <td><?= Html::encode((string)$row['median']) ?></td>
                        <td><?= Html::encode($row['p25'] . ' – ' . $row['p75']) ?></td>
                        <td><?= Html::encode($row['min'] . ' – ' . $row['max']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
