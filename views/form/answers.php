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
            <div class="cf-dash-kicker"><?= Yii::t('ThiscoveryFormsModule.base', 'Submissions') ?></div>
            <h1 class="cf-list-title"><?= Html::encode($formModel->title) ?></h1>
            <p class="cf-list-sub">
                <?= Yii::t('ThiscoveryFormsModule.base', '{n,plural,=0{No submissions yet}=1{1 submission} other{# submissions}}', [
                    'n' => $total,
                ]) ?>
            </p>
        </div>
        <div class="cf-list-header__actions">
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Back to form'))
                ->link(Url::toView($formModel))
                ->sm()
                ->loader(false) ?>
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Dashboard'))
                ->link(Url::toDashboard($formModel))
                ->sm()
                ->icon('bar-chart')
                ->loader(false) ?>
            <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Export CSV'))
                ->link(Url::toExport($formModel))
                ->sm()
                ->icon('download')
                ->loader(false) ?>
        </div>
    </div>

    <?php if (!$dataProvider->getCount()): ?>
        <div class="cf-list-empty">
            <i class="fa fa-inbox"></i>
            <h3><?= Yii::t('ThiscoveryFormsModule.base', 'No submissions yet.') ?></h3>
            <p><?= Yii::t('ThiscoveryFormsModule.base', 'Responses will appear here once members complete this form.') ?></p>
        </div>
    <?php else: ?>
        <div class="cf-answer-list">
            <?php foreach ($dataProvider->getModels() as $index => $answer): ?>
                <?php
                /** @var FormAnswer $answer */
                $displayName = $answer->getSubmitterDisplayName();
                $created = $answer->created_at ? Yii::$app->formatter->asDatetime($answer->created_at, 'medium') : '';
                $updated = ($answer->updated_at && $answer->updated_at !== $answer->created_at)
                    ? Yii::$app->formatter->asDatetime($answer->updated_at, 'medium')
                    : null;

                $valueMap = [];
                foreach ($answer->answerFields as $af) {
                    $valueMap[(int)$af->field_id] = $af->getDisplayValue();
                }
                $answeredCount = 0;
                $answerableTotal = 0;
                foreach ($formModel->fields as $field) {
                    if (!$field->collectsAnswer()) {
                        continue;
                    }
                    $answerableTotal++;
                    $v = $valueMap[(int)$field->id] ?? '';
                    if ($v !== '') {
                        $answeredCount++;
                    }
                }
                $page = $dataProvider->pagination;
                $seq = $page ? ($page->page * $page->pageSize) + $index + 1 : $index + 1;
                ?>
                <article class="cf-answer-card">
                    <header class="cf-answer-card__header">
                        <div class="cf-answer-card__who">
                            <span class="cf-answer-card__avatar" aria-hidden="true">
                                <?= Html::encode(mb_strtoupper(mb_substr($displayName, 0, 1))) ?>
                            </span>
                            <div>
                                <div class="cf-answer-card__name"><?= Html::encode($displayName) ?></div>
                                <div class="cf-answer-card__when">
                                    <span><?= Html::encode($created) ?></span>
                                    <?php if ($updated): ?>
                                        <span class="cf-answer-card__dot">·</span>
                                        <span><?= Yii::t('ThiscoveryFormsModule.base', 'Updated {date}', ['date' => $updated]) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="cf-answer-card__badges">
                            <span class="cf-answer-card__chip">#<?= (int)$seq ?></span>
                            <span class="cf-answer-card__chip">
                                <?= Yii::t('ThiscoveryFormsModule.base', '{answered}/{total} fields', [
                                    'answered' => $answeredCount,
                                    'total' => $answerableTotal,
                                ]) ?>
                            </span>
                        </div>
                    </header>

                    <div class="cf-answer-card__body">
                        <?php if (!count($formModel->fields)): ?>
                            <p class="cf-answer-empty"><?= Yii::t('ThiscoveryFormsModule.base', 'This form has no fields.') ?></p>
                        <?php else: ?>
                            <div class="cf-answer-fields">
                                <?php foreach ($formModel->fields as $field): ?>
                                    <?php
                                    if (!$field->collectsAnswer()) {
                                        continue;
                                    }
                                    $afValue = $valueMap[(int)$field->id] ?? '';
                                    $isEmpty = $afValue === '';
                                    ?>
                                    <div class="cf-answer-field<?= $isEmpty ? ' is-empty' : '' ?>">
                                        <div class="cf-answer-field__label"><?= Html::encode($field->label) ?></div>
                                        <div class="cf-answer-field__value">
                                            <?php if ($isEmpty): ?>
                                                <span class="cf-answer-field__blank"><?= Yii::t('ThiscoveryFormsModule.base', 'No answer') ?></span>
                                            <?php else: ?>
                                                <?= nl2br(Html::encode($afValue)) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="cf-list-pager">
            <?= LinkPager::widget(['pagination' => $dataProvider->pagination]) ?>
        </div>
    <?php endif; ?>
</div>
