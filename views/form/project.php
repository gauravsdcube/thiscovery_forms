<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormAnswerApproval;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\ApprovalWorkflowService;
use humhub\modules\thiscoveryForms\services\FormPager;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var FormAnswer $answer */
/** @var ApprovalWorkflowService $service */
/** @var $contentContainer */

ThiscoveryFormsAsset::register($this);

$valueMap = [];
$answerFieldMap = [];
foreach ($answer->answerFields as $af) {
    $answerFieldMap[(int)$af->field_id] = $af;
    $valueMap[(int)$af->field_id] = $af->getDisplayValue();
}

$pager = (new FormPager())->buildPages($formModel->fields);
$pages = $pager['pages'];
$user = Yii::$app->user->getIdentity();
$canAct = $service->canActOnAnswer($answer, $user);
$canManage = $formModel->canManage($user);
$isAuthor = $user && (int)$answer->created_by === (int)$user->id;
?>

<div class="cf-project-page">
    <div class="cf-list-header">
        <div>
            <div class="cf-dash-kicker"><?= Html::encode($formModel->title) ?></div>
            <h1 class="cf-list-title"><?= Html::encode($answer->getRecordTitle()) ?></h1>
            <p class="cf-list-sub">
                <?= Html::encode($answer->getSubmitterDisplayName()) ?>
                · <?= Html::encode(Yii::$app->formatter->asDatetime($answer->submitted_at ?: $answer->created_at, 'medium')) ?>
                · <span class="cf-answer-card__chip"><?= Html::encode($answer->getWorkflowLabel()) ?></span>
                <?php if ($answer->currentStage && $answer->isInReview()): ?>
                    · <?= Yii::t('ThiscoveryFormsModule.base', 'Stage: {name}', ['name' => $answer->currentStage->name]) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="cf-list-header__actions">
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Catalogue'))
                ->link(Url::toCatalogue($formModel))->sm()->loader(false) ?>
            <?php if ($formModel->canViewAnswers()): ?>
                <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'All submissions'))
                    ->link(Url::toAnswers($formModel))->sm()->loader(false) ?>
            <?php endif; ?>
            <?php if ($isAuthor && $answer->isChangesRequested()): ?>
                <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Edit and resubmit'))
                    ->link(Url::toEditAnswer($formModel, $answer))->sm()->loader(false) ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($answer->isChangesRequested() && $isAuthor): ?>
        <div class="alert alert-warning">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Changes were requested. Update the record and submit it again.') ?>
        </div>
    <?php endif; ?>

    <div class="cf-project-sections">
        <?php foreach ($pages as $page): ?>
            <section class="cf-project-section">
                <?php if (!empty($page['title'])): ?>
                    <h2><?= Html::encode($page['title']) ?></h2>
                <?php endif; ?>
                <div class="cf-answer-fields">
                    <?php foreach ($page['items'] as $field): ?>
                        <?php
                        /** @var FormField $field */
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
                                    <?php if ($field->type === FormField::TYPE_MAP && !empty($answerFieldMap[(int)$field->id])): ?>
                                        <?= $this->render('_answer_map', [
                                            'field' => $field,
                                            'answerField' => $answerFieldMap[(int)$field->id],
                                        ]) ?>
                                    <?php else: ?>
                                        <?= !empty($answerFieldMap[(int)$field->id])
                                            ? $answerFieldMap[(int)$field->id]->getAnswerHtml()
                                            : nl2br(Html::encode($afValue)) ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <?php if ($canAct && ($answer->isInReview() || $answer->isChangesRequested() || $canManage)): ?>
        <div class="cf-approval-actions panel panel-default">
            <div class="panel-heading"><?= Yii::t('ThiscoveryFormsModule.base', 'Review') ?></div>
            <div class="panel-body">
                <?php if ($answer->currentStage): ?>
                    <p class="text-muted">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Current stage: {name} — {summary}', [
                            'name' => $answer->currentStage->name,
                            'summary' => $answer->currentStage->getAuthoritySummary(),
                        ]) ?>
                    </p>
                <?php endif; ?>
                <?php if ($answer->isInReview() || ($canManage && !$answer->isPublished() && !$answer->isArchived())): ?>
                    <?= Html::beginForm(Url::toAnswerApprove($formModel, $answer), 'post', ['class' => 'mb-3']) ?>
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Comment (optional)') ?></label>
                        <textarea name="comment" class="form-control mb-2" rows="2"></textarea>
                        <button type="submit" class="btn btn-primary"><?= Yii::t('ThiscoveryFormsModule.base', 'Approve') ?></button>
                    <?= Html::endForm() ?>
                    <?= Html::beginForm(Url::toAnswerChanges($formModel, $answer), 'post', ['class' => 'mb-3']) ?>
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'What needs to change') ?></label>
                        <textarea name="comment" class="form-control mb-2" rows="2"></textarea>
                        <button type="submit" class="btn btn-light"><?= Yii::t('ThiscoveryFormsModule.base', 'Request changes') ?></button>
                    <?= Html::endForm() ?>
                <?php endif; ?>
                <?php if ($canManage && !$answer->isArchived()): ?>
                    <?= Html::beginForm(Url::toAnswerArchive($formModel, $answer), 'post') ?>
                        <button type="submit" class="btn btn-sm btn-danger"><?= Yii::t('ThiscoveryFormsModule.base', 'Archive') ?></button>
                    <?= Html::endForm() ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php
    $history = $answer->approvals;
    if ($history):
        ?>
        <div class="cf-approval-history">
            <h2><?= Yii::t('ThiscoveryFormsModule.base', 'Decision log') ?></h2>
            <ul>
                <?php foreach ($history as $row): ?>
                    <?php /** @var FormAnswerApproval $row */ ?>
                    <li>
                        <strong><?= Html::encode($row->user->displayName ?? Yii::t('ThiscoveryFormsModule.base', 'Unknown user')) ?></strong>
                        — <?= Html::encode(FormAnswerApproval::getActionLabels()[$row->action] ?? $row->action) ?>
                        <?php if ($row->stage): ?>
                            (<?= Html::encode($row->stage->name) ?>)
                        <?php endif; ?>
                        <span class="text-muted"><?= Html::encode(Yii::$app->formatter->asDatetime($row->created_at, 'short')) ?></span>
                        <?php if ($row->comment): ?>
                            <div><?= nl2br(Html::encode($row->comment)) ?></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
