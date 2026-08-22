<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\FormPanel;
use humhub\modules\thiscoveryForms\models\FormPanelActivity;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var FormPanel $panel */
/** @var FormPanelMember $member */
/** @var FormPanelActivity[] $activities */
/** @var $contentContainer */

ThiscoveryFormsAsset::register($this);
$activities = $activities ?? [];
?>

<div class="cf-list-page">
    <div class="cf-list-header">
        <div>
            <div class="cf-dash-kicker"><?= Html::encode($panel->title) ?></div>
            <h1 class="cf-list-title"><?= Html::encode($member->getDisplayLabel()) ?></h1>
            <p class="cf-list-sub">
                <?= Html::encode((string)$member->email) ?>
                · <span class="cf-form-row__chip">
                    <?= $member->user_id
                        ? Yii::t('ThiscoveryFormsModule.base', 'Signed-in user')
                        : Yii::t('ThiscoveryFormsModule.base', 'Email invite') ?>
                </span>
                <?php if ($member->created_at): ?>
                    · <?= Yii::t('ThiscoveryFormsModule.base', 'Joined {date}', [
                        'date' => Yii::$app->formatter->asDatetime($member->created_at, 'medium'),
                    ]) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="cf-list-header__actions">
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Back to panel'))
                ->link(Url::toPanelView($panel, $contentContainer))
                ->sm()
                ->icon('arrow-left')
                ->loader(false) ?>
            <?= Html::beginForm(Url::toPanelMemberRemove($panel, $contentContainer), 'post', ['class' => 'd-inline']) ?>
                <?= Html::hiddenInput('member_id', $member->id) ?>
                <?= Button::danger(Yii::t('ThiscoveryFormsModule.base', 'Remove from panel'))
                    ->confirm(Yii::t('ThiscoveryFormsModule.base', 'Remove this person from the panel? Their completion history on this panel will stay.'))
                    ->submit()
                    ->sm()
                    ->icon('trash')
                    ->loader(false) ?>
            <?= Html::endForm() ?>
        </div>
    </div>

    <?= Html::beginForm(Url::toPanelMember($member, $contentContainer), 'post', ['class' => 'cf-folder-form']) ?>
        <div class="cf-folder-form__card">
            <h2 class="cf-folder-form__h"><?= Yii::t('ThiscoveryFormsModule.base', 'Member record') ?></h2>
            <div class="row g-3">
                <div class="col-md-4 form-group mb-0">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'First name') ?></label>
                    <input type="text" name="first_name" class="form-control"
                           value="<?= Html::encode((string)$member->first_name) ?>">
                </div>
                <div class="col-md-4 form-group mb-0">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Last name') ?></label>
                    <input type="text" name="last_name" class="form-control"
                           value="<?= Html::encode((string)$member->last_name) ?>">
                </div>
                <div class="col-md-4 form-group mb-0">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Email') ?></label>
                    <input type="email" name="email" class="form-control"
                           value="<?= Html::encode((string)$member->email) ?>">
                </div>
                <?php foreach ($panel->getMemberFields() as $extra): ?>
                    <div class="col-md-4 form-group mb-0">
                        <label class="cf-label"><?= Html::encode($extra['label']) ?></label>
                        <?php $attrVal = \humhub\modules\thiscoveryForms\services\PanelFieldService::memberValue($member, $extra['key']); ?>
                        <?php if ($extra['type'] === 'dropdown' && $extra['options']): ?>
                            <?= Html::dropDownList('attrs[' . $extra['key'] . ']', $attrVal, array_combine($extra['options'], $extra['options']), [
                                'class' => 'form-control',
                                'prompt' => Yii::t('ThiscoveryFormsModule.base', 'Select…'),
                            ]) ?>
                        <?php else: ?>
                            <input type="<?= $extra['type'] === 'number' || $extra['type'] === 'date' || $extra['type'] === 'email' ? $extra['type'] : 'text' ?>"
                                   name="attrs[<?= Html::encode($extra['key']) ?>]" class="form-control"
                                   value="<?= Html::encode($attrVal) ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="cf-folder-form__actions mt-3">
                <?= Button::save(Yii::t('ThiscoveryFormsModule.base', 'Save record'))->submit() ?>
            </div>
        </div>
    <?= Html::endForm() ?>

    <div class="cf-folder-form__card">
        <h2 class="cf-folder-form__h"><?= Yii::t('ThiscoveryFormsModule.base', 'Recorded on this member') ?></h2>
        <p class="cf-list-sub">
            <?= Yii::t('ThiscoveryFormsModule.base', 'These facts are stored on the panel record. Survey answers live on each form response; open a completion to see them. Fields dropped onto a survey as Panel member also copy onto this record when the person submits.') ?>
        </p>
        <div class="cf-form-table-wrap">
            <table class="cf-form-table">
                <tbody>
                <tr><th><?= Yii::t('ThiscoveryFormsModule.base', 'Display name') ?></th><td><?= Html::encode($member->getDisplayLabel()) ?></td></tr>
                <tr><th><?= Yii::t('ThiscoveryFormsModule.base', 'Source') ?></th><td><?= $member->user_id
                    ? Yii::t('ThiscoveryFormsModule.base', 'Signed-in user')
                    : Yii::t('ThiscoveryFormsModule.base', 'Email invite') ?></td></tr>
                <tr><th><?= Yii::t('ThiscoveryFormsModule.base', 'Linked account') ?></th><td><?= $member->user
                    ? Html::encode($member->user->displayName)
                    : '—' ?></td></tr>
                <tr><th><?= Yii::t('ThiscoveryFormsModule.base', 'Consent recorded') ?></th><td><?= $member->consent_at
                    ? Html::encode(Yii::$app->formatter->asDatetime($member->consent_at, 'medium'))
                    : Yii::t('ThiscoveryFormsModule.base', 'Not yet') ?></td></tr>
                <tr><th><?= Yii::t('ThiscoveryFormsModule.base', 'Joined') ?></th><td><?= $member->created_at
                    ? Html::encode(Yii::$app->formatter->asDatetime($member->created_at, 'medium'))
                    : '—' ?></td></tr>
                <tr><th><?= Yii::t('ThiscoveryFormsModule.base', 'Weight') ?></th><td><?= Html::encode((string)$member->weight) ?></td></tr>
                <?php foreach ($panel->getMemberFields() as $extra): ?>
                    <tr>
                        <th><?= Html::encode($extra['label']) ?> <code>{{member.<?= Html::encode($extra['key']) ?>}}</code></th>
                        <td><?= Html::encode(\humhub\modules\thiscoveryForms\services\PanelFieldService::memberValue($member, $extra['key']) ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="cf-folder-form__card">
        <h2 class="cf-folder-form__h"><?= Yii::t('ThiscoveryFormsModule.base', 'Form completions') ?></h2>
        <?php if (!$activities): ?>
            <p class="text-muted mb-0"><?= Yii::t('ThiscoveryFormsModule.base', 'No completions recorded on this panel yet.') ?></p>
        <?php else: ?>
            <div class="cf-form-table-wrap">
                <table class="cf-form-table">
                    <thead>
                    <tr>
                        <th class="cf-form-table__actions-col"><?= Yii::t('ThiscoveryFormsModule.base', 'Actions') ?></th>
                        <th><?= Yii::t('ThiscoveryFormsModule.base', 'Form') ?></th>
                        <th class="cf-form-table__date-col"><?= Yii::t('ThiscoveryFormsModule.base', 'Completed') ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($activities as $row): ?>
                        <?php /** @var FormPanelActivity $row */ ?>
                        <tr class="cf-form-row">
                            <td class="cf-form-table__actions-col">
                                <?php if ($row->form && $row->answer_id): ?>
                                    <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Open'))
                                        ->link(Url::toAnswerDetail($row->form, $row->answer_id))
                                        ->sm()
                                        ->loader(false) ?>
                                <?php endif; ?>
                            </td>
                            <td class="cf-form-table__form-col">
                                <div class="cf-form-row__title">
                                    <?= Html::encode($row->form ? $row->form->title : Yii::t('ThiscoveryFormsModule.base', 'Form')) ?>
                                </div>
                            </td>
                            <td class="cf-form-table__date-col">
                                <?= $row->created_at ? Html::encode(Yii::$app->formatter->asDatetime($row->created_at, 'medium')) : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
