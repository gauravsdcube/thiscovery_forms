<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormFolder;
use humhub\modules\thiscoveryForms\services\FolderService;
use humhub\modules\thiscoveryForms\services\FormListService;
use humhub\widgets\bootstrap\Badge;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;
use yii\web\View;
use yii\widgets\LinkPager;

/** @var $dataProvider yii\data\ActiveDataProvider */
/** @var array $filters */
/** @var $contentContainer humhub\modules\content\components\ContentContainerActiveRecord|null */
/** @var $canCreate bool */
/** @var CustomForm[] $templates */
/** @var bool $canConfigure */

ThiscoveryFormsAsset::register($this);
$statusLabels = CustomForm::getStatusLabels();
$kindLabels = CustomForm::getKindLabels();
$templates = $templates ?? [];
$canConfigure = !empty($canConfigure);
$canManagePanels = !empty($canManagePanels) || $canCreate || $canConfigure;
$canViewHelp = !empty($canViewHelp);
$filters = array_merge([
    'q' => '',
    'kind' => '',
    'status' => '',
    'pageSize' => FormListService::DEFAULT_PAGE_SIZE,
    'folder' => 0,
], $filters ?? []);
$folderBrowse = array_merge([
    'folder' => null,
    'folderId' => 0,
    'children' => [],
    'crumbs' => [],
    'tree' => [],
    'viewTree' => [],
    'moveTree' => [],
    'canManageFolders' => false,
    'canCreateFolder' => false,
], $folderBrowse ?? []);
$hasFilters = $filters['q'] !== '' || $filters['kind'] !== '' || $filters['status'] !== '';
$sort = $dataProvider->sort;
$total = (int)$dataProvider->getTotalCount();
$clearParams = !empty($folderBrowse['folderId']) ? ['folder' => (int)$folderBrowse['folderId']] : [];
$clearUrl = Url::toManageIndex($contentContainer, $clearParams);
$createParams = $clearParams;
$currentFolder = $folderBrowse['folder'] instanceof FormFolder ? $folderBrowse['folder'] : null;
$searching = $filters['q'] !== '';
?>

<div class="cf-list-page">
    <div class="cf-list-header">
        <div>
            <h1 class="cf-list-title"><?= Yii::t('ThiscoveryFormsModule.base', 'Forms') ?></h1>
            <p class="cf-list-sub"><?= Yii::t('ThiscoveryFormsModule.base', 'Browse, open, and manage forms in one place.') ?></p>
        </div>
        <div class="cf-list-header__actions">
            <?php if ($canConfigure): ?>
                <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Configuration'))
                    ->link(Url::toAdminSettings())
                    ->icon('cog')
                    ->loader(false) ?>
            <?php endif; ?>
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Dashboard'))
                ->link(Url::toOverview($contentContainer))
                ->icon('bar-chart')
                ->loader(false) ?>
            <?php if (!empty($canViewHelp)): ?>
                <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Help'))
                    ->link(Url::toHelp($contentContainer))
                    ->icon('question-circle')
                    ->loader(false) ?>
            <?php endif; ?>
            <?php if ($canManagePanels): ?>
                <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Panels'))
                    ->link(Url::toPanelIndex($contentContainer))
                    ->icon('users')
                    ->loader(false) ?>
                <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Email templates'))
                    ->link(Url::toEmailTemplateIndex($contentContainer))
                    ->icon('envelope')
                    ->loader(false) ?>
            <?php endif; ?>
            <?php if ($canCreate): ?>
                <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Create form'))
                    ->link(Url::toCreate($contentContainer, $createParams))
                    ->icon('plus')
                    ->loader(false) ?>
            <?php endif; ?>
            <?php if (!empty($folderBrowse['canCreateFolder'])): ?>
                <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'New folder'))
                    ->link(Url::toFolderEdit($contentContainer, null, $currentFolder ? ['parent' => (int)$currentFolder->id] : []))
                    ->icon('folder')
                    ->loader(false) ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($currentFolder || $folderBrowse['crumbs']): ?>
        <nav class="cf-folder-crumbs" aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Folders')) ?>">
            <a href="<?= Html::encode(Url::toManageIndex($contentContainer)) ?>"><?= Yii::t('ThiscoveryFormsModule.base', 'All forms') ?></a>
            <?php foreach ($folderBrowse['crumbs'] as $crumb): ?>
                <span class="cf-folder-crumbs__sep">/</span>
                <a href="<?= Html::encode(Url::toManageIndex($contentContainer, ['folder' => (int)$crumb->id])) ?>"><?= Html::encode($crumb->name) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if ($currentFolder && !empty($folderBrowse['canManageFolders'])): ?>
            <div class="cf-folder-toolbar">
                <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Folder settings'))
                    ->link(Url::toFolderEdit($contentContainer, $currentFolder->id))
                    ->sm()
                    ->icon('cog')
                    ->loader(false) ?>
                <?= Html::beginForm(Url::toFolderDelete($contentContainer, (int)$currentFolder->id), 'post', ['class' => 'cf-folder-toolbar__delete']) ?>
                <?= Button::danger(Yii::t('ThiscoveryFormsModule.base', 'Delete folder'))
                    ->confirm(Yii::t('ThiscoveryFormsModule.base', 'Delete this folder and its subfolders? Forms inside will be moved to Unfiled.'))
                    ->submit()
                    ->sm()
                    ->icon('trash')
                    ->loader(false) ?>
                <?= Html::endForm() ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!$searching && !empty($folderBrowse['children'])): ?>
        <div class="cf-folder-grid">
            <?php foreach ($folderBrowse['children'] as $child): ?>
                <?php
                $formCount = FolderService::formCount($child);
                $subCount = FolderService::childCount($child);
                ?>
                <a class="cf-folder-card" href="<?= Html::encode(Url::toManageIndex($contentContainer, ['folder' => (int)$child->id])) ?>">
                    <span class="cf-folder-card__icon"><i class="fa fa-folder"></i></span>
                    <span class="cf-folder-card__body">
                        <span class="cf-folder-card__name"><?= Html::encode($child->name) ?></span>
                        <span class="cf-folder-card__meta">
                            <?= Yii::t('ThiscoveryFormsModule.base', '{n,plural,=0{No forms}=1{1 form} other{# forms}}', ['n' => $formCount]) ?>
                            <?php if ($subCount): ?>
                                · <?= Yii::t('ThiscoveryFormsModule.base', '{n,plural,=1{1 subfolder} other{# subfolders}}', ['n' => $subCount]) ?>
                            <?php endif; ?>
                        </span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($total === 0 && !$hasFilters && !$currentFolder && empty($folderBrowse['children'])): ?>
        <div class="cf-list-empty">
            <i class="fa fa-wpforms"></i>
            <h3><?= Yii::t('ThiscoveryFormsModule.base', 'No forms yet.') ?></h3>
            <?php if ($canCreate): ?>
                <p><?= Yii::t('ThiscoveryFormsModule.base', 'Create your first form to collect responses from members.') ?></p>
                <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Create form'))
                    ->link(Url::toCreate($contentContainer, $createParams))
                    ->loader(false) ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <form method="get" action="<?= Html::encode(Url::toManageIndex($contentContainer)) ?>" class="cf-list-filters" data-pjax-prevent>
            <?php if (!empty(Yii::$app->request->get('sort'))): ?>
                <?= Html::hiddenInput('sort', Yii::$app->request->get('sort')) ?>
            <?php endif; ?>
            <select name="folder" class="cf-folder-jump" data-cf-auto-submit aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Folder')) ?>">
                <option value=""><?= Yii::t('ThiscoveryFormsModule.base', 'Top level / Unfiled') ?></option>
                <?php foreach (($folderBrowse['viewTree'] ?: $folderBrowse['tree']) as $fid => $label): ?>
                    <option value="<?= (int)$fid ?>"<?= (int)$folderBrowse['folderId'] === (int)$fid ? ' selected' : '' ?>>
                        <?= Html::encode($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="cf-list-filters__search">
                <i class="fa fa-search" aria-hidden="true"></i>
                <input type="search" name="q" value="<?= Html::encode($filters['q']) ?>"
                       placeholder="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Search forms')) ?>"
                       aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Search forms')) ?>">
            </div>
            <select name="kind" aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Type')) ?>" data-cf-auto-submit>
                <option value=""><?= Yii::t('ThiscoveryFormsModule.base', 'All types') ?></option>
                <?php foreach ($kindLabels as $kind => $label): ?>
                    <option value="<?= Html::encode($kind) ?>"<?= $filters['kind'] === $kind ? ' selected' : '' ?>>
                        <?= Html::encode($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="status" aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Status')) ?>" data-cf-auto-submit>
                <option value=""><?= Yii::t('ThiscoveryFormsModule.base', 'All statuses') ?></option>
                <?php foreach ($statusLabels as $status => $label): ?>
                    <option value="<?= (int)$status ?>"<?= (string)$filters['status'] === (string)$status ? ' selected' : '' ?>>
                        <?= Html::encode($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="per-page" aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Per page')) ?>" data-cf-auto-submit>
                <?php foreach (FormListService::PAGE_SIZES as $size): ?>
                    <option value="<?= (int)$size ?>"<?= (int)$filters['pageSize'] === (int)$size ? ' selected' : '' ?>>
                        <?= Yii::t('ThiscoveryFormsModule.base', '{n} per page', ['n' => $size]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-default btn-sm"><?= Yii::t('ThiscoveryFormsModule.base', 'Search') ?></button>
            <?php if ($hasFilters): ?>
                <a class="btn btn-link btn-sm" href="<?= Html::encode($clearUrl) ?>"><?= Yii::t('ThiscoveryFormsModule.base', 'Clear') ?></a>
            <?php endif; ?>
        </form>

        <?php if (!$dataProvider->getCount()): ?>
            <div class="cf-list-empty">
                <i class="fa fa-search"></i>
            <h3><?= $hasFilters
                ? Yii::t('ThiscoveryFormsModule.base', 'No matching forms.')
                : ($currentFolder
                    ? Yii::t('ThiscoveryFormsModule.base', 'No forms in this folder.')
                    : Yii::t('ThiscoveryFormsModule.base', 'No matching forms.')) ?></h3>
            <p><?= $hasFilters
                ? Yii::t('ThiscoveryFormsModule.base', 'Try a different search or clear the filters.')
                : Yii::t('ThiscoveryFormsModule.base', 'Create a form or move one here from another folder.') ?></p>
            </div>
        <?php else: ?>
            <div class="cf-form-table-wrap">
                <table class="cf-form-table">
                    <thead>
                    <tr>
                        <th class="cf-form-table__actions-col"><?= Yii::t('ThiscoveryFormsModule.base', 'Actions') ?></th>
                        <th class="cf-form-table__status-col"><?= $sort->link('status', ['label' => Yii::t('ThiscoveryFormsModule.base', 'Status')]) ?></th>
                        <th class="cf-form-table__form-col"><?= $sort->link('title', ['label' => Yii::t('ThiscoveryFormsModule.base', 'Form')]) ?></th>
                        <th class="cf-form-table__type-col"><?= $sort->link('kind', ['label' => Yii::t('ThiscoveryFormsModule.base', 'Type')]) ?></th>
                        <th class="cf-form-table__num-col"><?= $sort->link('field_count', ['label' => Yii::t('ThiscoveryFormsModule.base', 'Fields')]) ?></th>
                        <th class="cf-form-table__num-col"><?= $sort->link('answer_count', ['label' => Yii::t('ThiscoveryFormsModule.base', 'Submissions')]) ?></th>
                        <th class="cf-form-table__date-col"><?= $sort->link('created_at', ['label' => Yii::t('ThiscoveryFormsModule.base', 'Date created')]) ?></th>
                        <th class="cf-form-table__date-col"><?= $sort->link('updated_at', ['label' => Yii::t('ThiscoveryFormsModule.base', 'Date modified')]) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($dataProvider->getModels() as $formModel): ?>
                        <?php
                        /** @var CustomForm $formModel */
                        $status = $statusLabels[$formModel->status] ?? '';
                        $fieldCount = (int)($formModel->field_count ?? count($formModel->fields));
                        $answerCount = (int)($formModel->answer_count ?? $formModel->getAnswers()->count());
                        $canManage = $formModel->canManage();
                        $canViewAnswers = $formModel->canViewAnswers();
                        $createdAt = $formModel->content->created_at ?? null;
                        $updatedAt = $formModel->content->updated_at ?? null;
                        ?>
                        <tr class="cf-form-row" data-status="<?= (int)$formModel->status ?>">
                            <td class="cf-form-table__actions-col">
                                <div class="cf-form-row__actions">
                                    <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Open'))
                                        ->link(Url::toView($formModel))
                                        ->pjax(!$formModel->hidesHumhubHeader())
                                        ->sm()
                                        ->loader(false) ?>

                                    <?php if ($canManage): ?>
                                        <?= Button::light()
                                            ->link(Url::toEdit($formModel))
                                            ->sm()
                                            ->icon('pencil')
                                            ->tooltip(Yii::t('ThiscoveryFormsModule.base', 'Edit'))
                                            ->loader(false) ?>
                                    <?php endif; ?>

                                    <?php if ($canViewAnswers): ?>
                                        <?= Button::light()
                                            ->link(Url::toDashboard($formModel))
                                            ->sm()
                                            ->icon('bar-chart')
                                            ->tooltip(Yii::t('ThiscoveryFormsModule.base', 'Dashboard'))
                                            ->loader(false) ?>
                                        <?= Button::light()
                                            ->link(Url::toAnswers($formModel))
                                            ->sm()
                                            ->icon('list')
                                            ->tooltip(Yii::t('ThiscoveryFormsModule.base', 'Answers'))
                                            ->loader(false) ?>
                                    <?php endif; ?>

                                <?php if ($canManage && (!empty($folderBrowse['moveTree']) || $formModel->folder_id)): ?>
                                    <?= Html::beginForm(Url::toMoveForm($formModel), 'post', [
                                        'class' => 'cf-move-form',
                                        'data-pjax-prevent' => true,
                                    ]) ?>
                                    <label class="cf-move-form__label"><?= Yii::t('ThiscoveryFormsModule.base', 'Move to') ?></label>
                                    <select name="folder_id" class="cf-move-form__select" data-cf-auto-submit aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Move to folder')) ?>" title="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Move to folder')) ?>">
                                        <option value="0"<?= !$formModel->folder_id ? ' selected' : '' ?>><?= Yii::t('ThiscoveryFormsModule.base', 'Unfiled') ?></option>
                                        <?php foreach ($folderBrowse['moveTree'] as $fid => $label): ?>
                                            <option value="<?= (int)$fid ?>"<?= (int)$formModel->folder_id === (int)$fid ? ' selected' : '' ?>><?= Html::encode($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-light btn-sm"><?= Yii::t('ThiscoveryFormsModule.base', 'Go') ?></button>
                                    <?= Html::endForm() ?>
                                <?php endif; ?>
                                <?php if ($canManage): ?>
                                    <?= Html::beginForm(Url::toDelete($formModel), 'post', [
                                        'class' => 'cf-form-row__delete',
                                        'data-pjax-prevent' => true,
                                    ]) ?>
                                        <?= Button::danger()
                                            ->confirm(Yii::t('ThiscoveryFormsModule.base', 'Delete this form and all submissions?'))
                                            ->submit()
                                            ->sm()
                                            ->icon('trash')
                                            ->tooltip(Yii::t('ThiscoveryFormsModule.base', 'Delete'))
                                            ->loader(false) ?>
                                        <?= Html::endForm() ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="cf-form-table__status-col">
                                <?php if ($formModel->isClosed()): ?>
                                    <?= Badge::danger($status) ?>
                                <?php elseif ($formModel->isDraft()): ?>
                                    <?= Badge::warning($status) ?>
                                <?php else: ?>
                                    <?= Badge::success($status) ?>
                                <?php endif; ?>
                                <?php
                                if (\humhub\modules\thiscoveryForms\services\FormVersionService::isAvailable() && $formModel->id) {
                                    $periods = (new \humhub\modules\thiscoveryVersioning\services\VersioningService())
                                        ->openPeriods()
                                        ->listPeriods('form', (int)$formModel->id);
                                    $latest = $periods[0] ?? null;
                                    if ($latest) {
                                        $label = $latest->closed_at
                                            ? Yii::t('ThiscoveryFormsModule.base', 'Last open: {from} – {to}', [
                                                'from' => Yii::$app->formatter->asDate($latest->opened_at, 'short'),
                                                'to' => Yii::$app->formatter->asDate($latest->closed_at, 'short'),
                                            ])
                                            : Yii::t('ThiscoveryFormsModule.base', 'Open since {from}', [
                                                'from' => Yii::$app->formatter->asDate($latest->opened_at, 'short'),
                                            ]);
                                        echo '<div class="cf-form-row__period text-muted small">' . Html::encode($label) . '</div>';
                                    }
                                }
                                ?>
                            </td>
                            <td class="cf-form-table__form-col">
                                <div class="cf-form-row__title">
                                    <?= Html::a(Html::encode($formModel->title), Url::toView($formModel), $formModel->fillHtmlOptions()) ?>
                                    <?php if ($formModel->show_in_menu): ?>
                                        <span class="cf-form-row__chip cf-form-row__chip--icon" title="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'In menu')) ?>">
                                            <i class="fa fa-bars"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($formModel->description): ?>
                                    <div class="cf-form-row__desc">
                                        <?= Html::encode(mb_strimwidth(strip_tags($formModel->description), 0, 120, '…')) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="cf-form-table__type-col">
                                <span class="cf-form-row__chip"><?= Html::encode($kindLabels[$formModel->kind] ?? $formModel->kind) ?></span>
                            </td>
                            <td class="cf-form-table__num-col">
                                <span class="cf-form-row__stat"><?= (int)$fieldCount ?></span>
                            </td>
                            <td class="cf-form-table__num-col">
                                <span class="cf-form-row__stat"><?= (int)$answerCount ?></span>
                            </td>
                            <td class="cf-form-table__date-col">
                                <?= $createdAt ? Html::encode(Yii::$app->formatter->asDatetime($createdAt, 'short')) : '—' ?>
                            </td>
                            <td class="cf-form-table__date-col">
                                <?= $updatedAt ? Html::encode(Yii::$app->formatter->asDatetime($updatedAt, 'short')) : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="cf-list-pager">
                <div class="cf-list-pager__count">
                    <?= Yii::t('ThiscoveryFormsModule.base', '{n,plural,=1{1 form} other{# forms}}', ['n' => $total]) ?>
                </div>
                <?= LinkPager::widget(['pagination' => $dataProvider->pagination]) ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($templates)): ?>
        <h2 class="cf-create-wizard__h"><?= Yii::t('ThiscoveryFormsModule.base', 'Templates') ?></h2>
        <ul class="cf-template-list">
            <?php foreach ($templates as $template): ?>
                <li>
                    <a href="<?= Html::encode(Url::toEdit($template)) ?>">
                        <strong><?= Html::encode($template->title) ?></strong>
                        <span class="text-muted">
                            <?= Html::encode($kindLabels[$template->kind] ?? $template->kind) ?>
                            · <?= Yii::t('ThiscoveryFormsModule.base', '{n} fields', ['n' => count($template->fields)]) ?>
                        </span>
                    </a>
                    <?php if ($canCreate): ?>
                        · <?= Html::a(
                            Yii::t('ThiscoveryFormsModule.base', 'Use template'),
                            Url::toCreate($contentContainer, array_merge($createParams, ['template' => $template->id]))
                        ) ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php
$this->registerJs('humhub.require("thiscoveryForms").initListForms();', View::POS_READY);
