<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\FormFolder;
use humhub\modules\user\models\Group;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var FormFolder $folder */
/** @var bool $isNew */
/** @var $contentContainer */
/** @var array $parentOptions */
/** @var Group[] $groups */
/** @var array $aclMap */

ThiscoveryFormsAsset::register($this);
$parentOptions = ['' => Yii::t('ThiscoveryFormsModule.base', 'No parent (top level)')] + ($parentOptions ?? []);
?>

<div class="cf-list-page" id="cf-folder-edit">
    <div class="cf-list-header">
        <div>
            <h1 class="cf-list-title">
                <?= $isNew
                    ? Yii::t('ThiscoveryFormsModule.base', 'New folder')
                    : Yii::t('ThiscoveryFormsModule.base', 'Edit folder') ?>
            </h1>
            <p class="cf-list-sub">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Use folders to group forms by subject or client. Subfolders inherit permissions unless you set your own.') ?>
            </p>
        </div>
        <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Back to forms'))
            ->link(Url::toManageIndex($contentContainer, $folder->parent_id ? ['folder' => (int)$folder->parent_id] : []))
            ->icon('arrow-left')
            ->loader(false) ?>
    </div>

    <?= Html::beginForm(Url::toFolderEdit($contentContainer, $isNew ? null : $folder->id, $isNew && $folder->parent_id ? ['parent' => (int)$folder->parent_id] : []), 'post', [
        'class' => 'cf-folder-form',
    ]) ?>

    <div class="cf-folder-form__card">
        <div class="form-group">
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Folder name') ?></label>
            <?= Html::activeTextInput($folder, 'name', [
                'class' => 'form-control',
                'required' => true,
                'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'e.g. Client A or Recruitment'),
            ]) ?>
        </div>
        <div class="form-group">
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Description') ?>
                <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
            </label>
            <?= Html::activeTextarea($folder, 'description', ['class' => 'form-control', 'rows' => 2]) ?>
        </div>
        <div class="form-group">
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Parent folder') ?></label>
            <?= Html::activeDropDownList($folder, 'parent_id', $parentOptions, ['class' => 'form-control']) ?>
        </div>
        <div class="form-group cf-checks">
            <label>
                <?= Html::activeCheckbox($folder, 'inherit_acl', [
                    'label' => false,
                    'id' => 'cf-inherit-acl',
                ]) ?>
                <?= Yii::t('ThiscoveryFormsModule.base', 'Inherit permissions from parent') ?>
            </label>
            <p class="cf-field-note">
                <?= Yii::t('ThiscoveryFormsModule.base', 'When inherited, a top-level folder uses the same access as Forms admin. Site administrators always have access.') ?>
            </p>
        </div>
    </div>

    <div class="cf-folder-form__card" id="cf-folder-acl"<?= (int)$folder->inherit_acl ? ' hidden' : '' ?>>
        <h2 class="cf-folder-form__h"><?= Yii::t('ThiscoveryFormsModule.base', 'Folder permissions') ?></h2>
        <p class="cf-list-sub">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Choose which groups can see this folder, create forms in it, or manage it. Manage includes moving forms and editing these permissions.') ?>
        </p>
        <div class="cf-form-table-wrap">
            <table class="cf-form-table cf-acl-table">
                <thead>
                <tr>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Group') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'View') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Create forms') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Manage folder') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($groups as $group): ?>
                    <?php
                    $gid = (int)$group->id;
                    $acl = $aclMap[$gid] ?? null;
                    ?>
                    <tr>
                        <td><?= Html::encode($group->name) ?></td>
                        <td><input type="checkbox" name="acl[<?= $gid ?>][view]" value="1"<?= $acl && $acl->can_view ? ' checked' : '' ?>></td>
                        <td><input type="checkbox" name="acl[<?= $gid ?>][create]" value="1"<?= $acl && $acl->can_create ? ' checked' : '' ?>></td>
                        <td><input type="checkbox" name="acl[<?= $gid ?>][manage]" value="1"<?= $acl && $acl->can_manage ? ' checked' : '' ?>></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="cf-folder-form__actions">
        <?= Button::save($isNew
            ? Yii::t('ThiscoveryFormsModule.base', 'Create folder')
            : Yii::t('ThiscoveryFormsModule.base', 'Save folder'))->submit() ?>
        <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Cancel'))
            ->link(Url::toManageIndex($contentContainer, !$isNew && $folder->id ? ['folder' => (int)$folder->id] : ($folder->parent_id ? ['folder' => (int)$folder->parent_id] : [])))
            ->loader(false) ?>
    </div>
    <?= Html::endForm() ?>
</div>

<?php
$this->registerJs(<<<'JS'
(function() {
    var box = document.getElementById('cf-inherit-acl');
    var panel = document.getElementById('cf-folder-acl');
    if (!box || !panel) { return; }
    var sync = function() { panel.hidden = box.checked; };
    box.addEventListener('change', sync);
    sync();
})();
JS
, \yii\web\View::POS_READY);
