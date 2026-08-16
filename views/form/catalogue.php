<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;
use yii\widgets\LinkPager;

/** @var CustomForm $formModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var $contentContainer */

ThiscoveryFormsAsset::register($this);
$total = (int)$dataProvider->getTotalCount();
?>

<div class="cf-answers-page">
    <div class="cf-list-header">
        <div>
            <div class="cf-dash-kicker"><?= Yii::t('ThiscoveryFormsModule.base', 'Catalogue') ?></div>
            <h1 class="cf-list-title"><?= Html::encode($formModel->title) ?></h1>
            <p class="cf-list-sub">
                <?= Yii::t('ThiscoveryFormsModule.base', '{n,plural,=0{No published projects yet}=1{1 published project} other{# published projects}}', [
                    'n' => $total,
                ]) ?>
            </p>
        </div>
        <div class="cf-list-header__actions">
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Submit a project'))
                ->link(Url::toView($formModel))
                ->sm()
                ->loader(false) ?>
            <?php if ($formModel->canViewAnswers()): ?>
                <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'All submissions'))
                    ->link(Url::toAnswers($formModel))
                    ->sm()
                    ->loader(false) ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$dataProvider->getCount()): ?>
        <div class="cf-list-empty">
            <i class="fa fa-folder-open-o"></i>
            <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Nothing published yet.') ?></h3>
            <p><?= Yii::t('ThiscoveryFormsModule.base', 'Approved projects will appear here.') ?></p>
        </div>
    <?php else: ?>
        <div class="cf-catalogue-grid">
            <?php foreach ($dataProvider->getModels() as $answer): ?>
                <?php /** @var FormAnswer $answer */ ?>
                <a class="cf-catalogue-card" href="<?= Html::encode(Url::toProject($formModel, $answer)) ?>">
                    <div class="cf-catalogue-card__eyebrow"><?= Html::encode($answer->getSubmitterDisplayName()) ?></div>
                    <h3 class="cf-catalogue-card__title"><?= Html::encode($answer->getRecordTitle()) ?></h3>
                    <div class="cf-catalogue-card__meta">
                        <?= Html::encode(Yii::$app->formatter->asDate($answer->submitted_at ?: $answer->created_at, 'medium')) ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="cf-list-pager">
            <?= LinkPager::widget(['pagination' => $dataProvider->pagination]) ?>
        </div>
    <?php endif; ?>
</div>
