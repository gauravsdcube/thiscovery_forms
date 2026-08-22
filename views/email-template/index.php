<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\FormEmailTemplate;
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
$indexUrl = Url::toEmailTemplateIndex($contentContainer);
?>

<div class="cf-list-page">
    <div class="cf-list-header">
        <div>
            <div class="cf-dash-kicker"><?= Yii::t('ThiscoveryFormsModule.base', 'Thiscovery Forms') ?></div>
            <h1 class="cf-list-title"><?= Yii::t('ThiscoveryFormsModule.base', 'Email templates') ?></h1>
            <p class="cf-list-sub">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Reusable messages for invites, waves, reminders, post-completion emails, and field, page, or submit actions. Each template has a header, body, and footer.') ?>
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
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Panels'))
                ->link(Url::toPanelIndex($contentContainer))
                ->sm()
                ->icon('users')
                ->loader(false) ?>
            <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Create template'))
                ->link(Url::toEmailTemplateEdit($contentContainer))
                ->sm()
                ->icon('plus')
                ->pjax(false)
                ->loader(false) ?>
        </div>
    </div>

    <form method="get" action="<?= Html::encode($indexUrl) ?>" class="cf-list-filters">
        <div class="cf-list-filters__search">
            <i class="fa fa-search" aria-hidden="true"></i>
            <input type="search" name="q" value="<?= Html::encode($filters['q']) ?>"
                   placeholder="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Search templates')) ?>">
        </div>
        <button type="submit" class="btn btn-default btn-sm"><?= Yii::t('ThiscoveryFormsModule.base', 'Search') ?></button>
        <?php if ($hasFilters): ?>
            <a class="btn btn-link btn-sm" href="<?= Html::encode($indexUrl) ?>"><?= Yii::t('ThiscoveryFormsModule.base', 'Clear') ?></a>
        <?php endif; ?>
    </form>

    <?php if ($total === 0): ?>
        <div class="cf-list-empty">
            <i class="fa fa-envelope-o" aria-hidden="true"></i>
            <h3><?= $hasFilters
                ? Yii::t('ThiscoveryFormsModule.base', 'No matching templates.')
                : Yii::t('ThiscoveryFormsModule.base', 'No email templates yet') ?></h3>
            <p><?= $hasFilters
                ? Yii::t('ThiscoveryFormsModule.base', 'Try a different search or clear the filters.')
                : Yii::t('ThiscoveryFormsModule.base', 'Create a template with a header, body, and footer, then choose it on a form or an action.') ?></p>
            <?php if (!$hasFilters): ?>
                <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Create template'))
                    ->link(Url::toEmailTemplateEdit($contentContainer))
                    ->pjax(false)
                    ->loader(false) ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="cf-form-table-wrap">
            <table class="cf-form-table">
                <thead>
                <tr>
                    <th class="cf-form-table__actions-col"><?= Yii::t('ThiscoveryFormsModule.base', 'Actions') ?></th>
                    <th><?= $sort->link('title', ['label' => Yii::t('ThiscoveryFormsModule.base', 'Template')]) ?></th>
                    <th><?= $sort->link('subject', ['label' => Yii::t('ThiscoveryFormsModule.base', 'Subject')]) ?></th>
                    <th class="cf-form-table__date-col"><?= $sort->link('updated_at', ['label' => Yii::t('ThiscoveryFormsModule.base', 'Date modified')]) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($dataProvider->getModels() as $template): ?>
                    <?php /** @var FormEmailTemplate $template */ ?>
                    <tr class="cf-form-row">
                        <td class="cf-form-table__actions-col">
                            <div class="cf-form-row__actions">
                                <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Open'))
                                    ->link(Url::toEmailTemplateEdit($contentContainer, $template->id))
                                    ->sm()
                                    ->pjax(false)
                                    ->loader(false) ?>
                                <?= Html::beginForm(Url::toEmailTemplateDelete($template, $contentContainer), 'post', ['class' => 'cf-form-row__delete']) ?>
                                    <?= Button::danger()
                                        ->confirm(Yii::t('ThiscoveryFormsModule.base', 'Delete this email template? Forms using it will fall back to the default text.'))
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
                                <?= Html::a(Html::encode($template->title), Url::toEmailTemplateEdit($contentContainer, $template->id), ['data-pjax' => '0']) ?>
                            </div>
                        </td>
                        <td><?= Html::encode($template->subject) ?></td>
                        <td class="cf-form-table__date-col">
                            <?= $template->updated_at ? Html::encode(Yii::$app->formatter->asDatetime($template->updated_at, 'short')) : '—' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="cf-list-pager">
            <div class="cf-list-pager__count">
                <?= Yii::t('ThiscoveryFormsModule.base', '{n,plural,=1{1 template} other{# templates}}', ['n' => $total]) ?>
            </div>
            <?= LinkPager::widget(['pagination' => $dataProvider->pagination]) ?>
        </div>
    <?php endif; ?>
</div>
