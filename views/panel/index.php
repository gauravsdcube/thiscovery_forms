<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\FormPanel;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;
use yii\widgets\LinkPager;

/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $filters */
/** @var $contentContainer */

ThiscoveryFormsAsset::register($this);
$filters = array_merge(['q' => ''], $filters ?? []);
$hasFilters = $filters['q'] !== '';
$total = (int)$dataProvider->getTotalCount();
$sort = $dataProvider->sort;
$indexUrl = Url::toPanelIndex($contentContainer);
?>

<div class="cf-list-page">
    <div class="cf-list-header">
        <div>
            <div class="cf-dash-kicker"><?= Yii::t('ThiscoveryFormsModule.base', 'Thiscovery Forms') ?></div>
            <h1 class="cf-list-title"><?= Yii::t('ThiscoveryFormsModule.base', 'Panels') ?></h1>
            <p class="cf-list-sub">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Groups of people who can take part in forms. One person can belong to several panels.') ?>
            </p>
        </div>
        <div class="cf-list-header__actions">
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Back to forms'))
                ->link(Url::toManageIndex($contentContainer))
                ->sm()
                ->icon('arrow-left')
                ->loader(false) ?>
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Help'))
                ->link(Url::toHelp($contentContainer, 'creators-panels'))
                ->sm()
                ->icon('question-circle')
                ->loader(false) ?>
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Email templates'))
                ->link(Url::toEmailTemplateIndex($contentContainer))
                ->sm()
                ->icon('envelope')
                ->loader(false) ?>
            <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Create panel'))
                ->link(Url::toPanelEdit($contentContainer))
                ->sm()
                ->icon('plus')
                ->loader(false) ?>
        </div>
    </div>

    <form method="get" action="<?= Html::encode($indexUrl) ?>" class="cf-list-filters">
        <div class="cf-list-filters__search">
            <i class="fa fa-search" aria-hidden="true"></i>
            <input type="search" name="q" value="<?= Html::encode($filters['q']) ?>"
                   placeholder="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Search panels')) ?>"
                   aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Search panels')) ?>">
        </div>
        <button type="submit" class="btn btn-default btn-sm"><?= Yii::t('ThiscoveryFormsModule.base', 'Search') ?></button>
        <?php if ($hasFilters): ?>
            <a class="btn btn-link btn-sm" href="<?= Html::encode($indexUrl) ?>"><?= Yii::t('ThiscoveryFormsModule.base', 'Clear') ?></a>
        <?php endif; ?>
    </form>

    <?php if ($total === 0): ?>
        <div class="cf-list-empty">
            <i class="fa fa-users" aria-hidden="true"></i>
            <h3><?= $hasFilters
                ? Yii::t('ThiscoveryFormsModule.base', 'No matching panels.')
                : Yii::t('ThiscoveryFormsModule.base', 'No panels yet') ?></h3>
            <p><?= $hasFilters
                ? Yii::t('ThiscoveryFormsModule.base', 'Try a different search or clear the filters.')
                : Yii::t('ThiscoveryFormsModule.base', 'Create a panel, then add people by email, from this site, by CSV, or when they complete a form.') ?></p>
            <?php if (!$hasFilters): ?>
                <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Create panel'))
                    ->link(Url::toPanelEdit($contentContainer))
                    ->loader(false) ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="cf-form-table-wrap">
            <table class="cf-form-table">
                <thead>
                <tr>
                    <th class="cf-form-table__actions-col"><?= Yii::t('ThiscoveryFormsModule.base', 'Actions') ?></th>
                    <th><?= $sort->link('title', ['label' => Yii::t('ThiscoveryFormsModule.base', 'Panel')]) ?></th>
                    <th class="cf-form-table__num-col"><?= Yii::t('ThiscoveryFormsModule.base', 'Members') ?></th>
                    <th class="cf-form-table__date-col"><?= $sort->link('created_at', ['label' => Yii::t('ThiscoveryFormsModule.base', 'Date created')]) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($dataProvider->getModels() as $panel): ?>
                    <?php /** @var FormPanel $panel */ ?>
                    <tr class="cf-form-row">
                        <td class="cf-form-table__actions-col">
                            <div class="cf-form-row__actions">
                                <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Open'))
                                    ->link(Url::toPanelView($panel, $contentContainer))
                                    ->sm()
                                    ->loader(false) ?>
                                <?= Button::light()
                                    ->link(Url::toPanelEdit($contentContainer, $panel->id))
                                    ->sm()
                                    ->icon('pencil')
                                    ->tooltip(Yii::t('ThiscoveryFormsModule.base', 'Edit'))
                                    ->loader(false) ?>
                                <?= Html::beginForm(Url::toPanelDelete($panel, $contentContainer), 'post', ['class' => 'cf-form-row__delete']) ?>
                                    <?= Button::danger()
                                        ->confirm(Yii::t('ThiscoveryFormsModule.base', 'Delete this panel and its members? Forms that enrol into it will need a new panel chosen.'))
                                        ->submit()
                                        ->sm()
                                        ->icon('trash')
                                        ->tooltip(Yii::t('ThiscoveryFormsModule.base', 'Delete'))
                                        ->loader(false) ?>
                                <?= Html::endForm() ?>
                            </div>
                        </td>
                        <td class="cf-form-table__form-col">
                            <div class="cf-form-row__title">
                                <?= Html::a(Html::encode($panel->title), Url::toPanelView($panel, $contentContainer)) ?>
                            </div>
                            <?php if (trim((string)$panel->description) !== ''): ?>
                                <div class="cf-form-row__desc">
                                    <?= Html::encode(mb_strimwidth($panel->description, 0, 120, '…')) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="cf-form-table__num-col">
                            <span class="cf-form-row__stat"><?= (int)$panel->getActiveMemberCount() ?></span>
                        </td>
                        <td class="cf-form-table__date-col">
                            <?= $panel->created_at ? Html::encode(Yii::$app->formatter->asDatetime($panel->created_at, 'short')) : '—' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="cf-list-pager">
            <div class="cf-list-pager__count">
                <?= Yii::t('ThiscoveryFormsModule.base', '{n,plural,=1{1 panel} other{# panels}}', ['n' => $total]) ?>
            </div>
            <?= LinkPager::widget(['pagination' => $dataProvider->pagination]) ?>
        </div>
    <?php endif; ?>
</div>
