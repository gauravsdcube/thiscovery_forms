<?php

use humhub\modules\content\widgets\richtext\RichTextField;
use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var bool $isNew */
/** @var $contentContainer */
/** @var $fields */

ThiscoveryFormsAsset::register($this);

$palette = [
    ['type' => FormField::TYPE_RICH_TEXT, 'icon' => 'fa-paragraph', 'group' => 'content'],
    ['type' => FormField::TYPE_HTML, 'icon' => 'fa-code', 'group' => 'content'],
    ['type' => FormField::TYPE_PAGE_BREAK, 'icon' => 'fa-files-o', 'group' => 'content'],
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
    ['type' => FormField::TYPE_FILE, 'icon' => 'fa-cloud-upload', 'group' => 'input'],
];

$typeLabels = FormField::getTypeLabels();

$this->registerJsConfig('thiscoveryForms', [
    'none' => Yii::t('ThiscoveryFormsModule.base', 'None'),
    'untitled' => Yii::t('ThiscoveryFormsModule.base', 'Untitled field'),
    'required' => Yii::t('ThiscoveryFormsModule.base', 'Required'),
    'types' => $typeLabels,
    'optionTypes' => [FormField::TYPE_DROPDOWN, FormField::TYPE_RADIO, FormField::TYPE_CHECKBOX, FormField::TYPE_RANKING],
    'ratingType' => FormField::TYPE_RATING,
    'pageBreakType' => FormField::TYPE_PAGE_BREAK,
    'richTextType' => FormField::TYPE_RICH_TEXT,
    'htmlType' => FormField::TYPE_HTML,
    'operators' => FormField::getOperatorLabels(),
    'clearConfirm' => Yii::t('ThiscoveryFormsModule.base', 'Remove all fields from this form?'),
    'copied' => Yii::t('ThiscoveryFormsModule.base', 'Copied!'),
]);
$this->registerJs('humhub.require("thiscoveryForms").initBuilder("#cf-builder");', \yii\web\View::POS_READY);

$fieldList = is_array($fields) ? $fields : [];
$shareUrl = !$isNew ? Url::toView($formModel, true) : '';
?>

<div class="cf-studio panel panel-default" id="cf-builder">
    <?= Html::beginForm($isNew ? Url::toCreate($contentContainer) : Url::toEdit($formModel), 'post', [
        'class' => 'cf-studio__form',
    ]) ?>

    <div class="cf-studio__tabs" role="tablist">
        <button type="button" class="cf-studio__tab is-active" data-cf-tab="builder" role="tab" aria-selected="true">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Form builder') ?>
        </button>
        <button type="button" class="cf-studio__tab" data-cf-tab="settings" role="tab" aria-selected="false">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Settings') ?>
        </button>
        <button type="button" class="cf-studio__tab" data-cf-tab="css" role="tab" aria-selected="false">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Custom CSS') ?>
        </button>
        <button type="button" class="cf-studio__tab" data-cf-tab="share" role="tab" aria-selected="false">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Share') ?>
        </button>
    </div>

    <div class="cf-studio__panel is-active" data-cf-panel="builder">
        <div class="cf-studio__workspace">
            <aside class="cf-studio__palette" data-cf-palette>
                <div class="cf-palette__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Add fields') ?></div>

                <?php
                $groups = [
                    'content' => Yii::t('ThiscoveryFormsModule.base', 'Content'),
                    'input' => Yii::t('ThiscoveryFormsModule.base', 'Inputs'),
                    'choice' => Yii::t('ThiscoveryFormsModule.base', 'Choices'),
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
                                    title="<?= Html::encode($typeLabels[$item['type']] ?? $item['type']) ?>">
                                <span class="cf-palette__icon"><i class="fa <?= Html::encode($item['icon']) ?>"></i></span>
                                <span class="cf-palette__label"><?= Html::encode($typeLabels[$item['type']] ?? $item['type']) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <button type="button" class="btn btn-sm btn-dark cf-palette__clear" data-cf-clear-fields>
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Clear') ?>
                </button>
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
                            'key' => $field->id ?: ('n' . $i),
                            'field' => $field,
                            'allFields' => $fieldList,
                            'collapsed' => true,
                        ]);
                        $i++;
                    endforeach;
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="cf-studio__panel" data-cf-panel="settings">
        <div class="cf-studio__settings">
            <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Form details') ?></h5>

            <div class="form-group">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Title') ?></label>
                <?= Html::activeTextInput($formModel, 'title', [
                    'class' => 'form-control form-control-lg',
                    'required' => true,
                    'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'e.g. Membership feedback'),
                ]) ?>
            </div>

            <div class="form-group">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Description') ?>
                    <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
                </label>
                <?= Html::activeTextarea($formModel, 'description', [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Explain what this form is for'),
                ]) ?>
            </div>

            <div class="form-group">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Thank you message') ?>
                    <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
                </label>
                <div class="cf-field-note">
                    <i class="fa fa-info-circle" aria-hidden="true"></i>
                    <div><?= Yii::t('ThiscoveryFormsModule.base', 'Shown after a successful submission. Leave empty for the default thank-you message.') ?></div>
                </div>
                <?= RichTextField::widget([
                    'model' => $formModel,
                    'attribute' => 'thank_you_content',
                    'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Thanks for completing this form…'),
                    'backupInterval' => 0,
                    'exclude' => ['oembed', 'mention'],
                ]) ?>
            </div>

            <div class="row g-3">
                <div class="col-md-4 form-group mb-0">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Status') ?></label>
                    <?= Html::activeDropDownList($formModel, 'status', CustomForm::getStatusLabels(), ['class' => 'form-control']) ?>
                </div>
                <div class="col-md-4 form-group mb-0">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Who can view answers') ?></label>
                    <?= Html::activeDropDownList($formModel, 'answers_visibility', CustomForm::getAnswersVisibilityLabels(), ['class' => 'form-control']) ?>
                </div>
                <div class="col-md-4">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Options') ?></label>
                    <div class="cf-checks">
                        <label>
                            <?= Html::activeCheckbox($formModel, 'allow_multiple', ['label' => false]) ?>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Allow multiple submissions') ?>
                        </label>
                        <label>
                            <?= Html::activeCheckbox($formModel, 'allow_anonymous', ['label' => false]) ?>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Allow anonymous submissions') ?>
                        </label>
                        <label>
                            <?= Html::activeCheckbox($formModel, 'show_in_menu', ['label' => false]) ?>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Show in side menu') ?>
                        </label>
                    </div>
                    <p class="cf-hint text-muted">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Anonymous mode does not store who submitted the form. Guests can fill the form when this is enabled.') ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="cf-studio__panel" data-cf-panel="css">
        <div class="cf-studio__settings">
            <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Custom CSS') ?></h5>
            <p class="cf-hint text-muted">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Styles apply only to the fill page. Prefer selectors under #cf-fill (for example #cf-fill .cf-question or #cf-fill .cf-fill-hero__title).') ?>
            </p>
            <?= Html::activeTextarea($formModel, 'custom_css', [
                'class' => 'form-control cf-css-editor',
                'rows' => 18,
                'spellcheck' => 'false',
                'placeholder' => "#cf-fill .cf-fill-hero__title {\n  color: #1a5f4a;\n}\n#cf-fill .cf-question {\n  margin-bottom: 1.5rem;\n}",
            ]) ?>
        </div>
    </div>

    <div class="cf-studio__panel" data-cf-panel="share">
        <div class="cf-studio__settings">
            <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Distribution URL') ?></h5>
            <?php if ($isNew): ?>
                <p class="cf-hint text-muted">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Save the form first to generate a shareable link.') ?>
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
            <?php endif; ?>
        </div>
    </div>

    <script type="text/template" id="cf-field-template">
        <?= $this->render('_field_row', [
            'key' => '__INDEX__',
            'field' => new FormField([
                'type' => FormField::TYPE_TEXT,
                'label' => '',
                'required' => 0,
            ]),
            'allFields' => [],
            'collapsed' => false,
        ]) ?>
    </script>

    <div class="cf-studio__footer">
        <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Close'))
            ->link($isNew ? Url::toIndex($contentContainer) : Url::toView($formModel)) ?>
        <?= Button::save(Yii::t('ThiscoveryFormsModule.base', 'Save form'))->submit()->icon('floppy-o') ?>
    </div>

    <?= Html::endForm() ?>
</div>
