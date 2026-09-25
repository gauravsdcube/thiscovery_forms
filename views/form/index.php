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
$total = (int) $dataProvider->getTotalCount();
$currentFolder = $folderBrowse['folder'] instanceof FormFolder ? $folderBrowse['folder'] : null;
$searching = $filters['q'] !== '';
$viewKey = (string) Yii::$app->request->get('view', '');
$showTemplates = $viewKey === 'templates';

$folderWalk = FolderService::walkVisible($contentContainer);
$formCounts = FolderService::liveCountsByFolder($contentContainer);
$unfiledCount = (int) ($formCounts[0] ?? 0);

$childFolders = $currentFolder ? ($folderBrowse['children'] ?? []) : [];
if ($searching && $childFolders) {
    $q = mb_strtolower($filters['q']);
    $childFolders = array_values(array_filter($childFolders, static function (FormFolder $folder) use ($q) {
        return str_contains(mb_strtolower($folder->name . ' ' . (string) $folder->description), $q);
    }));
}

$matchesTemplate = static function (CustomForm $form) use ($filters): bool {
    if ($filters['kind'] !== '' && (string) $form->kind !== (string) $filters['kind']) {
        return false;
    }
    if ($filters['q'] !== '') {
        $haystack = mb_strtolower($form->title . ' ' . strip_tags((string) $form->description));
        if (!str_contains($haystack, mb_strtolower($filters['q']))) {
            return false;
        }
    }
    return true;
};
$tableTemplates = $showTemplates ? array_values(array_filter($templates, $matchesTemplate)) : [];

$indexParams = [];
if ($showTemplates) {
    $indexParams['view'] = 'templates';
} elseif ($currentFolder) {
    $indexParams['folder'] = (int) $currentFolder->id;
}
$clearUrl = Url::toManageIndex($contentContainer, $indexParams);
$createParams = $currentFolder && !$showTemplates ? ['folder' => (int) $currentFolder->id] : [];
$shown = $showTemplates ? count($tableTemplates) : (int) $dataProvider->getCount();
$nothingYet = !$hasFilters && !$currentFolder && !$showTemplates
    && $unfiledCount === 0 && $folderWalk === [] && $templates === [];

$folderTitle = $showTemplates
    ? Yii::t('ThiscoveryFormsModule.base', 'Templates')
    : ($currentFolder
        ? $currentFolder->name
        : Yii::t('ThiscoveryFormsModule.base', 'Top-level forms'));

$folderLink = static function (array $params, bool $active, string $icon, string $name, int $count, int $depth = 0) use ($contentContainer) {
    $href = Url::toManageIndex($contentContainer, $params);
    $depthClass = $depth > 0 ? ' cf-cms-folder--nested' : '';
    return '<a class="cf-cms-folder' . $depthClass . ($active ? ' is-active' : '') . '" href="' . Html::encode($href) . '"'
        . ($depth > 0 ? ' style="--cf-depth:' . (int) $depth . '"' : '') . '>'
        . '<span class="cf-cms-folder__icon"><i class="fa ' . Html::encode($icon) . '"></i></span>'
        . '<span class="cf-cms-folder__body">'
        . '<span class="cf-cms-folder__name">' . Html::encode($name) . '</span>'
        . '<span class="cf-cms-folder__count">' . Yii::t('ThiscoveryFormsModule.base', '{n,plural,=0{No items}=1{1 item} other{# items}}', ['n' => $count]) . '</span>'
        . '</span></a>';
};
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
            <?php if (!empty($folderBrowse['canCreateFolder']) && !$showTemplates): ?>
                <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'New folder'))
                    ->link(Url::toFolderEdit($contentContainer, null, $currentFolder ? ['parent' => (int) $currentFolder->id] : []))
                    ->icon('folder')
                    ->loader(false) ?>
            <?php endif; ?>
            <?php if ($canCreate && !$showTemplates): ?>
                <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Create form'))
                    ->link(Url::toCreate($contentContainer, $createParams))
                    ->icon('plus')
                    ->loader(false) ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($nothingYet): ?>
        <div class="cf-list-empty">
            <i class="fa fa-wpforms"></i>
            <h3><?= Yii::t('ThiscoveryFormsModule.base', 'No forms yet.') ?></h3>
            <?php if ($canCreate): ?>
                <p><?= Yii::t('ThiscoveryFormsModule.base', 'Create a folder to organise forms, or a form without a folder.') ?></p>
                <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Create form'))
                    ->link(Url::toCreate($contentContainer, $createParams))
                    ->loader(false) ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="cf-cms">
            <aside class="cf-cms-rail" aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Folders')) ?>">
                <div class="cf-cms-rail__label"><?= Yii::t('ThiscoveryFormsModule.base', 'Forms') ?></div>
                <?= $folderLink(
                    [],
                    !$showTemplates && !$currentFolder,
                    'fa-file-text-o',
                    Yii::t('ThiscoveryFormsModule.base', 'Top-level forms'),
                    $unfiledCount
                ) ?>

                <?php if ($folderWalk): ?>
                    <div class="cf-cms-rail__label"><?= Yii::t('ThiscoveryFormsModule.base', 'Folders') ?></div>
                    <?php foreach ($folderWalk as $row): ?>
                        <?php
                        /** @var FormFolder $navFolder */
                        $navFolder = $row['folder'];
                        $fid = (int) $navFolder->id;
                        echo $folderLink(
                            ['folder' => $fid],
                            !$showTemplates && $currentFolder && (int) $currentFolder->id === $fid,
                            'fa-folder',
                            $navFolder->name,
                            (int) ($formCounts[$fid] ?? 0),
                            (int) $row['depth']
                        );
                        ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($templates): ?>
                    <div class="cf-cms-rail__label"><?= Yii::t('ThiscoveryFormsModule.base', 'Templates') ?></div>
                    <?= $folderLink(
                        ['view' => 'templates'],
                        $showTemplates,
                        'fa-files-o',
                        Yii::t('ThiscoveryFormsModule.base', 'Templates'),
                        count($templates)
                    ) ?>
                <?php endif; ?>
            </aside>

            <div class="cf-cms-main">
                <div class="cf-cms-main__head">
                    <div>
                        <h2 class="cf-cms-main__title"><?= Html::encode($folderTitle) ?></h2>
                        <?php if ($currentFolder && $currentFolder->description && !$showTemplates): ?>
                            <p class="cf-cms-main__meta"><?= Html::encode($currentFolder->description) ?></p>
                        <?php endif; ?>
                        <?php
                        $crumbs = [];
                        if ($currentFolder && !$showTemplates) {
                            $crumbs[] = [
                                'label' => Yii::t('ThiscoveryFormsModule.base', 'Top-level forms'),
                                'url' => Url::toManageIndex($contentContainer),
                            ];
                            foreach ($currentFolder->getAncestors() as $crumb) {
                                $crumbs[] = [
                                    'label' => $crumb->name,
                                    'url' => Url::toManageIndex($contentContainer, ['folder' => (int) $crumb->id]),
                                ];
                            }
                        }
                        ?>
                        <?php if ($crumbs): ?>
                            <nav class="cf-folder-crumbs" aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Folders')) ?>">
                                <?php foreach ($crumbs as $i => $crumb): ?>
                                    <?php if ($i): ?><span class="cf-folder-crumbs__sep">/</span><?php endif; ?>
                                    <a href="<?= Html::encode($crumb['url']) ?>"><?= Html::encode($crumb['label']) ?></a>
                                <?php endforeach; ?>
                                <span class="cf-folder-crumbs__sep">/</span>
                                <span><?= Html::encode($folderTitle) ?></span>
                            </nav>
                        <?php endif; ?>
                    </div>
                    <div class="cf-folder-toolbar">
                        <?php if ($currentFolder && !empty($folderBrowse['canManageFolders']) && !$showTemplates): ?>
                            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Folder settings'))
                                ->link(Url::toFolderEdit($contentContainer, $currentFolder->id))
                                ->sm()
                                ->icon('cog')
                                ->loader(false) ?>
                            <?= Html::beginForm(Url::toFolderDelete($contentContainer, (int) $currentFolder->id), 'post', ['class' => 'cf-folder-toolbar__delete']) ?>
                            <?= Button::danger(Yii::t('ThiscoveryFormsModule.base', 'Delete folder'))
                                ->confirm(Yii::t('ThiscoveryFormsModule.base', 'Delete this folder and its subfolders? Forms inside will be moved to Unfiled.'))
                                ->submit()
                                ->sm()
                                ->icon('trash')
                                ->loader(false) ?>
                            <?= Html::endForm() ?>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="get" action="<?= Html::encode(Url::toManageIndex($contentContainer)) ?>" class="cf-list-filters" data-pjax-prevent>
                    <?php if (!empty(Yii::$app->request->get('sort'))): ?>
                        <?= Html::hiddenInput('sort', Yii::$app->request->get('sort')) ?>
                    <?php endif; ?>
                    <?php if ($showTemplates): ?>
                        <input type="hidden" name="view" value="templates">
                    <?php endif; ?>
                    <?php if ($currentFolder && !$showTemplates): ?>
                        <input type="hidden" name="folder" value="<?= (int) $currentFolder->id ?>">
                    <?php endif; ?>
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
                    <?php if (!$showTemplates): ?>
                        <select name="status" aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Status')) ?>" data-cf-auto-submit>
                            <option value=""><?= Yii::t('ThiscoveryFormsModule.base', 'All statuses') ?></option>
                            <?php foreach ($statusLabels as $status => $label): ?>
                                <option value="<?= (int) $status ?>"<?= (string) $filters['status'] === (string) $status ? ' selected' : '' ?>>
                                    <?= Html::encode($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="per-page" aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Per page')) ?>" data-cf-auto-submit>
                            <?php foreach (FormListService::PAGE_SIZES as $size): ?>
                                <option value="<?= (int) $size ?>"<?= (int) $filters['pageSize'] === (int) $size ? ' selected' : '' ?>>
                                    <?= Yii::t('ThiscoveryFormsModule.base', '{n} per page', ['n' => $size]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-default btn-sm"><?= Yii::t('ThiscoveryFormsModule.base', 'Search') ?></button>
                    <?php if ($hasFilters): ?>
                        <a class="btn btn-link btn-sm" href="<?= Html::encode($clearUrl) ?>"><?= Yii::t('ThiscoveryFormsModule.base', 'Clear') ?></a>
                    <?php endif; ?>
                </form>

                <?php
                $hasFolderCards = !$showTemplates && $childFolders;
                ?>
                <?php if ($hasFolderCards): ?>
                    <div class="cf-folder-grid">
                        <?php foreach ($childFolders as $child): ?>
                            <?php
                            $cid = (int) $child->id;
                            $formCount = (int) ($formCounts[$cid] ?? 0);
                            $subCount = FolderService::childCount($child);
                            ?>
                            <a class="cf-folder-card" href="<?= Html::encode(Url::toManageIndex($contentContainer, ['folder' => $cid])) ?>">
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

                <?php if ($shown === 0): ?>
                    <?php if (!$hasFolderCards || $hasFilters): ?>
                        <div class="cf-list-empty">
                            <i class="fa <?= $hasFilters ? 'fa-search' : ($showTemplates ? 'fa-files-o' : 'fa-file-o') ?>"></i>
                            <h3><?= $hasFilters
                                ? Yii::t('ThiscoveryFormsModule.base', 'No matching forms.')
                                : ($showTemplates
                                    ? Yii::t('ThiscoveryFormsModule.base', 'No templates yet.')
                                    : ($currentFolder
                                        ? Yii::t('ThiscoveryFormsModule.base', 'This folder is empty.')
                                        : Yii::t('ThiscoveryFormsModule.base', 'No top-level forms.'))) ?></h3>
                            <p><?= $hasFilters
                                ? Yii::t('ThiscoveryFormsModule.base', 'Try a different search or clear the filters.')
                                : ($currentFolder && !$showTemplates
                                    ? Yii::t('ThiscoveryFormsModule.base', 'Create a subfolder or a form here.')
                                    : Yii::t('ThiscoveryFormsModule.base', 'Create a form here, or open a folder on the left.')) ?></p>
                        </div>
                    <?php endif; ?>
                <?php elseif ($showTemplates): ?>
                    <div class="cf-form-table-wrap">
                        <table class="cf-form-table">
                            <thead>
                            <tr>
                                <th class="cf-form-table__actions-col"><?= Yii::t('ThiscoveryFormsModule.base', 'Actions') ?></th>
                                <th class="cf-form-table__form-col"><?= Yii::t('ThiscoveryFormsModule.base', 'Template') ?></th>
                                <th class="cf-form-table__type-col"><?= Yii::t('ThiscoveryFormsModule.base', 'Type') ?></th>
                                <th class="cf-form-table__num-col"><?= Yii::t('ThiscoveryFormsModule.base', 'Fields') ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($tableTemplates as $template): ?>
                                <tr class="cf-form-row">
                                    <td class="cf-form-table__actions-col">
                                        <div class="cf-form-row__actions">
                                            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Edit'))
                                                ->link(Url::toEdit($template))
                                                ->sm()
                                                ->icon('pencil')
                                                ->loader(false) ?>
                                            <?php if ($canCreate): ?>
                                                <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Use template'))
                                                    ->link(Url::toCreate($contentContainer, ['template' => $template->id]))
                                                    ->sm()
                                                    ->loader(false) ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="cf-form-table__form-col">
                                        <div class="cf-form-row__title">
                                            <?= Html::a(Html::encode($template->title), Url::toEdit($template)) ?>
                                        </div>
                                        <?php if ($template->description): ?>
                                            <div class="cf-form-row__desc">
                                                <?= Html::encode(mb_strimwidth(strip_tags($template->description), 0, 120, '…')) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="cf-form-table__type-col">
                                        <span class="cf-form-row__chip"><?= Html::encode($kindLabels[$template->kind] ?? $template->kind) ?></span>
                                    </td>
                                    <td class="cf-form-table__num-col">
                                        <span class="cf-form-row__stat"><?= count($template->fields) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="cf-list-pager">
                        <div class="cf-list-pager__count">
                            <?= Yii::t('ThiscoveryFormsModule.base', '{n,plural,=1{1 template} other{# templates}}', ['n' => $shown]) ?>
                        </div>
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
                                $fieldCount = (int) ($formModel->field_count ?? count($formModel->fields));
                                $answerCount = (int) ($formModel->answer_count ?? $formModel->getAnswers()->count());
                                $canManage = $formModel->canManage();
                                $canViewAnswers = $formModel->canViewAnswers();
                                $createdAt = $formModel->content->created_at ?? null;
                                $updatedAt = $formModel->content->updated_at ?? null;
                                ?>
                                <tr class="cf-form-row" data-status="<?= (int) $formModel->status ?>">
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
                                                        <option value="<?= (int) $fid ?>"<?= (int) $formModel->folder_id === (int) $fid ? ' selected' : '' ?>><?= Html::encode($label) ?></option>
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
                                                ->listPeriods('form', (int) $formModel->id);
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
                                        <span class="cf-form-row__stat"><?= (int) $fieldCount ?></span>
                                    </td>
                                    <td class="cf-form-table__num-col">
                                        <span class="cf-form-row__stat"><?= (int) $answerCount ?></span>
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
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
$this->registerJs('humhub.require("thiscoveryForms").initListForms();', View::POS_READY);
