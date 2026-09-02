<?php

use humhub\modules\thiscoveryEditor\widgets\EditorField;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\FolderService;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var bool $isNew */
/** @var $contentContainer */
/** @var bool $isPoll */
/** @var FormField[] $fieldList */
/** @var array $emailTemplateOptions */
/** @var array $enrolPanelOptions */

$kindGuide = CustomForm::getKindLabels()[$formModel->kind] ?? $formModel->kind;
if ($formModel->isTemplate()) {
    $kindGuide .= ' · ' . Yii::t('ThiscoveryFormsModule.base', 'Template');
}
$kindExtra = [];
if ($formModel->usesWaves()) {
    $kindExtra[] = Yii::t('ThiscoveryFormsModule.base', 'One submission per panel member per wave. Set up the panel and waves on the Panel & waves tab.');
}
if ($formModel->isEq5d()) {
    $kindExtra[] = Yii::t('ThiscoveryFormsModule.base', 'Paste licensed EQ-5D wording and copyright on each page. Thiscovery Forms supplies the five-page layout and thermometer only — not the official instrument.');
}
if ($formModel->isConsensus()) {
    $kindExtra[] = Yii::t('ThiscoveryFormsModule.base', 'One submission per person per round. Set up rounds on the Rounds tab.');
}
if ($formModel->isProject()) {
    $kindExtra[] = Yii::t('ThiscoveryFormsModule.base', 'Submissions go through the approval stages on the Approval tab before they appear in the catalogue.');
}
$kindHtml = '<p>' . Html::encode($kindGuide) . '</p>';
foreach ($kindExtra as $extra) {
    $kindHtml .= '<p>' . Html::encode($extra) . '</p>';
}

$folderOptions = ['' => Yii::t('ThiscoveryFormsModule.base', 'Unfiled')] + FolderService::treeOptions($contentContainer, null, FolderService::ACCESS_CREATE);
if ($formModel->folder_id && !isset($folderOptions[(int)$formModel->folder_id]) && $formModel->folder) {
    $folderOptions[(int)$formModel->folder_id] = $formModel->folder->getPathLabel();
}

$submitPageKeys = ['' => Yii::t('ThiscoveryFormsModule.base', 'Select page…')];
foreach ($fieldList as $pkField) {
    if ($pkField->type === FormField::TYPE_PAGE_BREAK) {
        $pk = $pkField->getPageBreakConfig()['pageKey'];
        if ($pk !== '') {
            $title = $pkField->getPageBreakConfig()['title'] ?: $pk;
            $submitPageKeys[$pk] = $title . ' (' . $pk . ')';
        }
    }
}
$fnRows = $formModel->custom_functions ?: [['name' => '', 'value' => '']];
?>
<div class="cf-studio__settings">
    <div class="cf-set-toolbar">
        <button type="button" class="btn btn-sm btn-light" data-cf-acc-all="open"><?= Yii::t('ThiscoveryFormsModule.base', 'Expand all') ?></button>
        <button type="button" class="btn btn-sm btn-light" data-cf-acc-all="close"><?= Yii::t('ThiscoveryFormsModule.base', 'Collapse all') ?></button>
    </div>

    <details class="cf-set-acc" open>
        <summary>
            <span class="cf-set-acc__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Basics') ?></span>
            <span class="cf-set-acc__summary"><?= Yii::t('ThiscoveryFormsModule.base', 'Title, description, status, thank you') ?></span>
        </summary>
        <div class="cf-set-acc__body">
            <div class="cf-field">
                <span class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Form type') ?></span>
                <?= $this->render('_setting_guide', ['html' => $kindHtml]) ?>
                <p class="form-control-plaintext mb-0"><?= Html::encode($kindGuide) ?></p>
            </div>
            <div class="form-group cf-field">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Title') ?></label>
                <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'The name people see at the top of the form, in lists, and in emails. Keep it short and clear.')]) ?>
                <?= Html::activeTextInput($formModel, 'title', [
                    'class' => 'form-control form-control-lg',
                    'required' => true,
                    'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'e.g. Membership feedback'),
                ]) ?>
            </div>
            <div class="form-group cf-field">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Description') ?>
                    <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
                </label>
                <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Shown under the title on the fill page. Use it to explain who the form is for and what they should expect.')]) ?>
                <?= Html::activeTextarea($formModel, 'description', [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Explain what this form is for'),
                ]) ?>
            </div>
            <div class="row g-3">
                <div class="col-md-4 form-group mb-0 cf-field">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Status') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Draft is only for managers and preview. Open accepts responses. Closed keeps the form visible but stops new submissions.')]) ?>
                    <?= Html::activeDropDownList($formModel, 'status', CustomForm::getStatusLabels(), ['class' => 'form-control']) ?>
                </div>
                <div class="col-md-4 form-group mb-0 cf-field">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Folder') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Optional grouping on the forms list. Unfiled forms still work as normal. Create folders from the forms list.')]) ?>
                    <?= Html::activeDropDownList($formModel, 'folder_id', $folderOptions, ['class' => 'form-control']) ?>
                </div>
                <div class="col-md-4 form-group mb-0 cf-field">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Who can view answers') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Author and managers only is the strictest. Managers and respondents lets people who submitted see results. Anyone with View Answers permission uses the space or network permission.')]) ?>
                    <?= Html::activeDropDownList($formModel, 'answers_visibility', CustomForm::getAnswersVisibilityLabels(), ['class' => 'form-control']) ?>
                </div>
            </div>
            <div class="form-group cf-field">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Thank you message') ?>
                    <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
                </label>
                <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Shown after a successful submission. Leave empty for the default thank-you message.')]) ?>
                <div class="cf-rich-editor" data-cf-rich-editor>
                    <?= EditorField::widget([
                        'model' => $formModel,
                        'attribute' => 'thank_you_content',
                        'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Thanks for completing this form…'),
                        'height' => 220,
                        'profile' => 'simple',
                    ]) ?>
                </div>
            </div>
            <div class="form-group cf-field mb-0">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Already submitted message') ?>
                    <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
                </label>
                <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Shown when this person has already submitted and multiple submissions are not allowed. Use {formName} for the form title. Leave empty for the default message.', ['formName' => '{formName}'])]) ?>
                <?= Html::activeTextarea($formModel, 'already_submitted_message', [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'You have already submitted {formName}. Multiple submissions are not allowed', ['formName' => '{formName}']),
                ]) ?>
            </div>
        </div>
    </details>

    <details class="cf-set-acc">
        <summary>
            <span class="cf-set-acc__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Who can take part') ?></span>
            <span class="cf-set-acc__summary"><?= Yii::t('ThiscoveryFormsModule.base', 'Anonymous fill, editing, save and resume') ?></span>
        </summary>
        <div class="cf-set-acc__body">
            <div class="cf-checks">
                <?php if (!$formModel->usesWaves() && !$formModel->isConsensus()): ?>
                <div class="cf-check-setting">
                    <label>
                        <?= Html::activeCheckbox($formModel, 'allow_multiple', ['label' => false]) ?>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Allow multiple submissions') ?>
                    </label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Lets the same person submit more than once. Turn this off when you need one response per person.')]) ?>
                </div>
                <?php endif; ?>
                <?php if (!$formModel->isProject()): ?>
                <div class="cf-check-setting">
                    <label>
                        <?= Html::activeCheckbox($formModel, 'allow_anonymous', ['label' => false]) ?>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Allow anonymous submissions') ?>
                    </label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Anonymous mode does not store who submitted the form. Guests can fill the form when this is enabled.')]) ?>
                </div>
                <?php endif; ?>
                <div class="cf-check-setting">
                    <label>
                        <?= Html::activeCheckbox($formModel, 'allow_edit', ['label' => false]) ?>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Allow respondents to edit their answers') ?>
                    </label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'If respondents cannot edit, they will see a confirmation after submitting and cannot change that response. Form managers can still update answers.')]) ?>
                </div>
                <div class="cf-check-setting">
                    <label>
                        <?= Html::activeCheckbox($formModel, 'allow_resume', ['label' => false]) ?>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Allow save and resume') ?>
                    </label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'People are asked whether they are continuing a saved response or starting a new one. Save & continue later is only shown when this is on. Preview follows the same rule.')]) ?>
                </div>
                <div class="cf-check-setting">
                    <label>
                        <?= Html::activeCheckbox($formModel, 'keep_partials', ['label' => false]) ?>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Keep incomplete responses') ?>
                    </label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Keep incomplete responses stores in-progress answers for the dashboard and CSV export, even if the person never uses save and resume. Progress is saved as they move through the form.')]) ?>
                </div>
                <?php if ($formModel->isSurvey() && \humhub\modules\thiscoveryForms\Module::wavesEnabledForSurveysStatic()): ?>
                <div class="cf-check-setting">
                    <label>
                        <?= Html::activeCheckbox($formModel, 'use_waves', ['label' => false]) ?>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Use waves') ?>
                    </label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Use waves for repeating measures on the same survey. Save the form, then set up the panel and waves on the Panel & waves tab.')]) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </details>

    <details class="cf-set-acc">
        <summary>
            <span class="cf-set-acc__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Sharing and display') ?></span>
            <span class="cf-set-acc__summary"><?= Yii::t('ThiscoveryFormsModule.base', 'Menu, header, dashboard link') ?></span>
        </summary>
        <div class="cf-set-acc__body">
            <div class="cf-checks">
                <div class="cf-check-setting">
                    <label>
                        <?= Html::activeCheckbox($formModel, 'public_dashboard_enabled', ['label' => false]) ?>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Share dashboard without sign-in') ?>
                    </label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Creates a secret link on the Share tab so people can view charts without logging in. Anyone with the link can see the dashboard.')]) ?>
                </div>
                <div class="cf-check-setting">
                    <label>
                        <?= Html::activeCheckbox($formModel, 'show_in_menu', ['label' => false]) ?>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Show in side menu') ?>
                    </label>
                    <?= $this->render('_setting_guide', ['text' => (
                        $contentContainer === null
                        && class_exists(\humhub\modules\thiscoveryNavigation\helpers\Navigation::class)
                        && \humhub\modules\thiscoveryNavigation\helpers\Navigation::isActive()
                    )
                        ? Yii::t('ThiscoveryFormsModule.base', 'Adds this form to the space menu. For the network top bar, add it in Site navigation.')
                        : Yii::t('ThiscoveryFormsModule.base', 'Adds this form to the space menu, or the network top menu for global forms, so members can open it without going to the forms list.')
                    ]) ?>
                </div>
                <div class="cf-check-setting">
                    <label>
                        <?= Html::activeCheckbox($formModel, 'hide_humhub_header', ['label' => false]) ?>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Run without HumHub header') ?>
                    </label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Run without HumHub header shows only the form on fill, preview, and thank-you pages. Site navigation and the space menu are hidden.')]) ?>
                </div>
                <?php if ($isPoll): ?>
                <div class="cf-check-setting">
                    <label>
                        <?= Html::activeCheckbox($formModel, 'show_results', ['label' => false]) ?>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Show results after voting') ?>
                    </label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'After someone votes, show the poll totals on the fill page.')]) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </details>

    <details class="cf-set-acc">
        <summary>
            <span class="cf-set-acc__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Panel enrolment') ?></span>
            <span class="cf-set-acc__summary"><?= Yii::t('ThiscoveryFormsModule.base', 'Add completers to a panel') ?></span>
        </summary>
        <div class="cf-set-acc__body">
            <div class="cf-enrol-panel" data-cf-enrol>
                <div class="form-group cf-field">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Add completers to a panel') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'If this form already uses a panel on Panel & waves, completers with an email are added there automatically. Use this only to add them to a different panel as well.')]) ?>
                    <?= Html::activeDropDownList($formModel, 'enrol_panel_mode', [
                        CustomForm::ENROL_PANEL_NONE => Yii::t('ThiscoveryFormsModule.base', 'Do not add to a panel'),
                        CustomForm::ENROL_PANEL_EXISTING => Yii::t('ThiscoveryFormsModule.base', 'Add to an existing panel'),
                        CustomForm::ENROL_PANEL_CREATE => Yii::t('ThiscoveryFormsModule.base', 'Create a new panel'),
                    ], ['class' => 'form-control', 'data-cf-enrol-mode' => 1]) ?>
                </div>
                <div class="form-group cf-field" data-cf-enrol-existing>
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Panel') ?></label>
                    <?= $this->render('_setting_guide', [
                        'html' => '<p>' . Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Create and manage panels on the Panels screen.'))
                            . ' <a href="' . Html::encode(Url::toPanelIndex($contentContainer)) . '">'
                            . Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Open panels')) . '</a></p>',
                    ]) ?>
                    <?= Html::activeDropDownList($formModel, 'enrol_panel_id', $enrolPanelOptions, ['class' => 'form-control']) ?>
                </div>
                <div class="form-group cf-field" data-cf-enrol-create>
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'New panel name') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'The panel is created when you save this form, so it appears on the Panels screen before anyone submits.')]) ?>
                    <?= Html::activeTextInput($formModel, 'enrol_panel_title', [
                        'class' => 'form-control',
                        'placeholder' => Html::encode($formModel->title
                            ? Yii::t('ThiscoveryFormsModule.base', '{title} panel', ['title' => $formModel->title])
                            : Yii::t('ThiscoveryFormsModule.base', 'Panel')),
                    ]) ?>
                </div>
                <div class="cf-checks">
                    <div class="cf-check-setting">
                        <label>
                            <?= Html::activeCheckbox($formModel, 'log_panel_activity', ['label' => false]) ?>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Record each completion on the panel') ?>
                        </label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'When this form uses a panel, completions are listed on matching members automatically (invite link, signed-in email, or an email field on the form). Tick this to also record on the enrolment panel chosen above.')]) ?>
                    </div>
                </div>
            </div>
        </div>
    </details>

    <details class="cf-set-acc">
        <summary>
            <span class="cf-set-acc__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Email templates') ?></span>
            <span class="cf-set-acc__summary"><?= Yii::t('ThiscoveryFormsModule.base', 'Invite, wave, reminder, completion') ?></span>
        </summary>
        <div class="cf-set-acc__body">
            <div class="cf-field mb-3">
                <span class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'About email templates') ?></span>
                <?= $this->render('_setting_guide', [
                    'html' => '<p>' . Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Choose templates created on the Email templates screen. Each template has a header, body, and footer written in Thiscovery Editor. Leave as default to keep the built-in invite wording. Extra emails can also be sent from field, page, or submit actions.'))
                        . ' <a href="' . Html::encode(Url::toEmailTemplateIndex($contentContainer)) . '">'
                        . Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Open email templates')) . '</a></p>',
                ]) ?>
            </div>
            <div class="row g-3">
                <div class="col-md-6 form-group cf-field">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Invite email') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Sent when you invite panel members to this form.')]) ?>
                    <?= Html::activeDropDownList($formModel, 'invite_email_template_id', $emailTemplateOptions, ['class' => 'form-control']) ?>
                </div>
                <div class="col-md-6 form-group cf-field">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Wave email') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Sent when a wave opens, using each member’s personal link.')]) ?>
                    <?= Html::activeDropDownList($formModel, 'wave_email_template_id', $emailTemplateOptions, ['class' => 'form-control']) ?>
                </div>
                <div class="col-md-6 form-group cf-field">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Post-completion email') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Sent after a successful submission when we have an email address from the account or a field on the form.')]) ?>
                    <?= Html::activeDropDownList($formModel, 'completion_email_template_id', $emailTemplateOptions, ['class' => 'form-control']) ?>
                </div>
                <div class="col-md-4 form-group cf-field">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Reminder email') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Reminders are sent to panel members who have not completed the current open wave, once the wave has been open for the number of days set here. Set days to 0 to turn reminders off.')]) ?>
                    <?= Html::activeDropDownList($formModel, 'reminder_email_template_id', $emailTemplateOptions, ['class' => 'form-control']) ?>
                </div>
                <div class="col-md-2 form-group cf-field">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'After (days)') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'How long a wave must be open before a reminder is sent. Use 0 to turn reminders off.')]) ?>
                    <?= Html::activeInput('number', $formModel, 'reminder_days', ['class' => 'form-control', 'min' => 0, 'step' => 1]) ?>
                </div>
            </div>
        </div>
    </details>

    <details class="cf-set-acc">
        <summary>
            <span class="cf-set-acc__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Languages') ?></span>
            <span class="cf-set-acc__summary"><?= Yii::t('ThiscoveryFormsModule.base', 'Source language and translations') ?></span>
        </summary>
        <div class="cf-set-acc__body">
            <div class="row g-3">
                <div class="col-12 form-group cf-field">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Source language') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'The source language is what you write in the builder. Extra languages are edited on the Translations tab.')]) ?>
                    <?= Html::activeDropDownList(
                        $formModel,
                        'source_language',
                        \humhub\modules\thiscoveryForms\services\TranslationService::selectableLanguageLabels($formModel),
                        ['class' => 'form-control', 'style' => 'max-width: 20rem']
                    ) ?>
                </div>
                <div class="col-12 form-group cf-field">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Enabled languages') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Languages people can switch to on the fill page. When Thiscovery Translate is on, this list matches the languages enabled there. Translate labels on the Translations tab after you save.')]) ?>
                    <?php
                    $langChoices = \humhub\modules\thiscoveryForms\services\TranslationService::selectableLanguageLabels($formModel);
                    $enabledLangs = $formModel->getEnabledLanguages();
                    ?>
                    <p class="cf-hint text-muted">
                        <?= Yii::t('ThiscoveryFormsModule.base', '{n} languages available. Use browser search (Ctrl/Cmd+F) to find one quickly.', [
                            'n' => count($langChoices),
                        ]) ?>
                    </p>
                    <div class="cf-lang-grid">
                        <?php foreach ($langChoices as $code => $label): ?>
                            <label class="cf-lang-check">
                                <?= Html::checkbox('CustomForm[enabled_languages][]', in_array($code, $enabledLangs, true), [
                                    'value' => $code,
                                    'uncheck' => null,
                                ]) ?>
                                <span class="cf-lang-check__label"><?= Html::encode($label) ?></span>
                                <code class="cf-lang-check__code"><?= Html::encode($code) ?></code>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </details>

    <details class="cf-set-acc">
        <summary>
            <span class="cf-set-acc__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Actions and functions') ?></span>
            <span class="cf-set-acc__summary"><?= Yii::t('ThiscoveryFormsModule.base', 'On submit, custom functions') ?></span>
        </summary>
        <div class="cf-set-acc__body">
            <div class="cf-field mb-3">
                <span class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Custom functions and variables') ?></span>
                <?= $this->render('_setting_guide', [
                    'html' => '<p>' . Html::encode(Yii::t('ThiscoveryFormsModule.base', 'A custom function is a named formula you write once and reuse. It is not code — it is a text template that can include answers and other placeholders.')) . '</p>'
                        . '<ol class="cf-fn-guide">'
                        . '<li>' . Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Give it a short name using letters, numbers, or underscore only — for example riskBand.')) . '</li>'
                        . '<li>' . Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Put the formula in Value. Use {{answer:Question label}} for an answer, {{user.displayname}} for the person, or {{var:otherName}} for another variable already set.')) . '</li>'
                        . '<li>' . Html::encode(Yii::t('ThiscoveryFormsModule.base', 'On a field, page, or On submit action, choose Custom function and type the same name. Leave Value empty to use this formula, or type a different value to override it for that action only.')) . '</li>'
                        . '<li>' . Html::encode(Yii::t('ThiscoveryFormsModule.base', 'After it has run, use {{var:riskBand}} (or {{riskBand}}) in email templates, later action values, or rich text.')) . '</li>'
                        . '</ol>'
                        . '<p>' . Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Set variable is the one-off version of the same idea: it stores a name and value for this response without needing a saved function. Custom function is for a formula you want to reuse.')) . '</p>',
                ]) ?>
            </div>
            <div class="row g-2 mb-1 d-none d-md-flex">
                <div class="col-md-4"><label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Function name') ?></label></div>
                <div class="col-md-7"><label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Formula or value') ?></label></div>
            </div>
            <div data-cf-fn-list>
                <?php foreach ($fnRows as $fi => $fnRow): ?>
                    <div class="row g-2 mb-2" data-cf-fn-row>
                        <div class="col-md-4">
                            <input type="text" name="custom_functions[<?= (int)$fi ?>][name]" class="form-control"
                                   value="<?= Html::encode((string)($fnRow['name'] ?? '')) ?>"
                                   placeholder="riskBand"
                                   aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Function name')) ?>">
                        </div>
                        <div class="col-md-7">
                            <input type="text" name="custom_functions[<?= (int)$fi ?>][value]" class="form-control"
                                   value="<?= Html::encode((string)($fnRow['value'] ?? '')) ?>"
                                   placeholder="{{answer:Overall health}}"
                                   aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Formula or value')) ?>">
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-sm btn-light" data-cf-remove-fn title="<?= Yii::t('ThiscoveryFormsModule.base', 'Remove') ?>">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-light mb-4" data-cf-add-fn>
                <i class="fa fa-plus"></i> <?= Yii::t('ThiscoveryFormsModule.base', 'Add function') ?>
            </button>

            <div class="cf-field mb-3">
                <span class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'On submit') ?></span>
                <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'These run when the form is submitted, after the last page. Field and page actions are set on each item in the builder.')]) ?>
            </div>
            <?= $this->render('@thiscovery-forms/views/form/_action_rows', [
                'namePrefix' => 'submit_actions',
                'actions' => $formModel->submit_actions ?: [\humhub\modules\thiscoveryForms\services\FormActionService::emptyAction()],
                'emailTemplateOptions' => $emailTemplateOptions,
                'pageKeyOptions' => $submitPageKeys,
            ]) ?>
        </div>
    </details>

    <?php if ($formModel->isConsensus()): ?>
    <details class="cf-set-acc">
        <summary>
            <span class="cf-set-acc__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Consensus') ?></span>
            <span class="cf-set-acc__summary"><?= Yii::t('ThiscoveryFormsModule.base', 'Identity, threshold, freeze') ?></span>
        </summary>
        <div class="cf-set-acc__body">
            <div class="row g-3">
                <div class="col-md-6 form-group cf-field">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Identity') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Identified shows names to managers. Managers only hides names from respondents. Fully anonymous hides names even from managers.')]) ?>
                    <?= Html::activeDropDownList($formModel, 'identity_mode', CustomForm::getIdentityModeLabels(), ['class' => 'form-control']) ?>
                </div>
                <div class="col-md-6 form-group cf-field">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Consensus threshold (%)') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'The share of agreement needed before an item is treated as having reached consensus.')]) ?>
                    <?= Html::activeInput('number', $formModel, 'consensus_threshold', ['class' => 'form-control', 'min' => 1, 'max' => 100]) ?>
                </div>
            </div>
            <div class="cf-checks mt-3">
                <div class="cf-check-setting">
                    <label>
                        <?= Html::activeCheckbox($formModel, 'freeze_on_consensus', ['label' => false]) ?>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Freeze items that reach consensus') ?>
                    </label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Stops further changes on items that have already reached the threshold.')]) ?>
                </div>
                <div class="cf-check-setting">
                    <label>
                        <?= Html::activeCheckbox($formModel, 'require_justification', ['label' => false]) ?>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Require a comment after each choice') ?>
                    </label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Asks for a short comment after each choice question in a consensus round.')]) ?>
                </div>
            </div>
        </div>
    </details>
    <?php endif; ?>
</div>
