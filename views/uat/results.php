<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\UatSubmission;
use yii\grid\GridView;
use yii\helpers\Html;

/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var string $kind */
/** @var string $status */
/** @var string $result */
/** @var int $scenarioCount */
/** @var $contentContainer */

ThiscoveryFormsAsset::register($this);
$this->title = Yii::t('ThiscoveryFormsModule.base', 'UAT submissions');
$container = $contentContainer ?? null;
?>

<div class="cf-uat-results">
    <div class="cf-list-header">
        <div>
            <div class="cf-dash-kicker"><?= Yii::t('ThiscoveryFormsModule.base', 'Thiscovery Forms') ?></div>
            <h1 class="cf-list-title"><?= Yii::t('ThiscoveryFormsModule.base', 'UAT submissions') ?></h1>
            <p class="cf-list-sub">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Results and proposed scenarios from testers. Catalog has {n} scenarios.', [
                    'n' => (int)$scenarioCount,
                ]) ?>
            </p>
        </div>
        <div class="cf-list-header__actions">
            <?= Html::a(
                '<i class="fa fa-download"></i> ' . Yii::t('ThiscoveryFormsModule.base', 'Scenarios CSV'),
                Url::toUatCsv(),
                ['class' => 'btn btn-default', 'data-pjax' => '0']
            ) ?>
            <?= Html::a(
                '<i class="fa fa-pencil"></i> ' . Yii::t('ThiscoveryFormsModule.base', 'Open tester form'),
                Url::toUat(),
                ['class' => 'btn btn-primary', 'data-pjax' => '0']
            ) ?>
        </div>
    </div>

    <form method="get" class="form-inline" style="margin-bottom:1rem">
        <?= Html::dropDownList('kind', $kind, ['' => Yii::t('ThiscoveryFormsModule.base', 'All types')] + UatSubmission::kindLabels(), ['class' => 'form-control']) ?>
        <?= Html::dropDownList('result', $result, ['' => Yii::t('ThiscoveryFormsModule.base', 'All results')] + UatSubmission::resultLabels(), ['class' => 'form-control']) ?>
        <?= Html::dropDownList('status', $status, [
            '' => Yii::t('ThiscoveryFormsModule.base', 'All statuses'),
            UatSubmission::STATUS_NEW => Yii::t('ThiscoveryFormsModule.base', 'New'),
            UatSubmission::STATUS_REVIEWED => Yii::t('ThiscoveryFormsModule.base', 'Reviewed'),
            UatSubmission::STATUS_CLOSED => Yii::t('ThiscoveryFormsModule.base', 'Closed'),
        ], ['class' => 'form-control']) ?>
        <button type="submit" class="btn btn-default"><?= Yii::t('ThiscoveryFormsModule.base', 'Filter') ?></button>
    </form>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'tableOptions' => ['class' => 'table table-hover'],
        'columns' => [
            [
                'attribute' => 'created_at',
                'label' => Yii::t('ThiscoveryFormsModule.base', 'When'),
                'format' => ['datetime', 'php:Y-m-d H:i'],
            ],
            [
                'attribute' => 'kind',
                'value' => fn (UatSubmission $m) => UatSubmission::kindLabels()[$m->kind] ?? $m->kind,
            ],
            'test_id',
            'feature',
            [
                'attribute' => 'scenario',
                'contentOptions' => ['style' => 'max-width:240px'],
            ],
            [
                'attribute' => 'result',
                'value' => fn (UatSubmission $m) => UatSubmission::resultLabels()[$m->result] ?? $m->result,
            ],
            'tester_name',
            'status',
            [
                'label' => '',
                'format' => 'raw',
                'value' => fn (UatSubmission $m) => Html::a(
                    Yii::t('ThiscoveryFormsModule.base', 'Open'),
                    Url::toUatView((int)$m->id, $container),
                    ['class' => 'btn btn-sm btn-default']
                ),
            ],
        ],
    ]) ?>
</div>
