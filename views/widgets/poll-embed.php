<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var SubmitForm $submit */
/** @var FormField|null $question */
/** @var bool $canVote */
/** @var bool $already */
/** @var bool $showResults */
/** @var array $results */
/** @var bool $compact */

$submitUrl = Url::toSubmitJson($formModel);
?>
<div class="cf-poll-embed<?= $compact ? ' cf-poll-embed--compact' : '' ?>"
     data-cf-poll-embed
     data-cf-submit-url="<?= Html::encode($submitUrl) ?>">
    <?php if (!$compact): ?>
        <h3 class="cf-poll-embed__title"><?= Html::encode($formModel->title) ?></h3>
        <?php if (trim((string)$formModel->description) !== ''): ?>
            <p class="cf-poll-embed__desc"><?= nl2br(Html::encode($formModel->description)) ?></p>
        <?php endif; ?>
    <?php elseif (trim((string)$formModel->description) !== ''): ?>
        <p class="cf-poll-embed__desc text-muted"><?= nl2br(Html::encode(mb_strimwidth($formModel->description, 0, 220, '…'))) ?></p>
    <?php endif; ?>

    <div data-cf-poll-body>
        <?php if ($canVote && $question): ?>
            <?= Html::beginForm($submitUrl, 'post', [
                'class' => 'cf-poll-embed__form',
                'data-cf-poll-form' => 1,
                'autocomplete' => 'off',
            ]) ?>
                <div class="cf-poll-embed__question">
                    <?= $this->render('@thiscovery-forms/views/form/_field_fill', [
                        'field' => $question,
                        'value' => $submit->values[$question->id] ?? null,
                        'displayIndex' => 1,
                        'formModel' => $formModel,
                        'allValues' => $submit->values,
                        'allFields' => $formModel->fields,
                    ]) ?>
                </div>
                <div class="cf-poll-embed__actions">
                    <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Vote'))
                        ->submit()
                        ->sm()
                        ->loader(true) ?>
                </div>
            <?= Html::endForm() ?>
        <?php elseif ($formModel->isDraft()): ?>
            <p class="text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'This poll is still a draft.') ?></p>
        <?php elseif ($already): ?>
            <p class="cf-poll-embed__thanks">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Thanks for voting.') ?>
            </p>
        <?php elseif ($formModel->isClosed()): ?>
            <p class="text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'This poll is closed.') ?></p>
        <?php else: ?>
            <p class="text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'You are not allowed to vote in this poll.') ?></p>
        <?php endif; ?>

        <?php if ($showResults && $results): ?>
            <?= $this->render('@thiscovery-forms/views/widgets/poll-results', [
                'results' => $results,
            ]) ?>
        <?php endif; ?>
    </div>
</div>
