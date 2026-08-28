<?php

use humhub\modules\thiscoveryEditor\widgets\EditorField;
use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormLibraryItem;
use humhub\modules\thiscoveryForms\services\FolderService;
use humhub\modules\thiscoveryForms\services\EmailTemplateService;
use humhub\modules\thiscoveryForms\services\PanelFieldService;
use humhub\modules\thiscoveryForms\services\PanelService;
use humhub\modules\thiscoveryForms\services\RespondentMetaService;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var bool $isNew */
/** @var $contentContainer */
/** @var $fields */

ThiscoveryFormsAsset::register($this);
if (class_exists(\humhub\modules\thiscoveryMapping\assets\MappingFormAsset::class) && Yii::$app->getModule('thiscovery-mapping')) {
    \humhub\modules\thiscoveryMapping\assets\MappingFormAsset::register($this);
}

$isPoll = $formModel->isPoll();
$enrolPanels = (new PanelService())->listAvailableForContainer($contentContainer ? $contentContainer->contentcontainer_id : null);
$emailTemplateOptions = (new EmailTemplateService())->optionsForContainer($contentContainer ? $contentContainer->contentcontainer_id : null);
$enrolPanelOptions = ['' => Yii::t('ThiscoveryFormsModule.base', 'Choose a panel')];
foreach ($enrolPanels as $enrolPanel) {
    $enrolPanelOptions[(int)$enrolPanel->id] = $enrolPanel->title;
}
if ((int)$formModel->enrol_panel_id && !isset($enrolPanelOptions[(int)$formModel->enrol_panel_id])) {
    $attached = \humhub\modules\thiscoveryForms\models\FormPanel::findOne((int)$formModel->enrol_panel_id);
    if ($attached) {
        $enrolPanelOptions[(int)$attached->id] = $attached->title;
    }
}
$allowedTypes = $formModel->getAllowedFieldTypes();
$palette = [
    ['type' => FormField::TYPE_RICH_TEXT, 'icon' => 'fa-paragraph', 'group' => 'content'],
    ['type' => FormField::TYPE_HTML, 'icon' => 'fa-code', 'group' => 'content'],
    ['type' => FormField::TYPE_PAGE_BREAK, 'icon' => 'fa-files-o', 'group' => 'content'],
    ['type' => FormField::TYPE_QUESTION_GROUP, 'icon' => 'fa-object-group', 'group' => 'content'],
    ['type' => FormField::TYPE_TEXT, 'icon' => 'fa-font', 'group' => 'input'],
    ['type' => FormField::TYPE_TEXTAREA, 'icon' => 'fa-align-left', 'group' => 'input'],
    ['type' => FormField::TYPE_NUMBER, 'icon' => 'fa-hashtag', 'group' => 'input'],
    ['type' => FormField::TYPE_EMAIL, 'icon' => 'fa-envelope-o', 'group' => 'input'],
    ['type' => FormField::TYPE_DATE, 'icon' => 'fa-calendar', 'group' => 'input'],
    ['type' => FormField::TYPE_DROPDOWN, 'icon' => 'fa-caret-square-o-down', 'group' => 'choice'],
    ['type' => FormField::TYPE_RADIO, 'icon' => 'fa-dot-circle-o', 'group' => 'choice'],
    ['type' => FormField::TYPE_CHECKBOX, 'icon' => 'fa-check-square-o', 'group' => 'choice'],
    ['type' => FormField::TYPE_RATING, 'icon' => 'fa-star', 'group' => 'choice'],
    ['type' => FormField::TYPE_RANKING, 'icon' => 'fa-sort-amount-desc', 'group' => 'choice'],
    ['type' => FormField::TYPE_GRID_SINGLE, 'icon' => 'fa-th', 'group' => 'research'],
    ['type' => FormField::TYPE_GRID_MULTI, 'icon' => 'fa-th-large', 'group' => 'research'],
    ['type' => FormField::TYPE_BEST_WORST, 'icon' => 'fa-exchange', 'group' => 'research'],
    ['type' => FormField::TYPE_MAXDIFF, 'icon' => 'fa-balance-scale', 'group' => 'research'],
    ['type' => FormField::TYPE_DRILLDOWN, 'icon' => 'fa-sitemap', 'group' => 'research'],
    ['type' => FormField::TYPE_IMAGE_AREA, 'icon' => 'fa-picture-o', 'group' => 'research'],
    ['type' => FormField::TYPE_MAP, 'icon' => 'fa-map-marker', 'group' => 'research'],
    ['type' => FormField::TYPE_FILE, 'icon' => 'fa-cloud-upload', 'group' => 'input'],
];
$metaIcons = [
    RespondentMetaService::KEY_IP => 'fa-globe',
    RespondentMetaService::KEY_BROWSER => 'fa-window-maximize',
    RespondentMetaService::KEY_OS => 'fa-cogs',
    RespondentMetaService::KEY_DEVICE => 'fa-desktop',
    RespondentMetaService::KEY_SCREEN => 'fa-arrows-alt',
    RespondentMetaService::KEY_LANGUAGE => 'fa-language',
    RespondentMetaService::KEY_TIMEZONE => 'fa-clock-o',
    RespondentMetaService::KEY_USER_AGENT => 'fa-info-circle',
];
foreach (RespondentMetaService::keyLabels() as $metaKey => $metaLabel) {
    $palette[] = [
        'type' => FormField::TYPE_RESPONDENT_META,
        'meta' => $metaKey,
        'label' => $metaLabel,
        'icon' => $metaIcons[$metaKey] ?? 'fa-eye-slash',
        'group' => 'metadata',
    ];
}
$attachedPanel = $formModel->getAttachedPanel();
$panelAttrKeys = PanelFieldService::surveyFieldLabels($attachedPanel);
$panelIcons = [
    PanelFieldService::KEY_FIRST => 'fa-user',
    PanelFieldService::KEY_LAST => 'fa-user',
    PanelFieldService::KEY_EMAIL => 'fa-envelope-o',
    PanelFieldService::KEY_DISPLAY => 'fa-id-card-o',
];
foreach ($panelAttrKeys as $panelKey => $panelLabel) {
    $palette[] = [
        'type' => FormField::TYPE_PANEL_ATTR,
        'panel' => $panelKey,
        'label' => $panelLabel,
        'icon' => $panelIcons[$panelKey] ?? 'fa-address-card-o',
        'group' => 'panel',
    ];
}
if ($allowedTypes !== null) {
    $palette = array_values(array_filter($palette, static fn($p) => in_array($p['type'], $allowedTypes, true)));
}

$typeLabels = FormField::getTypeLabels();
if ($allowedTypes !== null) {
    $typeLabels = array_intersect_key($typeLabels, array_flip($allowedTypes));
}

$this->registerJsConfig('thiscoveryForms', [
    'none' => Yii::t('ThiscoveryFormsModule.base', 'None'),
    'untitled' => Yii::t('ThiscoveryFormsModule.base', 'Untitled field'),
    'hiddenBadge' => Yii::t('ThiscoveryFormsModule.base', 'Hidden'),
    'types' => $typeLabels,
    'optionTypes' => [FormField::TYPE_DROPDOWN, FormField::TYPE_RADIO, FormField::TYPE_CHECKBOX, FormField::TYPE_RANKING],
    'ratingType' => FormField::TYPE_RATING,
    'pageBreakType' => FormField::TYPE_PAGE_BREAK,
    'questionGroupType' => FormField::TYPE_QUESTION_GROUP,
    'richTextType' => FormField::TYPE_RICH_TEXT,
    'htmlType' => FormField::TYPE_HTML,
    'metaKeys' => RespondentMetaService::keyLabels(),
    'panelKeys' => $panelAttrKeys,
    'operators' => FormField::getOperatorLabels(),
    'logicActions' => \humhub\modules\thiscoveryForms\services\LogicEngine::actionLabels(),
    'groupLogicActions' => \humhub\modules\thiscoveryForms\services\LogicEngine::actionLabelsForType(FormField::TYPE_QUESTION_GROUP),
    'clearConfirm' => Yii::t('ThiscoveryFormsModule.base', 'Remove all fields from this form?'),
    'copied' => Yii::t('ThiscoveryFormsModule.base', 'Copied!'),
    'kind' => $formModel->kind,
    'maxAnswerable' => $isPoll ? 1 : 0,
    'pollLimit' => Yii::t('ThiscoveryFormsModule.base', 'A quick poll can have only one question.'),
    'libraryListUrl' => Url::toLibraryList($contentContainer),
    'librarySaveUrl' => Url::toLibrarySave($contentContainer),
    'libraryDeleteUrl' => Url::toLibraryDelete($contentContainer),
    'libraryInsertUrl' => Url::toLibraryInsert($contentContainer),
    'librarySaved' => Yii::t('ThiscoveryFormsModule.base', 'Saved to library.'),
    'libraryTitlePrompt' => Yii::t('ThiscoveryFormsModule.base', 'Name this library item'),
    'libraryEmpty' => Yii::t('ThiscoveryFormsModule.base', 'No library items yet. Save a question or block from the canvas.'),
    'libraryInsertError' => Yii::t('ThiscoveryFormsModule.base', 'Could not insert that library item.'),
    'libraryDeleteError' => Yii::t('ThiscoveryFormsModule.base', 'Could not delete that library item.'),
    'libraryTypes' => FormLibraryItem::getTypeLabels(),
    'libraryFieldOne' => Yii::t('ThiscoveryFormsModule.base', '{n} field'),
    'libraryFieldMany' => Yii::t('ThiscoveryFormsModule.base', '{n} fields'),
    'libraryGlobal' => Yii::t('ThiscoveryFormsModule.base', 'global'),
    'healthStatusInsertUrl' => Url::toHealthStatusInsert($contentContainer),
    'healthStatusInsertError' => Yii::t('ThiscoveryFormsModule.base', 'Could not insert the health-status pages.'),
    'deleteConfirm' => Yii::t('ThiscoveryFormsModule.base', 'Delete this library item?'),
    'uploadUrl' => \yii\helpers\Url::to(['/file/file/upload']),
    'uploadModel' => !$isNew ? CustomForm::class : '',
    'uploadModelId' => !$isNew ? (string)$formModel->id : '',
    'uploadError' => Yii::t('ThiscoveryFormsModule.base', 'Could not upload that image.'),
    'notImage' => Yii::t('ThiscoveryFormsModule.base', 'Please choose an image file.'),
    'guideShow' => Yii::t('ThiscoveryFormsModule.base', 'Guidance'),
    'guideHide' => Yii::t('ThiscoveryFormsModule.base', 'Hide guidance'),
    'uploading' => Yii::t('ThiscoveryFormsModule.base', 'Uploading…'),
]);
$this->registerJs('humhub.require("thiscoveryForms").initBuilder("#cf-builder");', \yii\web\View::POS_READY);

$fieldList = is_array($fields) ? $fields : [];
$shareUrl = !$isNew ? Url::toView($formModel, true) : '';
?>

<div class="cf-studio panel panel-default" id="cf-builder" data-cf-kind="<?= Html::encode($formModel->kind) ?>">
    <div class="cf-studio__nav">
        <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Back to forms'))
            ->link(Url::toManageIndex($contentContainer))
            ->icon('arrow-left')
            ->loader(false) ?>
        <div class="cf-studio__nav-title">
            <?= Html::encode($isNew
                ? Yii::t('ThiscoveryFormsModule.base', 'New form')
                : $formModel->title) ?>
        </div>
        <div class="cf-studio__nav-actions">
            <button type="submit" name="after_save" value="preview" form="cf-studio-form" class="btn btn-primary cf-studio__preview-btn">
                <i class="fa fa-eye" aria-hidden="true"></i>
                <?= Yii::t('ThiscoveryFormsModule.base', 'Preview') ?>
            </button>
            <?= Button::save(Yii::t('ThiscoveryFormsModule.base', 'Save form'))
                ->submit()
                ->icon('floppy-o')
                ->options(['form' => 'cf-studio-form'])
                ->cssClass('cf-studio__preview-btn')
                ->loader(false) ?>
            <?php if (!$isNew && $formModel->canManage()): ?>
                <?= Html::beginForm(Url::toDelete($formModel), 'post', [
                    'id' => 'cf-delete-form',
                    'class' => 'cf-studio__delete-form',
                    'data-pjax-prevent' => true,
                ]) ?>
                <?= Button::danger(Yii::t('ThiscoveryFormsModule.base', 'Delete form'))
                    ->confirm(Yii::t('ThiscoveryFormsModule.base', 'Delete this form and all submissions?'))
                    ->submit()
                    ->icon('trash')
                    ->cssClass('cf-studio__delete-btn')
                    ->loader(false) ?>
                <?= Html::endForm() ?>
            <?php endif; ?>
        </div>
    </div>
    <?= Html::beginForm($isNew ? Url::toCreate($contentContainer, ['kind' => $formModel->kind]) : Url::toEdit($formModel), 'post', [
        'class' => 'cf-studio__form',
        'id' => 'cf-studio-form',
        'enctype' => 'multipart/form-data',
    ]) ?>
    <?= Html::hiddenInput('studio_tab', (string)Yii::$app->request->get('tab', 'builder'), ['data-cf-studio-tab' => true]) ?>
    <?= Html::activeHiddenInput($formModel, 'kind') ?>
    <?= Html::activeHiddenInput($formModel, 'is_template') ?>
    <?= Html::activeHiddenInput($formModel, 'source_template_id') ?>
    <?= Html::hiddenInput('fields_json', '', ['data-cf-fields-json' => true]) ?>

    <div class="cf-studio__tabs" role="tablist">
        <button type="button" class="cf-studio__tab is-active" data-cf-tab="builder" role="tab" aria-selected="true">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Form builder') ?>
        </button>
        <button type="button" class="cf-studio__tab" data-cf-tab="settings" role="tab" aria-selected="false">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Settings') ?>
        </button>
        <button type="button" class="cf-studio__tab" data-cf-tab="integrity" role="tab" aria-selected="false">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Response integrity') ?>
        </button>
        <?php if ($formModel->usesWaves()): ?>
            <button type="button" class="cf-studio__tab" data-cf-tab="panel" role="tab" aria-selected="false">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Panel & waves') ?>
            </button>
        <?php endif; ?>
        <?php if ($formModel->isConsensus()): ?>
            <button type="button" class="cf-studio__tab" data-cf-tab="rounds" role="tab" aria-selected="false">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Rounds') ?>
            </button>
        <?php endif; ?>
        <?php if ($formModel->isProject()): ?>
            <button type="button" class="cf-studio__tab" data-cf-tab="approval" role="tab" aria-selected="false">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Approval') ?>
            </button>
        <?php endif; ?>
        <button type="button" class="cf-studio__tab" data-cf-tab="translations" role="tab" aria-selected="false">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Translations') ?>
        </button>
        <button type="button" class="cf-studio__tab" data-cf-tab="css" role="tab" aria-selected="false">
            <?= Yii::t('ThiscoveryFormsModule.base', 'CSS') ?>
        </button>
        <button type="button" class="cf-studio__tab" data-cf-tab="share" role="tab" aria-selected="false">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Share') ?>
        </button>
        <a class="cf-studio__help-link"
           href="<?= Html::encode(Url::toHelp($contentContainer, 'creators-builder')) ?>"
           target="_blank"
           rel="noopener"
           data-cf-studio-help
           data-cf-help-pages="<?= Html::encode(json_encode([
               'builder' => Url::toHelp($contentContainer, 'creators-builder'),
               'settings' => Url::toHelp($contentContainer, 'creators-settings'),
               'integrity' => Url::toHelp($contentContainer, 'creators-response-integrity'),
               'panel' => Url::toHelp($contentContainer, 'creators-panels'),
               'rounds' => Url::toHelp($contentContainer, 'creators-form-types'),
               'approval' => Url::toHelp($contentContainer, 'creators-form-types'),
               'translations' => Url::toHelp($contentContainer, 'creators-settings'),
               'css' => Url::toHelp($contentContainer, 'creators-settings'),
               'share' => Url::toHelp($contentContainer, 'creators-results'),
           ])) ?>">
            <i class="fa fa-question-circle" aria-hidden="true"></i>
            <?= Yii::t('ThiscoveryFormsModule.base', 'Help') ?>
        </a>
    </div>

    <div class="cf-studio__panel is-active" data-cf-panel="builder">
        <div class="cf-studio__workspace">
            <aside class="cf-studio__palette" data-cf-palette>
                <div class="cf-palette__tabs">
                    <button type="button" class="cf-palette__tab is-active" data-cf-palette-tab="fields">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Add fields') ?>
                    </button>
                    <button type="button" class="cf-palette__tab" data-cf-palette-tab="library">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Library') ?>
                    </button>
                </div>

                <div class="cf-palette__scroll">
                <div data-cf-palette-panel="fields">
                <div class="cf-palette__title"><?= $isPoll
                    ? Yii::t('ThiscoveryFormsModule.base', 'Poll question')
                    : Yii::t('ThiscoveryFormsModule.base', 'Add fields') ?></div>

                <?php
                $groups = [
                    'content' => Yii::t('ThiscoveryFormsModule.base', 'Content'),
                    'input' => Yii::t('ThiscoveryFormsModule.base', 'Inputs'),
                    'choice' => Yii::t('ThiscoveryFormsModule.base', 'Choices'),
                    'research' => Yii::t('ThiscoveryFormsModule.base', 'Research'),
                    'panel' => Yii::t('ThiscoveryFormsModule.base', 'Panel member'),
                    'metadata' => Yii::t('ThiscoveryFormsModule.base', 'Respondent metadata'),
                ];
                foreach ($groups as $groupKey => $groupLabel):
                    $items = array_filter($palette, static fn($p) => $p['group'] === $groupKey);
                    if (!$items) {
                        continue;
                    }
                    ?>
                    <div class="cf-palette__group">
                        <div class="cf-palette__group-label"><?= Html::encode($groupLabel) ?></div>
                        <?php foreach ($items as $item): ?>
                            <button type="button"
                                    class="cf-palette__item"
                                    draggable="true"
                                    data-cf-palette-type="<?= Html::encode($item['type']) ?>"
                                    <?php if (!empty($item['meta'])): ?>data-cf-palette-meta="<?= Html::encode($item['meta']) ?>"<?php endif; ?>
                                    <?php if (!empty($item['panel'])): ?>data-cf-palette-panel="<?= Html::encode($item['panel']) ?>"<?php endif; ?>
                                    title="<?= Html::encode($item['label'] ?? ($typeLabels[$item['type']] ?? $item['type'])) ?>">
                                <span class="cf-palette__icon"><i class="fa <?= Html::encode($item['icon']) ?>"></i></span>
                                <span class="cf-palette__label"><?= Html::encode($item['label'] ?? ($typeLabels[$item['type']] ?? $item['type'])) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <button type="button" class="btn btn-sm btn-dark cf-palette__clear" data-cf-clear-fields>
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Clear') ?>
                </button>
                <?php if (!$isPoll): ?>
                    <button type="button" class="btn btn-sm btn-light cf-palette__clear" data-cf-insert-health-status>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Insert health-status pages') ?>
                    </button>
                    <p class="cf-hint text-muted cf-palette__hint">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Adds five one-question pages and a vertical 0–100 scale. Paste licensed wording; this is not an official instrument.') ?>
                    </p>
                <?php endif; ?>
                </div>

                <div class="d-none" data-cf-palette-panel="library">
                    <div class="cf-palette__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Library') ?></div>
                    <p class="cf-hint text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'Click a saved question to add it, or drag it onto the form.') ?></p>
                    <div class="cf-library-list" data-cf-library-list></div>
                    <button type="button" class="btn btn-sm btn-light cf-palette__clear" data-cf-library-save-block>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Save all fields as block') ?>
                    </button>
                </div>
                </div>
            </aside>

            <div class="cf-studio__canvas" data-cf-canvas>
                <div class="cf-canvas-empty<?= !empty($fieldList) ? ' d-none' : '' ?>" data-cf-empty>
                    <div class="cf-canvas-empty__inner">
                        <i class="fa fa-hand-pointer-o" aria-hidden="true"></i>
                        <p><?= Yii::t('ThiscoveryFormsModule.base', 'Drag a field from the left to this area.') ?></p>
                        <span><?= Yii::t('ThiscoveryFormsModule.base', 'Or click a field type to add it.') ?></span>
                    </div>
                </div>

                <div class="cf-fields-list" data-cf-fields>
                    <?php
                    $i = 0;
                    foreach ($fieldList as $field):
                        echo $this->render('_field_row', [
                            'key' => $field->id ? ('id' . (int)$field->id) : ('n' . $i),
                            'field' => $field,
                            'allFields' => $fieldList,
                            'collapsed' => true,
                            'allowedTypes' => $allowedTypes,
                            'emailTemplateOptions' => $emailTemplateOptions,
                            'panelAttrKeys' => $panelAttrKeys,
                        ]);
                        $i++;
                    endforeach;
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="cf-studio__panel" data-cf-panel="settings">
        <?= $this->render('_studio_settings', [
            'formModel' => $formModel,
            'isNew' => $isNew,
            'contentContainer' => $contentContainer,
            'isPoll' => $isPoll,
            'fieldList' => $fieldList,
            'emailTemplateOptions' => $emailTemplateOptions,
            'enrolPanelOptions' => $enrolPanelOptions,
        ]) ?>
    </div>

    <div class="cf-studio__panel" data-cf-panel="integrity">
        <?= $this->render('_studio_integrity', [
            'formModel' => $formModel,
            'isNew' => $isNew,
            'fieldList' => $fieldList,
        ]) ?>
    </div>

    <div class="cf-studio__panel" data-cf-panel="css">
        <?= $this->render('_studio_css', ['formModel' => $formModel]) ?>
    </div>

    <div class="cf-studio__panel" data-cf-panel="share">
        <div class="cf-studio__settings">
            <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Distribution URL') ?></h5>
            <?php if ($isNew): ?>
                <p class="cf-hint text-muted">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Save the form first to generate a shareable link.') ?>
                </p>
                <hr>
                <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Sample import file') ?></h5>
                <p class="cf-hint text-muted">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Download an example, then import it after you save. CSV can include every question type, including page breaks. JSON keeps extra settings such as skip logic.') ?>
                </p>
                <p>
                    <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Sample JSON'))
                        ->link(Url::toSampleQuestions($contentContainer, 'json'))
                        ->icon('download')
                        ->loader(false) ?>
                    <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Sample CSV'))
                        ->link(Url::toSampleQuestions($contentContainer, 'csv'))
                        ->icon('download')
                        ->loader(false) ?>
                    <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'CSV import help'))
                        ->link(Url::toHelp($contentContainer, 'creators-csv-import'))
                        ->icon('question-circle')
                        ->loader(false)
                        ->options(['target' => '_blank', 'rel' => 'noopener']) ?>
                </p>
            <?php else: ?>
                <p class="cf-hint text-muted">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Share this link so people can open and fill the form. For anonymous public access, enable anonymous submissions and set the form status to Open.') ?>
                </p>
                <?php if (!$formModel->allowsAnonymous()): ?>
                    <div class="alert alert-warning">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Guest access is off for this form. People without a login will not be able to use this link. Enable “Allow anonymous submissions” and save.') ?>
                    </div>
                <?php elseif (!$formModel->isOpen()): ?>
                    <div class="alert alert-warning">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'This form is not Open yet. Guests cannot respond until the status is set to Open and saved.') ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Always use the https:// link below. Test it in a private/incognito window while logged out. Opening the link in your normal browser while signed in will always work, even for non-guest forms.') ?>
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Form URL') ?></label>
                    <div class="input-group">
                        <input type="text" class="form-control" readonly value="<?= Html::encode($shareUrl) ?>" data-cf-share-url>
                        <button type="button" class="btn btn-primary" data-cf-copy-url>
                            <i class="fa fa-clipboard"></i>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Copy link') ?>
                        </button>
                    </div>
                    <div class="cf-copy-feedback text-success d-none" data-cf-copy-feedback><?= Yii::t('ThiscoveryFormsModule.base', 'Copied!') ?></div>
                </div>
                <p>
                    <a href="<?= Html::encode($shareUrl) ?>" target="_blank" rel="noopener">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Open form') ?>
                        <i class="fa fa-external-link"></i>
                    </a>
                </p>
                <hr>
                <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Preview and test') ?></h5>
                <p class="cf-hint text-muted">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Share this link to try the form. Test answers are stored separately and are not counted as participant submissions, dashboard totals, or CSV export.') ?>
                </p>
                <p class="cf-hint text-muted">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Preview in the studio saves your latest work, then opens the test form. Use Edit on that page to come back.') ?>
                </p>
                <?php $previewUrl = Url::toPreview($formModel, true); ?>
                <div class="form-group">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Preview URL') ?></label>
                    <div class="input-group">
                        <input type="text" class="form-control" readonly value="<?= Html::encode($previewUrl) ?>">
                        <button type="button" class="btn btn-primary" data-cf-copy-url>
                            <i class="fa fa-clipboard"></i>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Copy link') ?>
                        </button>
                    </div>
                </div>
                <p>
                    <a href="<?= Html::encode($previewUrl) ?>" target="_blank" rel="noopener">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Open preview') ?>
                        <i class="fa fa-external-link"></i>
                    </a>
                    <button type="submit" class="btn btn-link btn-sm" form="cf-regen-preview-form">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Regenerate link') ?>
                    </button>
                </p>
                <hr>
                <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Share dashboard') ?></h5>
                <p class="cf-hint text-muted">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'When enabled in Settings, anyone with this link can see aggregate results without signing in. Individual answers and CSV export stay private.') ?>
                </p>
                <?php if ($formModel->allowsPublicDashboard()): ?>
                    <?php $dashUrl = Url::toPublicDashboard($formModel, true); ?>
                    <div class="form-group">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Dashboard URL') ?></label>
                        <div class="input-group">
                            <input type="text" class="form-control" readonly value="<?= Html::encode($dashUrl) ?>">
                            <button type="button" class="btn btn-primary" data-cf-copy-url>
                                <i class="fa fa-clipboard"></i>
                                <?= Yii::t('ThiscoveryFormsModule.base', 'Copy link') ?>
                            </button>
                        </div>
                    </div>
                    <p>
                        <a href="<?= Html::encode($dashUrl) ?>" target="_blank" rel="noopener">
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Open shared dashboard') ?>
                            <i class="fa fa-external-link"></i>
                        </a>
                        <button type="submit" class="btn btn-link btn-sm" form="cf-regen-dash-form">
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Regenerate link') ?>
                        </button>
                    </p>
                <?php else: ?>
                    <div class="alert alert-info">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Enable “Share dashboard without sign-in” on the Settings tab and save the form to generate a public dashboard link.') ?>
                    </div>
                <?php endif; ?>
                <?php if (!$formModel->isTemplate()): ?>
                    <hr>
                    <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Save as template') ?></h5>
                    <p class="cf-hint text-muted">
                        <?= Yii::t(
                            'ThiscoveryFormsModule.base',
                            'Stores a copy of this {type} (without answers) so you can create new {type} forms from it later.',
                            ['type' => CustomForm::getKindLabels()[$formModel->kind] ?? $formModel->kind]
                        ) ?>
                    </p>
                    <button type="submit" class="btn btn-light" form="cf-template-form">
                        <i class="fa fa-clone"></i>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Save as template') ?>
                    </button>
                <?php endif; ?>
                <hr>
                <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Import and export questions') ?></h5>
                <p class="cf-hint text-muted">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'CSV can include every question type, including page breaks. Tick Replace to overwrite the form; leave it unticked to append. See Help for columns and examples.') ?>
                </p>
                <p>
                    <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Export JSON'))
                        ->link(Url::toExportQuestions($formModel, 'json'))
                        ->icon('download')
                        ->loader(false) ?>
                    <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Export CSV'))
                        ->link(Url::toExportQuestions($formModel, 'csv'))
                        ->icon('download')
                        ->loader(false) ?>
                    <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Sample JSON'))
                        ->link(Url::toSampleQuestions($contentContainer, 'json'))
                        ->icon('download')
                        ->loader(false) ?>
                    <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Sample CSV'))
                        ->link(Url::toSampleQuestions($contentContainer, 'csv'))
                        ->icon('download')
                        ->loader(false) ?>
                    <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'CSV import help'))
                        ->link(Url::toHelp($contentContainer, 'creators-csv-import'))
                        ->icon('question-circle')
                        ->loader(false)
                        ->options(['target' => '_blank', 'rel' => 'noopener']) ?>
                </p>
                <div class="form-group">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Import file') ?></label>
                    <input type="file" name="import_file" class="form-control" form="cf-import-form" accept=".json,.csv,application/json,text/csv">
                </div>
                <div class="cf-checks mb-3">
                    <div class="cf-check-setting">
                        <label>
                            <input type="checkbox" name="replace_fields" value="1" id="cf-import-replace" form="cf-import-form">
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Replace all existing questions') ?>
                        </label>
                        <p class="cf-hint text-muted mb-0">
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Leave this unticked to add the imported questions after the ones already on the form.') ?>
                        </p>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" form="cf-import-form"
                    onclick="var r=document.getElementById('cf-import-replace'); return !r || !r.checked || confirm(<?= \yii\helpers\Json::htmlEncode(Yii::t('ThiscoveryFormsModule.base', 'This will delete every question currently on the form and replace them with the import. This cannot be undone. Continue?')) ?>);">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Import questions') ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <script type="text/template" id="cf-field-template">
        <?= $this->render('_field_row', [
            'key' => 'n__INDEX__',
            'field' => new FormField([
                'type' => $isPoll ? FormField::TYPE_RADIO : FormField::TYPE_TEXT,
                'label' => '',
                'required' => 0,
            ]),
            'allFields' => [],
            'collapsed' => false,
            'allowedTypes' => $allowedTypes,
            'emailTemplateOptions' => $emailTemplateOptions,
            'panelAttrKeys' => $panelAttrKeys,
        ]) ?>
    </script>

    <div class="cf-studio__footer">
        <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Back to forms'))
            ->link(Url::toManageIndex($contentContainer))
            ->icon('arrow-left') ?>
        <div class="cf-studio__footer-actions">
            <button type="submit" name="after_save" value="preview" class="btn btn-primary">
                <i class="fa fa-eye" aria-hidden="true"></i>
                <?= Yii::t('ThiscoveryFormsModule.base', 'Preview') ?>
            </button>
            <?= Button::save(Yii::t('ThiscoveryFormsModule.base', 'Save form'))->submit()->icon('floppy-o') ?>
            <?php if (!$isNew && $formModel->canManage()): ?>
                <button type="submit" form="cf-delete-form" class="btn btn-danger cf-studio__delete-btn"
                    onclick="return confirm(<?= \yii\helpers\Json::htmlEncode(Yii::t('ThiscoveryFormsModule.base', 'Delete this form and all submissions?')) ?>);">
                    <i class="fa fa-trash" aria-hidden="true"></i>
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Delete form') ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?= Html::endForm() ?>

    <div class="cf-studio__extra">
    <?php if ($formModel->usesWaves()): ?>
        <div class="cf-studio__panel" data-cf-panel="panel">
            <?= $this->render('_studio_panel', ['formModel' => $formModel, 'isNew' => $isNew]) ?>
        </div>
    <?php endif; ?>

    <?php if ($formModel->isConsensus()): ?>
        <div class="cf-studio__panel" data-cf-panel="rounds">
            <?= $this->render('_studio_rounds', ['formModel' => $formModel, 'isNew' => $isNew]) ?>
        </div>
    <?php endif; ?>

    <?php if ($formModel->isProject()): ?>
        <div class="cf-studio__panel" data-cf-panel="approval">
            <?= $this->render('_studio_approval', ['formModel' => $formModel, 'isNew' => $isNew]) ?>
        </div>
    <?php endif; ?>

    <div class="cf-studio__panel" data-cf-panel="translations">
        <?= $this->render('_studio_translations', ['formModel' => $formModel, 'isNew' => $isNew]) ?>
    </div>
    </div>

    <?php if (!$isNew): ?>
        <?= Html::beginForm(Url::toSaveTemplate($formModel), 'post', ['id' => 'cf-template-form', 'class' => 'd-none']) ?>
        <?= Html::endForm() ?>
        <?= Html::beginForm(Url::toRegeneratePreview($formModel), 'post', ['id' => 'cf-regen-preview-form', 'class' => 'd-none']) ?>
        <?= Html::endForm() ?>
        <?= Html::beginForm(Url::toRegenerateDashboardShare($formModel), 'post', ['id' => 'cf-regen-dash-form', 'class' => 'd-none']) ?>
        <?= Html::endForm() ?>
        <?= Html::beginForm(Url::toImportQuestions($formModel), 'post', [
            'id' => 'cf-import-form',
            'class' => 'd-none',
            'enctype' => 'multipart/form-data',
        ]) ?>
        <?= Html::endForm() ?>
        <?= Html::beginForm(Url::toImportTranslations($formModel), 'post', [
            'id' => 'cf-i18n-import-form',
            'class' => 'd-none',
            'enctype' => 'multipart/form-data',
        ]) ?>
        <?= Html::endForm() ?>
    <?php endif; ?>
</div>
