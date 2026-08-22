<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\FormPanel;
use humhub\modules\thiscoveryForms\models\FormPanelActivity;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use humhub\modules\user\widgets\UserPickerField;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;
use yii\widgets\LinkPager;

/** @var FormPanel $panel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array<int,int> $activityCounts */
/** @var array<int,FormPanelActivity> $latestActivity */
/** @var array $filters */
/** @var $contentContainer */

ThiscoveryFormsAsset::register($this);
$filters = array_merge(['q' => ''], $filters ?? []);
$hasFilters = $filters['q'] !== '';
$members = $dataProvider->getModels();
$total = (int)$dataProvider->getTotalCount();
$activityCounts = $activityCounts ?? [];
$latestActivity = $latestActivity ?? [];
$viewUrl = Url::toPanelView($panel, $contentContainer);
?>

<div class="cf-list-page">
    <div class="cf-list-header">
        <div>
            <div class="cf-dash-kicker"><?= Yii::t('ThiscoveryFormsModule.base', 'Panels') ?></div>
            <h1 class="cf-list-title"><?= Html::encode($panel->title) ?></h1>
            <p class="cf-list-sub">
                <?= $panel->description !== null && trim($panel->description) !== ''
                    ? Html::encode($panel->description)
                    : Yii::t('ThiscoveryFormsModule.base', 'People in this panel. Open a member to see their record and what was stored when they took part.') ?>
            </p>
        </div>
        <div class="cf-list-header__actions">
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Back to panels'))
                ->link(Url::toPanelIndex($contentContainer))
                ->sm()
                ->icon('arrow-left')
                ->loader(false) ?>
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Edit panel'))
                ->link(Url::toPanelEdit($contentContainer, $panel->id))
                ->sm()
                ->icon('pencil')
                ->loader(false) ?>
            <?= Html::beginForm(Url::toPanelDelete($panel, $contentContainer), 'post', ['class' => 'd-inline']) ?>
                <?= Button::danger(Yii::t('ThiscoveryFormsModule.base', 'Delete panel'))
                    ->confirm(Yii::t('ThiscoveryFormsModule.base', 'Delete this panel and its members? Forms that enrol into it will need a new panel chosen.'))
                    ->submit()
                    ->sm()
                    ->icon('trash')
                    ->loader(false) ?>
            <?= Html::endForm() ?>
        </div>
    </div>

    <?php $extraFields = $panel->getMemberFields(); ?>
    <div class="cf-folder-form__card">
        <h2 class="cf-folder-form__h"><?= Yii::t('ThiscoveryFormsModule.base', 'What this panel stores') ?></h2>
        <p class="cf-list-sub mb-2">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Every member has first name, last name, and email. Completions are listed on the member record. Extra fields you add here can be searched, imported in CSV, piped as {{member.key}}, and dropped onto a survey.') ?>
        </p>
        <ul class="cf-panel-field-list">
            <li><?= Yii::t('ThiscoveryFormsModule.base', 'First name, last name, email') ?></li>
            <li><?= Yii::t('ThiscoveryFormsModule.base', 'Source (signed-in account or email invite), consent date, joined date') ?></li>
            <li><?= Yii::t('ThiscoveryFormsModule.base', 'Form completions from surveys that use this panel') ?></li>
            <?php foreach ($extraFields as $extra): ?>
                <li>
                    <?= Html::encode($extra['label']) ?>
                    <code>{{member.<?= Html::encode($extra['key']) ?>}}</code>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$extraFields): ?>
            <p class="cf-hint text-muted mb-0">
                <?= Yii::t('ThiscoveryFormsModule.base', 'No extra fields yet.') ?>
                <?= Html::a(Yii::t('ThiscoveryFormsModule.base', 'Add fields on Edit panel'), Url::toPanelEdit($contentContainer, $panel->id)) ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if (!empty($wavesOnPanel)): ?>
        <?php
        $waves = $waves ?? [];
        ?>
        <div class="cf-folder-form__card">
            <h2 class="cf-folder-form__h"><?= Yii::t('ThiscoveryFormsModule.base', 'Waves') ?></h2>
            <p class="cf-list-sub">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Forms that use this panel share these waves. Only one wave can be open at a time.') ?>
            </p>
            <?= Html::beginForm(Url::toPanelWaveSave($panel, $contentContainer), 'post', ['class' => 'cf-inline-form mb-3']) ?>
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Title') ?></label>
                        <input type="text" name="title" class="form-control" placeholder="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Wave 2')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Opens') ?></label>
                        <input type="datetime-local" name="opens_at" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Closes') ?></label>
                        <input type="datetime-local" name="closes_at" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-light"><?= Yii::t('ThiscoveryFormsModule.base', 'Add wave') ?></button>
                    </div>
                </div>
            <?= Html::endForm() ?>
            <?= $this->render('@thiscovery-forms/views/form/_wave_list', [
                'waves' => $waves,
                'statusUrl' => Url::toPanelWaveStatus($panel, $contentContainer),
                'canManage' => true,
            ]) ?>
        </div>
    <?php endif; ?>

    <div class="cf-folder-form__card">
        <h2 class="cf-folder-form__h"><?= Yii::t('ThiscoveryFormsModule.base', 'Add members') ?></h2>
        <p class="cf-list-sub">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Add people who already have an account, enter an email, or import a CSV. Email is required for people who are not on the site yet.') ?>
        </p>

        <?= Html::beginForm(Url::toPanelMemberAdd($panel, $contentContainer), 'post', ['class' => 'mb-3']) ?>
            <div class="form-group">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'People on this site') ?></label>
                <?= UserPickerField::widget([
                    'id' => 'cf-panel-users',
                    'name' => 'userGuids',
                    'selection' => [],
                    'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Add users'),
                ]) ?>
            </div>
            <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Add selected people'))->submit()->sm() ?>
        <?= Html::endForm() ?>

        <?= Html::beginForm(Url::toPanelMemberAdd($panel, $contentContainer), 'post', ['class' => 'mb-3']) ?>
            <div class="row g-3">
                <div class="col-md-4 form-group mb-0">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Email') ?></label>
                    <input type="email" name="email" class="form-control" required
                           placeholder="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'name@nhs.net')) ?>">
                </div>
                <div class="col-md-4 form-group mb-0">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'First name') ?></label>
                    <input type="text" name="first_name" class="form-control">
                </div>
                <div class="col-md-4 form-group mb-0">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Last name') ?></label>
                    <input type="text" name="last_name" class="form-control">
                </div>
                <?php foreach ($extraFields as $extra): ?>
                    <div class="col-md-4 form-group mb-0">
                        <label class="cf-label"><?= Html::encode($extra['label']) ?></label>
                        <?php if ($extra['type'] === 'dropdown' && $extra['options']): ?>
                            <?= Html::dropDownList('attrs[' . $extra['key'] . ']', '', array_combine($extra['options'], $extra['options']), [
                                'class' => 'form-control',
                                'prompt' => Yii::t('ThiscoveryFormsModule.base', 'Select…'),
                            ]) ?>
                        <?php else: ?>
                            <input type="<?= $extra['type'] === 'number' || $extra['type'] === 'date' || $extra['type'] === 'email' ? $extra['type'] : 'text' ?>"
                                   name="attrs[<?= Html::encode($extra['key']) ?>]" class="form-control">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-3">
                <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Add member'))->submit()->sm() ?>
            </div>
        <?= Html::endForm() ?>

        <hr>
        <p class="cf-hint text-muted">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Include a header row. Email is required. Extra columns use the field key (for example {keys}).', [
                'keys' => $extraFields ? implode(', ', array_column($extraFields, 'key')) : 'email, first_name, last_name',
            ]) ?>
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Download sample CSV'))
                ->link(Url::toPanelSample($contentContainer, $panel))
                ->sm()
                ->icon('download')
                ->loader(false) ?>
        </p>
        <?= Html::beginForm(Url::toPanelImport($panel, $contentContainer), 'post', ['enctype' => 'multipart/form-data']) ?>
            <div class="row g-3">
                <div class="col-md-6 form-group mb-0">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'CSV file') ?></label>
                    <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv,text/plain">
                </div>
                <div class="col-md-6 form-group mb-0">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Or paste CSV') ?>
                        <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
                    </label>
                    <textarea name="csv_text" class="form-control" rows="3" placeholder="email,first_name,last_name"></textarea>
                </div>
            </div>
            <div class="mt-3">
                <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Import CSV'))->submit()->sm() ?>
            </div>
        <?= Html::endForm() ?>
    </div>

    <form method="get" action="<?= Html::encode($viewUrl) ?>" class="cf-list-filters">
        <div class="cf-list-filters__search">
            <i class="fa fa-search" aria-hidden="true"></i>
            <input type="search" name="q" value="<?= Html::encode($filters['q']) ?>"
                   placeholder="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Search name, email, or extra fields')) ?>"
                   aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Search members')) ?>">
        </div>
        <button type="submit" class="btn btn-default btn-sm"><?= Yii::t('ThiscoveryFormsModule.base', 'Search') ?></button>
        <?php if ($hasFilters): ?>
            <a class="btn btn-link btn-sm" href="<?= Html::encode($viewUrl) ?>"><?= Yii::t('ThiscoveryFormsModule.base', 'Clear') ?></a>
        <?php endif; ?>
    </form>

    <?php if (!$members): ?>
        <div class="cf-list-empty">
            <i class="fa fa-user" aria-hidden="true"></i>
            <h3><?= $hasFilters
                ? Yii::t('ThiscoveryFormsModule.base', 'No matching members.')
                : Yii::t('ThiscoveryFormsModule.base', 'No members yet.') ?></h3>
            <p><?= $hasFilters
                ? Yii::t('ThiscoveryFormsModule.base', 'Try a different search or clear the filters.')
                : Yii::t('ThiscoveryFormsModule.base', 'Add people above, or enrol them when they complete a form.') ?></p>
        </div>
    <?php else: ?>
        <div class="cf-form-table-wrap">
            <table class="cf-form-table">
                <thead>
                <tr>
                    <th class="cf-form-table__actions-col"><?= Yii::t('ThiscoveryFormsModule.base', 'Actions') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Member') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Email') ?></th>
                    <?php foreach (array_slice($extraFields, 0, 3) as $col): ?>
                        <th><?= Html::encode($col['label']) ?></th>
                    <?php endforeach; ?>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Source') ?></th>
                    <th class="cf-form-table__date-col"><?= Yii::t('ThiscoveryFormsModule.base', 'Joined') ?></th>
                    <th class="cf-form-table__num-col"><?= Yii::t('ThiscoveryFormsModule.base', 'Completions') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($members as $member): ?>
                    <?php /** @var FormPanelMember $member */ ?>
                    <?php
                    $count = (int)($activityCounts[(int)$member->id] ?? 0);
                    $latest = $latestActivity[(int)$member->id] ?? null;
                    $memberUrl = Url::toPanelMember($member, $contentContainer);
                    ?>
                    <tr class="cf-form-row">
                        <td class="cf-form-table__actions-col">
                            <div class="cf-form-row__actions">
                                <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Open'))
                                    ->link($memberUrl)
                                    ->sm()
                                    ->loader(false) ?>
                                <?= Html::beginForm(Url::toPanelMemberRemove($panel, $contentContainer), 'post', ['class' => 'cf-form-row__delete']) ?>
                                    <?= Html::hiddenInput('member_id', $member->id) ?>
                                    <?= Button::danger()
                                        ->confirm(Yii::t('ThiscoveryFormsModule.base', 'Remove this person from the panel? Their completion history on this panel will stay.'))
                                        ->submit()
                                        ->sm()
                                        ->icon('trash')
                                        ->tooltip(Yii::t('ThiscoveryFormsModule.base', 'Remove'))
                                        ->loader(false) ?>
                                <?= Html::endForm() ?>
                            </div>
                        </td>
                        <td class="cf-form-table__form-col">
                            <div class="cf-form-row__title">
                                <?= Html::a(Html::encode($member->getDisplayLabel()), $memberUrl) ?>
                            </div>
                            <?php if ($latest): ?>
                                <div class="cf-form-row__desc">
                                    <?= Html::encode($latest->form ? $latest->form->title : Yii::t('ThiscoveryFormsModule.base', 'Form')) ?>
                                    · <?= Html::encode(Yii::$app->formatter->asDatetime($latest->created_at, 'short')) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= Html::encode((string)$member->email) ?></td>
                        <?php foreach (array_slice($extraFields, 0, 3) as $col): ?>
                            <td><?= Html::encode(\humhub\modules\thiscoveryForms\services\PanelFieldService::memberValue($member, $col['key']) ?: '—') ?></td>
                        <?php endforeach; ?>
                        <td>
                            <span class="cf-form-row__chip">
                                <?= $member->user_id
                                    ? Yii::t('ThiscoveryFormsModule.base', 'Signed-in user')
                                    : Yii::t('ThiscoveryFormsModule.base', 'Email invite') ?>
                            </span>
                        </td>
                        <td class="cf-form-table__date-col">
                            <?= $member->created_at ? Html::encode(Yii::$app->formatter->asDatetime($member->created_at, 'short')) : '—' ?>
                        </td>
                        <td class="cf-form-table__num-col">
                            <span class="cf-form-row__stat"><?= (int)$count ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="cf-list-pager">
            <div class="cf-list-pager__count">
                <?= Yii::t('ThiscoveryFormsModule.base', '{n,plural,=0{No members}=1{1 member} other{# members}}', ['n' => $total]) ?>
            </div>
            <?= LinkPager::widget(['pagination' => $dataProvider->pagination]) ?>
        </div>
    <?php endif; ?>
</div>
