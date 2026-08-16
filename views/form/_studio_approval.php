<?php

use humhub\modules\admin\models\forms\UserEditForm;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormApprovalStage;
use humhub\modules\thiscoveryForms\services\ApprovalWorkflowService;
use humhub\modules\user\models\Group;
use humhub\modules\user\widgets\UserPickerField;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var bool $isNew */

$stages = $isNew ? [] : (new ApprovalWorkflowService())->listStages($formModel);
$groupItems = UserEditForm::getGroupItems(Group::find()->orderBy(['name' => SORT_ASC])->all());
?>

<div class="cf-studio__settings">
    <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Approval') ?></h5>
    <p class="cf-hint text-muted">
        <?= Yii::t('ThiscoveryFormsModule.base', 'Add stages in the order they should run. Each stage can be approved by named users, groups, or both. If a stage has no people assigned, form managers can approve it. If you add no stages, form managers publish records directly.') ?>
    </p>

    <?php if ($isNew): ?>
        <div class="alert alert-info">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Save the form first to set up approval stages.') ?>
        </div>
    <?php else: ?>
        <?= Html::beginForm(Url::studioAction($formModel, 'stage-save'), 'post', ['class' => 'cf-inline-form mb-4']) ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Stage name') ?></label>
                    <input type="text" name="name" class="form-control" placeholder="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'e.g. Moderator review')) ?>">
                </div>
                <div class="col-md-6">
                    <button type="submit" class="btn btn-primary"><?= Yii::t('ThiscoveryFormsModule.base', 'Add stage') ?></button>
                </div>
            </div>
        <?= Html::endForm() ?>

        <?php if (!$stages): ?>
            <p class="text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'No stages yet. Form managers can publish or request changes until you add stages.') ?></p>
        <?php endif; ?>

        <?php foreach ($stages as $stage): ?>
            <?php /** @var FormApprovalStage $stage */ ?>
            <div class="cf-approval-stage">
                <div class="cf-approval-stage__head">
                    <strong><?= Html::encode($stage->name) ?></strong>
                    <span class="cf-answer-card__chip">
                        <?= $stage->requiresAll()
                            ? Yii::t('ThiscoveryFormsModule.base', 'All authorities must approve')
                            : Yii::t('ThiscoveryFormsModule.base', 'Any listed authority can approve') ?>
                    </span>
                    <div class="cf-approval-stage__move">
                        <?= Html::beginForm(Url::studioAction($formModel, 'stage-move'), 'post', ['class' => 'd-inline']) ?>
                            <?= Html::hiddenInput('stage_id', $stage->id) ?>
                            <?= Html::hiddenInput('direction', 'up') ?>
                            <button type="submit" class="btn btn-sm btn-light" title="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Move up')) ?>">
                                <i class="fa fa-arrow-up"></i>
                            </button>
                        <?= Html::endForm() ?>
                        <?= Html::beginForm(Url::studioAction($formModel, 'stage-move'), 'post', ['class' => 'd-inline']) ?>
                            <?= Html::hiddenInput('stage_id', $stage->id) ?>
                            <?= Html::hiddenInput('direction', 'down') ?>
                            <button type="submit" class="btn btn-sm btn-light" title="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Move down')) ?>">
                                <i class="fa fa-arrow-down"></i>
                            </button>
                        <?= Html::endForm() ?>
                    </div>
                </div>
                <p class="cf-hint text-muted mb-2"><?= Html::encode($stage->getAuthoritySummary()) ?></p>

                <?= Html::beginForm(Url::studioAction($formModel, 'stage-save'), 'post', ['class' => 'mb-3']) ?>
                    <?= Html::hiddenInput('stage_id', $stage->id) ?>
                    <div class="form-group">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Stage name') ?></label>
                        <input type="text" name="name" class="form-control" value="<?= Html::encode($stage->name) ?>">
                    </div>
                    <div class="form-group">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Users who can approve') ?></label>
                        <?= UserPickerField::widget([
                            'id' => 'cf-stage-users-' . (int)$stage->id,
                            'name' => 'userGuids',
                            'selection' => $stage->getAuthorityUsers(),
                            'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Add users'),
                        ]) ?>
                    </div>
                    <div class="form-group">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Groups who can approve') ?></label>
                        <?= Html::dropDownList(
                            'groupIds[]',
                            $stage->getAuthorityGroupIds(),
                            $groupItems,
                            [
                                'class' => 'form-control',
                                'multiple' => true,
                                'size' => min(8, max(4, count($groupItems) ?: 4)),
                            ]
                        ) ?>
                        <div class="cf-hint text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'Hold Ctrl or Cmd to select more than one group. Any member of a selected group can act for that group.') ?></div>
                    </div>
                    <label class="cf-check">
                        <input type="checkbox" name="require_all" value="1"<?= $stage->requiresAll() ? ' checked' : '' ?>>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Every listed user and group must approve this stage (otherwise one matching person is enough).') ?>
                    </label>
                    <div class="mt-2">
                        <button type="submit" class="btn btn-primary"><?= Yii::t('ThiscoveryFormsModule.base', 'Save stage') ?></button>
                    </div>
                <?= Html::endForm() ?>

                <?= Html::beginForm(Url::studioAction($formModel, 'stage-delete'), 'post', ['class' => 'd-inline']) ?>
                    <?= Html::hiddenInput('stage_id', $stage->id) ?>
                    <button type="submit" class="btn btn-sm btn-danger" data-action-confirm="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Remove this approval stage?')) ?>">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Remove stage') ?>
                    </button>
                <?= Html::endForm() ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
