<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\widgets\bootstrap\Badge;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;
use yii\widgets\LinkPager;

/** @var $dataProvider yii\data\ActiveDataProvider */
/** @var $contentContainer humhub\modules\content\components\ContentContainerActiveRecord|null */
/** @var $canCreate bool */

ThiscoveryFormsAsset::register($this);
$statusLabels = CustomForm::getStatusLabels();
?>

<div class="cf-list-page">
    <div class="cf-list-header">
        <div>
            <h1 class="cf-list-title"><?= Yii::t('ThiscoveryFormsModule.base', 'Forms') ?></h1>
            <p class="cf-list-sub"><?= Yii::t('ThiscoveryFormsModule.base', 'Browse, open, and manage forms in one place.') ?></p>
        </div>
        <div class="cf-list-header__actions">
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Dashboard'))
                ->link(Url::toOverview($contentContainer))
                ->icon('bar-chart')
                ->loader(false) ?>
            <?php if ($canCreate): ?>
                <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Create form'))
                    ->link(Url::toCreate($contentContainer))
                    ->icon('plus')
                    ->loader(false) ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$dataProvider->getCount()): ?>
        <div class="cf-list-empty">
            <i class="fa fa-wpforms"></i>
            <h3><?= Yii::t('ThiscoveryFormsModule.base', 'No forms yet.') ?></h3>
            <?php if ($canCreate): ?>
                <p><?= Yii::t('ThiscoveryFormsModule.base', 'Create your first form to collect responses from members.') ?></p>
                <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Create form'))
                    ->link(Url::toCreate($contentContainer))
                    ->loader(false) ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="cf-form-table-wrap">
            <table class="cf-form-table">
                <thead>
                <tr>
                    <th class="cf-form-table__actions-col"><?= Yii::t('ThiscoveryFormsModule.base', 'Actions') ?></th>
                    <th class="cf-form-table__status-col"><?= Yii::t('ThiscoveryFormsModule.base', 'Status') ?></th>
                    <th class="cf-form-table__form-col"><?= Yii::t('ThiscoveryFormsModule.base', 'Form') ?></th>
                    <th class="cf-form-table__num-col"><?= Yii::t('ThiscoveryFormsModule.base', 'Fields') ?></th>
                    <th class="cf-form-table__num-col"><?= Yii::t('ThiscoveryFormsModule.base', 'Submissions') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($dataProvider->getModels() as $formModel): ?>
                    <?php
                    /** @var CustomForm $formModel */
                    $status = $statusLabels[$formModel->status] ?? '';
                    $fieldCount = count($formModel->fields);
                    $answerCount = (int)$formModel->getAnswers()->count();
                    $canManage = $formModel->canManage();
                    $canViewAnswers = $formModel->canViewAnswers();
                    ?>
                    <tr class="cf-form-row" data-status="<?= (int)$formModel->status ?>">
                        <td class="cf-form-table__actions-col">
                            <div class="cf-form-row__actions">
                                <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Open'))
                                    ->link(Url::toView($formModel))
                                    ->sm()
                                    ->loader(false) ?>

                                <?php if ($canManage): ?>
                                    <?= Button::light()
                                        ->link(Url::toEdit($formModel))
                                        ->sm()
                                        ->icon('pencil')
                                        ->tooltip(Yii::t('ThiscoveryFormsModule.base', 'Edit'))
                                        ->loader(false) ?>
                                <?php endif; ?>

                                <?php if ($canViewAnswers): ?>
                                    <?= Button::light()
                                        ->link(Url::toDashboard($formModel))
                                        ->sm()
                                        ->icon('bar-chart')
                                        ->tooltip(Yii::t('ThiscoveryFormsModule.base', 'Dashboard'))
                                        ->loader(false) ?>
                                    <?= Button::light()
                                        ->link(Url::toAnswers($formModel))
                                        ->sm()
                                        ->icon('list')
                                        ->tooltip(Yii::t('ThiscoveryFormsModule.base', 'Answers'))
                                        ->loader(false) ?>
                                <?php endif; ?>

                                <?php if ($canManage): ?>
                                    <?= Html::beginForm(Url::toDelete($formModel), 'post', ['class' => 'cf-form-row__delete']) ?>
                                    <?= Button::danger()
                                        ->confirm(Yii::t('ThiscoveryFormsModule.base', 'Delete this form and all submissions?'))
                                        ->submit()
                                        ->sm()
                                        ->icon('trash')
                                        ->tooltip(Yii::t('ThiscoveryFormsModule.base', 'Delete'))
                                        ->loader(false) ?>
                                    <?= Html::endForm() ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="cf-form-table__status-col">
                            <?php if ($formModel->isClosed()): ?>
                                <?= Badge::danger($status) ?>
                            <?php elseif ($formModel->isDraft()): ?>
                                <?= Badge::warning($status) ?>
                            <?php else: ?>
                                <?= Badge::success($status) ?>
                            <?php endif; ?>
                        </td>
                        <td class="cf-form-table__form-col">
                            <div class="cf-form-row__title">
                                <?= Html::a(Html::encode($formModel->title), Url::toView($formModel)) ?>
                                <?php if ($formModel->show_in_menu): ?>
                                    <span class="cf-form-row__chip" title="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'In menu')) ?>">
                                        <i class="fa fa-bars"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($formModel->description): ?>
                                <div class="cf-form-row__desc">
                                    <?= Html::encode(mb_strimwidth(strip_tags($formModel->description), 0, 120, '…')) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="cf-form-table__num-col">
                            <span class="cf-form-row__stat"><?= (int)$fieldCount ?></span>
                        </td>
                        <td class="cf-form-table__num-col">
                            <span class="cf-form-row__stat"><?= (int)$answerCount ?></span>
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
</div>
