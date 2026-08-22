<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var FormAnswer $answer */

$displayName = $answer->getSubmitterDisplayName();
$created = $answer->created_at ? Yii::$app->formatter->asDatetime($answer->created_at, 'medium') : '';
$updated = ($answer->updated_at && $answer->updated_at !== $answer->created_at)
    ? Yii::$app->formatter->asDatetime($answer->updated_at, 'medium')
    : null;

$valueMap = [];
$justMap = [];
$answerFieldMap = [];
foreach ($answer->answerFields as $af) {
    $answerFieldMap[(int)$af->field_id] = $af;
    $valueMap[(int)$af->field_id] = $af->getDisplayValue();
    if (trim((string)$af->justification) !== '') {
        $justMap[(int)$af->field_id] = (string)$af->justification;
    }
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
?>

<div class="cf-answer-detail">
    <div class="cf-answer-detail__who">
        <div class="cf-answer-detail__name"><?= Html::encode($displayName) ?></div>
        <div class="cf-answer-detail__when">
            <span><?= Html::encode($created) ?></span>
            <?php if ($updated): ?>
                <span class="cf-answer-card__dot">·</span>
                <span><?= Yii::t('ThiscoveryFormsModule.base', 'Updated {date}', ['date' => $updated]) ?></span>
            <?php endif; ?>
        </div>
        <div class="cf-answer-detail__chips">
            <span class="cf-form-row__chip">
                <?= $answer->isComplete()
                    ? Yii::t('ThiscoveryFormsModule.base', 'Complete')
                    : Yii::t('ThiscoveryFormsModule.base', 'In progress') ?>
            </span>
            <span class="cf-form-row__chip">
                <?= Yii::t('ThiscoveryFormsModule.base', '{answered}/{total} fields', [
                    'answered' => $answeredCount,
                    'total' => $answerableTotal,
                ]) ?>
            </span>
            <?php if ($answer->wave): ?>
                <span class="cf-form-row__chip"><?= Html::encode($answer->wave->getDisplayTitle()) ?></span>
            <?php endif; ?>
            <?php if ($answer->round): ?>
                <span class="cf-form-row__chip"><?= Html::encode($answer->round->getDisplayTitle()) ?></span>
            <?php endif; ?>
            <?php if ($formModel->isProject()): ?>
                <span class="cf-form-row__chip"><?= Html::encode($answer->getWorkflowLabel()) ?></span>
            <?php endif; ?>
        </div>
        <?php if ($formModel->isProject()): ?>
            <a class="btn btn-sm btn-default" href="<?= Html::encode(Url::toProject($formModel, $answer)) ?>">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Open record') ?>
            </a>
        <?php endif; ?>
    </div>

    <?php if (!count($formModel->fields)): ?>
        <p class="cf-answer-empty"><?= Yii::t('ThiscoveryFormsModule.base', 'This form has no fields.') ?></p>
    <?php else: ?>
        <div class="cf-answer-fields cf-answer-fields--stack">
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
                            <?= !empty($answerFieldMap[(int)$field->id])
                                ? $answerFieldMap[(int)$field->id]->getAnswerHtml()
                                : nl2br(Html::encode($afValue)) ?>
                            <?php if (!empty($justMap[(int)$field->id])): ?>
                                <div class="cf-answer-just">
                                    <?= nl2br(Html::encode($justMap[(int)$field->id])) ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
