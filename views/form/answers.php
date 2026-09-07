<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormIntegrityMeta;
use humhub\modules\thiscoveryForms\services\AnswerListService;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;
use yii\web\View;
use yii\widgets\LinkPager;

/** @var CustomForm $formModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $filters */
/** @var $contentContainer */
/** @var int $selectedAnswerId */

ThiscoveryFormsAsset::register($this);
$filters = array_merge([
    'q' => '',
    'status' => '',
    'integrity' => '',
    'minScore' => null,
    'pageSize' => AnswerListService::DEFAULT_PAGE_SIZE,
], $filters ?? []);
$selectedAnswerId = (int)($selectedAnswerId ?? 0);
$canManage = $formModel->canManage();
$hasFilters = $filters['q'] !== '' || $filters['status'] !== '' || $filters['integrity'] !== '' || $filters['minScore'] !== null;
$exportParams = array_filter([
    'integrity' => $filters['integrity'] !== '' ? $filters['integrity'] : null,
    'min_score' => $filters['minScore'] !== null ? $filters['minScore'] : null,
    'header_mode' => Yii::$app->request->get('header_mode') ?: null,
], static fn($v) => $v !== null && $v !== '');
$headerMode = (string)($exportParams['header_mode'] ?? \humhub\modules\thiscoveryForms\services\ExportService::HEADER_LABEL);
if (!isset(\humhub\modules\thiscoveryForms\services\ExportService::headerModeLabels()[$headerMode])) {
    $headerMode = \humhub\modules\thiscoveryForms\services\ExportService::HEADER_LABEL;
}
$exportParams['header_mode'] = $headerMode;
$sort = $dataProvider->sort;
$total = (int)$dataProvider->getTotalCount();
$clearUrl = Url::toAnswers($formModel);
$this->registerJsConfig('thiscoveryForms', [
    'loadingAnswer' => Yii::t('ThiscoveryFormsModule.base', 'Loading…'),
]);
$this->registerJs('humhub.require("thiscoveryForms").initAnswers("#cf-answers");', View::POS_READY);
if (class_exists(\humhub\modules\thiscoveryMapping\assets\MappingAsset::class)
    && \humhub\modules\thiscoveryForms\helpers\MappingAvailability::isEnabled()) {
    \humhub\modules\thiscoveryMapping\assets\MappingAsset::register($this);
}
?>

<div class="cf-answers-page" id="cf-answers"
     data-cf-open-answer="<?= $selectedAnswerId ?: '' ?>"
     data-cf-answer-detail-template="<?= Html::encode(preg_replace('/answerId=\d+/', 'answerId=__ID__', Url::toAnswerDetail($formModel, 0))) ?>">
    <div class="cf-list-header">
        <div>
            <div class="cf-dash-kicker"><?= Yii::t('ThiscoveryFormsModule.base', 'Submissions') ?></div>
            <h1 class="cf-list-title"><?= Html::encode($formModel->title) ?></h1>
            <p class="cf-list-sub">
                <?= Yii::t('ThiscoveryFormsModule.base', '{n,plural,=0{No submissions yet}=1{1 submission} other{# submissions}}', [
                    'n' => $total,
                ]) ?>
            </p>
        </div>
        <div class="cf-list-header__actions">
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Back to forms'))
                ->link(Url::toManageIndex($contentContainer))
                ->sm()
                ->icon('arrow-left')
                ->loader(false) ?>
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Open form'))
                ->link(Url::toView($formModel))
                ->pjax(!$formModel->hidesHumhubHeader())
                ->sm()
                ->loader(false) ?>
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Dashboard'))
                ->link(Url::toDashboard($formModel))
                ->sm()
                ->icon('bar-chart')
                ->loader(false) ?>
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Response integrity'))
                ->link(Url::toIntegrity($formModel))
                ->sm()
                ->icon('shield')
                ->loader(false) ?>
            <?php if ($formModel->isProject()): ?>
                <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Catalogue'))
                    ->link(Url::toCatalogue($formModel))
                    ->sm()
                    ->icon('folder-open')
                    ->loader(false) ?>
            <?php endif; ?>
            <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Export CSV'))
                ->link(Url::toExport($formModel, $exportParams))
                ->sm()
                ->icon('download')
                ->loader(false) ?>
            <div class="dropdown d-inline-block">
                <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Export headers') ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php foreach (\humhub\modules\thiscoveryForms\services\ExportService::headerModeLabels() as $mode => $label): ?>
                        <li>
                            <a class="dropdown-item<?= $headerMode === $mode ? ' active' : '' ?>"
                               href="<?= Html::encode(Url::toExport($formModel, array_merge($exportParams, ['header_mode' => $mode]))) ?>">
                                <?= Html::encode($label) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <form method="get" class="cf-list-filters" action="<?= Html::encode($clearUrl) ?>">
        <?php if (!empty(Yii::$app->request->get('sort'))): ?>
            <?= Html::hiddenInput('sort', Yii::$app->request->get('sort')) ?>
        <?php endif; ?>
        <div class="cf-list-filters__search">
            <i class="fa fa-search" aria-hidden="true"></i>
            <input type="search" name="q" value="<?= Html::encode($filters['q']) ?>"
                   placeholder="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Search answers')) ?>"
                   aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Search answers')) ?>">
        </div>
        <select name="status" aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Status')) ?>" onchange="this.form.submit()">
            <option value=""><?= Yii::t('ThiscoveryFormsModule.base', 'All statuses') ?></option>
            <option value="complete"<?= $filters['status'] === 'complete' ? ' selected' : '' ?>>
                <?= Yii::t('ThiscoveryFormsModule.base', 'Complete') ?>
            </option>
            <option value="progress"<?= $filters['status'] === 'progress' ? ' selected' : '' ?>>
                <?= Yii::t('ThiscoveryFormsModule.base', 'In progress') ?>
            </option>
        </select>
        <select name="integrity" aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Integrity')) ?>" onchange="this.form.submit()">
            <option value=""><?= Yii::t('ThiscoveryFormsModule.base', 'All quality statuses') ?></option>
            <?php
            $integrityOpts = FormIntegrityMeta::statusLabels() + [
                'quarantined' => Yii::t('ThiscoveryFormsModule.base', 'Quarantined'),
                'flag_speed' => Yii::t('ThiscoveryFormsModule.base', 'Speeding'),
                'flag_duplicate' => Yii::t('ThiscoveryFormsModule.base', 'Duplicate activity'),
                'flag_straightline' => Yii::t('ThiscoveryFormsModule.base', 'Straight-lining'),
                'flag_attention' => Yii::t('ThiscoveryFormsModule.base', 'Failed attention checks'),
                'flag_consistency' => Yii::t('ThiscoveryFormsModule.base', 'Logical inconsistencies'),
                'flag_freetext' => Yii::t('ThiscoveryFormsModule.base', 'Poor-quality free text'),
                'flag_similarity' => Yii::t('ThiscoveryFormsModule.base', 'Response similarity'),
            ];
            if ($canManage) {
                $integrityOpts['flag_bot'] = Yii::t('ThiscoveryFormsModule.base', 'Bot activity');
            }
            foreach ($integrityOpts as $val => $label): ?>
                <option value="<?= Html::encode($val) ?>"<?= $filters['integrity'] === $val ? ' selected' : '' ?>>
                    <?= Html::encode($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="min_score" aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Minimum score')) ?>" onchange="this.form.submit()">
            <option value=""><?= Yii::t('ThiscoveryFormsModule.base', 'Any quality score') ?></option>
            <?php foreach ([80, 70, 55, 40] as $min): ?>
                <option value="<?= (int)$min ?>"<?= (int)$filters['minScore'] === (int)$min ? ' selected' : '' ?>>
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Score {n}+', ['n' => $min]) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="per-page" aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Per page')) ?>" onchange="this.form.submit()">
            <?php foreach (AnswerListService::PAGE_SIZES as $size): ?>
                <option value="<?= (int)$size ?>"<?= (int)$filters['pageSize'] === (int)$size ? ' selected' : '' ?>>
                    <?= Yii::t('ThiscoveryFormsModule.base', '{n} per page', ['n' => $size]) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-default btn-sm"><?= Yii::t('ThiscoveryFormsModule.base', 'Search') ?></button>
        <?php if ($hasFilters): ?>
            <a class="btn btn-link btn-sm" href="<?= Html::encode($clearUrl) ?>"><?= Yii::t('ThiscoveryFormsModule.base', 'Clear') ?></a>
        <?php endif; ?>
        <a class="btn btn-link btn-sm" href="<?= Html::encode(Url::toExport($formModel, $exportParams + ['include_excluded' => 1])) ?>">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Export including excluded') ?>
        </a>
    </form>

    <?php if (!$dataProvider->getCount()): ?>
        <div class="cf-list-empty">
            <i class="fa fa-inbox"></i>
            <h3><?= $hasFilters
                ? Yii::t('ThiscoveryFormsModule.base', 'No matching answers.')
                : Yii::t('ThiscoveryFormsModule.base', 'No submissions yet.') ?></h3>
            <p><?= $hasFilters
                ? Yii::t('ThiscoveryFormsModule.base', 'Try a different search or clear the filters.')
                : Yii::t('ThiscoveryFormsModule.base', 'Responses will appear here once members complete this form.') ?></p>
        </div>
    <?php else: ?>
        <div class="cf-form-table-wrap">
            <table class="cf-form-table cf-answer-table">
                <thead>
                <tr>
                    <th><?= $sort->link('submitter', ['label' => Yii::t('ThiscoveryFormsModule.base', 'Respondent')]) ?></th>
                    <th><?= $sort->link('status', ['label' => Yii::t('ThiscoveryFormsModule.base', 'Status')]) ?></th>
                    <th><?= $sort->link('overall_score', ['label' => Yii::t('ThiscoveryFormsModule.base', 'Score')]) ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Integrity') ?></th>
                    <th><?= $sort->link('analysis_status', ['label' => Yii::t('ThiscoveryFormsModule.base', 'Analysis')]) ?></th>
                    <th><?= $sort->link('created_at', ['label' => Yii::t('ThiscoveryFormsModule.base', 'Date created')]) ?></th>
                    <th><?= $sort->link('updated_at', ['label' => Yii::t('ThiscoveryFormsModule.base', 'Date modified')]) ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Details') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($dataProvider->getModels() as $index => $answer): ?>
                    <?php
                    /** @var FormAnswer $answer */
                    $displayName = $answer->getSubmitterDisplayName();
                    $created = $answer->created_at ? Yii::$app->formatter->asDatetime($answer->created_at, 'short') : '';
                    $updated = $answer->updated_at ? Yii::$app->formatter->asDatetime($answer->updated_at, 'short') : '';
                    $page = $dataProvider->pagination;
                    $seq = $page ? ($page->page * $page->pageSize) + $index + 1 : $index + 1;
                    $detailUrl = Url::toAnswerDetail($formModel, $answer->id);
                    $isActive = $selectedAnswerId === (int)$answer->id;
                    ?>
                    <tr class="cf-answer-row<?= $isActive ? ' is-active' : '' ?>"
                        data-cf-answer-id="<?= (int)$answer->id ?>"
                        data-cf-answer-url="<?= Html::encode($detailUrl) ?>">
                        <td>
                            <button type="button" class="cf-answer-row__open" data-cf-answer-open
                                    data-cf-answer-id="<?= (int)$answer->id ?>"
                                    data-cf-answer-url="<?= Html::encode($detailUrl) ?>">
                                <span class="cf-answer-card__avatar" aria-hidden="true">
                                    <?= Html::encode(mb_strtoupper(mb_substr($displayName, 0, 1))) ?>
                                </span>
                                <span>
                                    <span class="cf-answer-row__name"><?= Html::encode($displayName) ?></span>
                                    <span class="cf-answer-row__meta">#<?= (int)$seq ?></span>
                                </span>
                            </button>
                        </td>
                        <td>
                            <span class="cf-form-row__chip">
                                <?= $answer->isComplete()
                                    ? Yii::t('ThiscoveryFormsModule.base', 'Complete')
                                    : Yii::t('ThiscoveryFormsModule.base', 'In progress') ?>
                            </span>
                            <?php if ($formModel->isProject()): ?>
                                <span class="cf-form-row__chip"><?= Html::encode($answer->getWorkflowLabel()) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($answer->integrityMeta): ?>
                                <?php $band = $answer->integrityMeta->getScoreBand(); ?>
                                <span class="cf-score-pill cf-score-pill--<?= Html::encode($band) ?>">
                                    <?= Html::encode(number_format((float)$answer->integrityMeta->overall_score, 0)) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($answer->integrityMeta): ?>
                                <span class="cf-form-row__chip"><?= Html::encode($answer->integrityMeta->getStatusLabel()) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($answer->integrityMeta): ?>
                                <span class="cf-form-row__chip cf-form-row__chip--analysis"><?= Html::encode($answer->integrityMeta->getAnalysisLabel()) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="cf-form-table__date-col"><?= Html::encode($created) ?></td>
                        <td class="cf-form-table__date-col"><?= Html::encode($updated) ?></td>
                        <td>
                            <?php if ($answer->wave): ?>
                                <span class="cf-form-row__chip"><?= Html::encode($answer->wave->getDisplayTitle()) ?></span>
                            <?php endif; ?>
                            <?php if ($answer->round): ?>
                                <span class="cf-form-row__chip"><?= Html::encode($answer->round->getDisplayTitle()) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="cf-list-pager">
            <?= LinkPager::widget(['pagination' => $dataProvider->pagination]) ?>
        </div>
    <?php endif; ?>

    <div class="cf-answer-overlay" data-cf-answer-overlay></div>
    <aside class="cf-answer-drawer" data-cf-answer-drawer aria-hidden="true">
        <header class="cf-answer-drawer__head">
            <h2><?= Yii::t('ThiscoveryFormsModule.base', 'Answer') ?></h2>
            <button type="button" class="cf-answer-drawer__close" data-cf-answer-close aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Close')) ?>">
                <i class="fa fa-times"></i>
            </button>
        </header>
        <div class="cf-answer-drawer__body" data-cf-answer-drawer-body></div>
    </aside>
</div>
