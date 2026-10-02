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
$activeSection = $activeSection ?? 'basics';
$rand = new \humhub\modules\thiscoveryForms\services\RandomisationService();
$consentSvc = new \humhub\modules\thiscoveryForms\services\ConsentService();
$loopSvc = new \humhub\modules\thiscoveryForms\services\LoopService();
$quotaSvc = new \humhub\modules\thiscoveryForms\services\QuotaService();
$consentOn = $consentSvc->formEnabled($formModel);
$loopsOn = $loopSvc->formEnabled($formModel);
$randOn = $rand->formEnabled($formModel);
$quotaOn = $quotaSvc->formEnabled($formModel);
$hasFn = false;
foreach ($fnRows as $fnRow) {
    if (trim((string)($fnRow['name'] ?? '')) !== '') {
        $hasFn = true;
        break;
    }
}
$fold = function (string $title, string $body, bool $open = false, string $hint = '', array $attrs = []): string {
    return $this->render('_settings_fold', [
        'title' => $title,
        'body' => $body,
        'open' => $open,
        'hint' => $hint,
        'attrs' => $attrs,
    ]);
};
$paneOpen = static function (string $id) use ($activeSection): bool {
    return $activeSection === $id;
};
?>
<div class="cf-studio__settings">
            <section class="cf-settings-pane<?= $activeSection === 'basics' ? ' is-active' : '' ?>" data-cf-settings-pane="basics" role="tabpanel"<?= $activeSection === 'basics' ? '' : ' hidden' ?>>
                <h3 class="cf-settings-pane__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Basics') ?></h3>
                <?php if (!empty($isNew)): ?>
                    <div class="alert alert-info" role="status">
                        <strong><?= Yii::t('ThiscoveryFormsModule.base', 'Start by naming the form.') ?></strong>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Type the title below, then choose Save form. Saving creates the form. Until you save, the share link, question import, the variable list, the flow chart, translations, and versions are not available. Questions you add on Form builder are stored with that first save.') ?>
                    </div>
                <?php endif; ?>
                <?php ob_start(); ?>
                <div class="cf-field">
                    <span class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Form type') ?></span>
                    <?= $this->render('_setting_guide', ['html' => $kindHtml]) ?>
                    <div class="form-control-plaintext mb-0"><?= Html::encode($kindGuide) ?></div>
                </div>
                <div class="form-group cf-field">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Title') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'The name people see at the top of the form, in lists, and in emails. Keep it short and clear.')]) ?>
                    <?= Html::activeTextInput($formModel, 'title', [
                        'class' => 'form-control form-control-lg',
                        'required' => true,
                        'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'e.g. Membership feedback'),
                        'autofocus' => !empty($isNew),
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
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'Name and description'),
                    ob_get_clean(),
                    true,
                    Yii::t('ThiscoveryFormsModule.base', 'Form type, title, and the description people see.')
                ) ?>
                <?php ob_start(); ?>
                <div class="row g-3">
                    <?php
                    $statusLocked = \humhub\modules\thiscoveryForms\services\FormVersionService::isAvailable()
                        && !(new \humhub\modules\thiscoveryForms\services\FormVersionService())->hasPublishedEdition($formModel);
                    $statusFieldOptions = ['class' => 'form-control'];
                    if ($statusLocked) {
                        $statusFieldOptions['disabled'] = true;
                        $statusFieldOptions['aria-describedby'] = 'cf-status-locked';
                    }
                    ?>
                    <div class="col-md-4 form-group mb-0 cf-field">
                        <label class="cf-label" for="customform-status"><?= Yii::t('ThiscoveryFormsModule.base', 'Status') ?></label>
                        <?= $this->render('_setting_guide', ['text' => $statusLocked
                            ? Yii::t('ThiscoveryFormsModule.base', 'Status stays locked until you publish an edition on the Versions or Share tab. After that, Draft is only for managers and preview, Open accepts responses, and Closed stops new submissions.')
                            : Yii::t('ThiscoveryFormsModule.base', 'Draft is only for managers and preview. Open accepts responses against the published edition. Closed keeps the form visible but stops new submissions.')]) ?>
                        <?php if ($statusLocked): ?>
                            <?= Html::activeHiddenInput($formModel, 'status', ['id' => 'customform-status-value']) ?>
                        <?php endif; ?>
                        <?= Html::activeDropDownList($formModel, 'status', CustomForm::getStatusLabels(), $statusFieldOptions) ?>
                        <?php if ($statusLocked): ?>
                            <p id="cf-status-locked" class="cf-hint text-warning mb-0" style="margin-top:6px">
                                <?= Yii::t('ThiscoveryFormsModule.base', 'Publish an edition before you can change the status.') ?>
                            </p>
                        <?php endif; ?>
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
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'Status and filing'),
                    ob_get_clean(),
                    true,
                    Yii::t('ThiscoveryFormsModule.base', 'Status, folder, and who can view answers.')
                ) ?>
            </section>

            <section class="cf-settings-pane<?= $paneOpen('variables') ? ' is-active' : '' ?>" data-cf-settings-pane="variables" role="tabpanel"<?= $paneOpen('variables') ? '' : ' hidden' ?>>
                <?= $this->render('_studio_variables', [
                    'formModel' => $formModel,
                    'isNew' => !empty($isNew),
                    'fieldList' => $fieldList,
                ]) ?>
            </section>

            <section class="cf-settings-pane<?= $paneOpen('consent') ? ' is-active' : '' ?>" data-cf-settings-pane="consent" role="tabpanel"<?= $paneOpen('consent') ? '' : ' hidden' ?>>
                <h3 class="cf-settings-pane__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Consent') ?></h3>
                <p class="cf-settings-pane__lead"><?= Yii::t('ThiscoveryFormsModule.base', 'A published statement is asked at the start of the form. A Consent question, if you add one, is used instead.') ?></p>
                <input type="hidden" name="econsent_present" value="1">
                <?php ob_start(); ?>
                <?php if (!\humhub\modules\thiscoveryForms\Module::econsentEnabled()): ?>
                    <p class="cf-hint text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'Consent is switched off for the whole site, so this form will not ask yet. Turn it on under Administration, Thiscovery Forms.') ?></p>
                <?php endif; ?>
                <div class="form-group cf-field">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Use consent on this form') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Asks for a published information sheet before the form starts. Consent must also be on under Administration. A Consent question in the builder is used instead of that opening sheet.')]) ?>
                    <?= Html::dropDownList('econsent_enabled', $consentOn ? '1' : '0', [
                        '0' => Yii::t('ThiscoveryFormsModule.base', 'Off'),
                        '1' => Yii::t('ThiscoveryFormsModule.base', 'On'),
                    ], ['class' => 'form-control', 'style' => 'max-width: 16rem']) ?>
                </div>
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'Use on this form'),
                    ob_get_clean(),
                    true,
                    Yii::t('ThiscoveryFormsModule.base', 'Off until this is on and an administrator has turned consent on for the site.')
                ) ?>
                <?php ob_start(); ?>
                <div class="row g-3">
                    <div class="col-md-6 form-group cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'When the sheet changes') ?></label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Keep the version they started, ask again on the next visit, or email people who already agreed.')]) ?>
                        <?= Html::dropDownList('econsent_reconsent', (string)$formModel->getSetting('reconsent', 'off'), [
                            'off' => Yii::t('ThiscoveryFormsModule.base', 'Keep the version they started'),
                            'next_visit' => Yii::t('ThiscoveryFormsModule.base', 'Ask again on the next visit'),
                            'email' => Yii::t('ThiscoveryFormsModule.base', 'Email people who already agreed'),
                        ], ['class' => 'form-control']) ?>
                    </div>
                    <div class="col-md-6 form-group cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Store IP and browser hashes') ?></label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Stores only a hash of the IP address and browser, and only when this is on. The default is off. A fully anonymous consent record is not linked to the answer.')]) ?>
                        <?= Html::dropDownList('consent_store_client_hashes', (string)$formModel->getSetting('consent_store_client_hashes', '0') === '1' ? '1' : '0', [
                            '0' => Yii::t('ThiscoveryFormsModule.base', 'Off'),
                            '1' => Yii::t('ThiscoveryFormsModule.base', 'On'),
                        ], ['class' => 'form-control']) ?>
                    </div>
                    <div class="col-md-12 form-group cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Not consented message') ?></label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Shown when someone refuses a required statement. That response is closed and is not counted as complete. Leave empty for the default message.')]) ?>
                        <?= Html::textInput('not_consented_message', (string)$formModel->getSetting('not_consented_message', ''), ['class' => 'form-control']) ?>
                    </div>
                    <?php if (empty($isNew)): ?>
                    <div class="col-md-12">
                        <?= Html::a(Yii::t('ThiscoveryFormsModule.base', 'Edit consent documents'), $formModel->actionUrl(['/thiscovery-forms/consent/index', 'id' => $formModel->id]), ['class' => 'btn btn-default btn-sm']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'After someone answers'),
                    ob_get_clean(),
                    $consentOn,
                    Yii::t('ThiscoveryFormsModule.base', 'Re-consent, stored hashes, refusal message, and consent documents.')
                ) ?>
            </section>

            <section class="cf-settings-pane<?= $paneOpen('loops') ? ' is-active' : '' ?>" data-cf-settings-pane="loops" role="tabpanel"<?= $paneOpen('loops') ? '' : ' hidden' ?>>
                <h3 class="cf-settings-pane__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Loops') ?></h3>
                <p class="cf-settings-pane__lead"><?= Yii::t('ThiscoveryFormsModule.base', 'A group can repeat from a fixed list, selected options, or a number. One repeating group can contain one other.') ?></p>
                <input type="hidden" name="loops_present" value="1">
                <div class="form-group cf-field">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Use loops on this form') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Repeats a question group. Loops must also be on under Administration. One group can contain one other group. A third level cannot be published.')]) ?>
                    <?= Html::dropDownList('loops_enabled', $loopsOn ? '1' : '0', [
                        '0' => Yii::t('ThiscoveryFormsModule.base', 'Off'),
                        '1' => Yii::t('ThiscoveryFormsModule.base', 'On'),
                    ], ['class' => 'form-control', 'style' => 'max-width: 16rem']) ?>
                </div>
            </section>

            <section class="cf-settings-pane<?= $paneOpen('randomisation') ? ' is-active' : '' ?>" data-cf-settings-pane="randomisation" role="tabpanel"<?= $paneOpen('randomisation') ? '' : ' hidden' ?>>
                <h3 class="cf-settings-pane__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Randomisation') ?></h3>
                <p class="cf-settings-pane__lead"><?= Yii::t('ThiscoveryFormsModule.base', 'The server draws the order and the arm, and stores both on the response.') ?></p>
                <input type="hidden" name="randomisation_present" value="1">
                <?php ob_start(); ?>
                <div class="form-group cf-field">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Use randomisation on this form') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'The server draws option order, question order, page order, and arms, and stores them on the response. Randomisation must also be on under Administration. Each response gets its own option order.')]) ?>
                    <?= Html::dropDownList('randomisation_enabled', $randOn ? '1' : '0', [
                        '0' => Yii::t('ThiscoveryFormsModule.base', 'Off'),
                        '1' => Yii::t('ThiscoveryFormsModule.base', 'On'),
                    ], ['class' => 'form-control', 'style' => 'max-width: 16rem']) ?>
                </div>
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'Use on this form'),
                    ob_get_clean(),
                    true,
                    Yii::t('ThiscoveryFormsModule.base', 'Off until this is on and an administrator has turned randomisation on.')
                ) ?>
                <?php ob_start(); ?>
                <div class="row g-3">
                    <div class="col-md-6 form-group cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Arm method') ?></label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Simple weighted draws from the weights. Block hands out a shuffled block in the weight ratio. Least filled prefers the arm with fewer assignments, with a random element so the next arm cannot be predicted. Stratified block does that inside each stratum.')]) ?>
                        <?= Html::dropDownList('randomisation_method', $rand->config($formModel)['method'], [
                            'simple' => Yii::t('ThiscoveryFormsModule.base', 'Simple weighted'),
                            'block' => Yii::t('ThiscoveryFormsModule.base', 'Block'),
                            'least_filled' => Yii::t('ThiscoveryFormsModule.base', 'Least filled'),
                            'stratified' => Yii::t('ThiscoveryFormsModule.base', 'Stratified block'),
                        ], ['class' => 'form-control']) ?>
                    </div>
                    <div class="col-md-6 form-group cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Block size') ?></label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Used by block and stratified methods. It must be a multiple of the total arm weight so every block keeps the ratio.')]) ?>
                        <?= Html::textInput('randomisation_block_size', (string)$rand->config($formModel)['block_size'], ['class' => 'form-control']) ?>
                    </div>
                    <div class="col-md-6 form-group cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Arms') ?></label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'One arm per line: code|name|weight. Example: usual|Usual leaflet|1')]) ?>
                        <?= Html::textarea('randomisation_arms', $rand->armsText($formModel), ['class' => 'form-control', 'rows' => 4]) ?>
                    </div>
                    <div class="col-md-6 form-group cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Stratify by') ?></label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'One factor per line: field:variable or panel:attribute. A fully anonymous form cannot use a panel attribute.')]) ?>
                        <?= Html::textarea('randomisation_strata', $rand->strataText($formModel), ['class' => 'form-control', 'rows' => 4]) ?>
                    </div>
                </div>
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'Arms'),
                    ob_get_clean(),
                    $randOn,
                    Yii::t('ThiscoveryFormsModule.base', 'Method, block size, arm list, and strata.')
                ) ?>
                <?php ob_start(); ?>
                <div class="row g-3">
                    <div class="col-md-6 form-group cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Assign') ?></label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Assign the arm when the response starts, or after the respondent leaves the page whose key you set.')]) ?>
                        <?= Html::dropDownList('randomisation_assign', $rand->config($formModel)['assign'], [
                            'start' => Yii::t('ThiscoveryFormsModule.base', 'When the response starts'),
                            'after_page' => Yii::t('ThiscoveryFormsModule.base', 'After a page'),
                        ], ['class' => 'form-control']) ?>
                    </div>
                    <div class="col-md-6 form-group cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Page key') ?></label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'The page key from the builder. The arm is assigned when the respondent leaves that page. Used only when Assign is After a page.')]) ?>
                        <?= Html::textInput('randomisation_assign_page', $rand->config($formModel)['assign_page'], ['class' => 'form-control']) ?>
                    </div>
                    <div class="col-md-12 form-group cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Screened-out message') ?></label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Shown when a question uses “End as screened out”. That response is closed and is not counted as a completed questionnaire.')]) ?>
                        <?= Html::textInput('screen_out_message', $rand->config($formModel)['screen_out_message'], ['class' => 'form-control']) ?>
                    </div>
                </div>
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'When to assign'),
                    ob_get_clean(),
                    false,
                    Yii::t('ThiscoveryFormsModule.base', 'Start of the response, or after a page. Plus the screened-out message.')
                ) ?>
            </section>

            <section class="cf-settings-pane<?= $activeSection === 'quotas' ? ' is-active' : '' ?>" data-cf-settings-pane="quotas" role="tabpanel"<?= $activeSection === 'quotas' ? '' : ' hidden' ?>>
                <h3 class="cf-settings-pane__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Quotas') ?></h3>
                <input type="hidden" name="quotas_present" value="1">
                <?php ob_start(); ?>
                <p class="cf-hint text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'A full cell keeps the answers and marks the response over quota. Reservations last 60 minutes only when a quota turns them on.') ?></p>
                <div class="form-group cf-field">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Use quotas on this form') ?></label>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Counts responses against targets. Quotas must also be on under Administration. A full cell keeps the answers and marks the response over quota. Editing a completed response does not change the count.')]) ?>
                    <?= Html::dropDownList('quotas_enabled', $quotaOn ? '1' : '0', [
                        '0' => Yii::t('ThiscoveryFormsModule.base', 'Off'),
                        '1' => Yii::t('ThiscoveryFormsModule.base', 'On'),
                    ], ['class' => 'form-control', 'style' => 'max-width: 16rem']) ?>
                </div>
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'Use on this form'),
                    ob_get_clean(),
                    true,
                    Yii::t('ThiscoveryFormsModule.base', 'Off until this is on and an administrator has turned quotas on.')
                ) ?>
                <?php ob_start(); ?>
                <div class="row g-3">
                    <div class="col-md-6 form-group cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Assign the least-filled open arm') ?></label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Places the respondent in the open arm they qualify for that has the most room. If every qualifying arm is full, that quota’s action runs and no arm is kept.')]) ?>
                        <?= Html::dropDownList('quota_assign_arm', (string)$formModel->getSetting('quota_assign_arm', '0') === '1' ? '1' : '0', [
                            '0' => Yii::t('ThiscoveryFormsModule.base', 'Off'),
                            '1' => Yii::t('ThiscoveryFormsModule.base', 'On'),
                        ], ['class' => 'form-control']) ?>
                    </div>
                    <div class="col-md-6 form-group cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Email when a quota fills') ?></label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Sends this address a message when a quota reaches its target. The event is also recorded on the quota. It is not posted to another system.')]) ?>
                        <?= Html::textInput('quota_full_email', (string)$formModel->getSetting('quota_full_email', ''), ['class' => 'form-control', 'placeholder' => 'name@example.org']) ?>
                    </div>
                    <?php if (empty($isNew)): ?>
                    <div class="col-md-12">
                        <?= Html::a(Yii::t('ThiscoveryFormsModule.base', 'Edit quotas'), $formModel->actionUrl(['/thiscovery-forms/quota/index', 'id' => $formModel->id]), ['class' => 'btn btn-default btn-sm']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'When a quota fills'),
                    ob_get_clean(),
                    $quotaOn,
                    Yii::t('ThiscoveryFormsModule.base', 'Least-filled arm, notification email, and the quota editor.')
                ) ?>
            </section>

            <section class="cf-settings-pane<?= $activeSection === 'end' ? ' is-active' : '' ?>" data-cf-settings-pane="end" role="tabpanel"<?= $activeSection === 'end' ? '' : ' hidden' ?>>
                <h3 class="cf-settings-pane__title"><?= Yii::t('ThiscoveryFormsModule.base', 'End of survey') ?></h3>
                <p class="cf-settings-pane__lead">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Choose what people see after they finish, and what to show if they have already submitted.') ?>
                </p>

                <details class="cf-set-acc" open data-cf-completion-mode>
                    <summary>
                        <span class="cf-set-acc__title"><?= Yii::t('ThiscoveryFormsModule.base', 'After a successful submission') ?></span>
                        <span class="cf-set-acc__summary"><?= Yii::t('ThiscoveryFormsModule.base', 'Thank-you message, or send people straight to another URL.') ?></span>
                    </summary>
                    <div class="cf-set-acc__body">
                    <div class="cf-radio-stack">
                        <?= Html::activeRadioList($formModel, 'completion_mode', CustomForm::getCompletionModeLabels(), [
                            'item' => static function ($index, $label, $name, $checked, $value) {
                                return '<label class="cf-radio-stack__item">'
                                    . Html::radio($name, $checked, [
                                        'value' => $value,
                                        'data-cf-completion-choice' => $value,
                                    ])
                                    . '<span>' . Html::encode($label) . '</span></label>';
                            },
                            'separator' => '',
                            'unselect' => null,
                        ]) ?>
                    </div>

                    <div class="cf-end-block__panel" data-cf-completion-panel="message">
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
                        <div class="cf-checks">
                            <div class="cf-check-setting">
                                <label>
                                    <?= Html::activeCheckbox($formModel, 'completion_button_enabled', ['label' => false]) ?>
                                    <?= Yii::t('ThiscoveryFormsModule.base', 'Show a button under the message') ?>
                                </label>
                                <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'When enabled, a button appears under the thank-you message. Leave the URL blank to send people back to this form.')]) ?>
                            </div>
                        </div>
                        <div class="row g-3" data-cf-completion-button-fields>
                            <div class="col-md-6 form-group cf-field">
                                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Button label') ?></label>
                                <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Text on the button under the thank-you message.')]) ?>
                                <?= Html::activeTextInput($formModel, 'completion_button_label', [
                                    'class' => 'form-control',
                                    'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Back to form'),
                                ]) ?>
                            </div>
                            <div class="col-md-6 form-group cf-field">
                                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Button URL') ?>
                                    <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
                                </label>
                                <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Where the thank-you button goes. Leave blank to return to this form. Use a full https:// link or a path starting with /.')]) ?>
                                <?= Html::activeTextInput($formModel, 'completion_button_url', [
                                    'class' => 'form-control',
                                    'placeholder' => 'https://… or /path',
                                ]) ?>
                            </div>
                        </div>
                    </div>

                    <div class="cf-end-block__panel" data-cf-completion-panel="redirect" hidden>
                        <div class="form-group cf-field mb-0">
                            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Redirect URL') ?></label>
                            <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'After submit, open this address instead of the thank-you page. Use a full https:// link or a path starting with /.')]) ?>
                            <?= Html::activeTextInput($formModel, 'completion_redirect_url', [
                                'class' => 'form-control',
                                'placeholder' => 'https://example.org/thanks',
                            ]) ?>
                        </div>
                    </div>
                    </div>
                </details>

                <details class="cf-set-acc">
                    <summary>
                        <span class="cf-set-acc__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Already submitted') ?></span>
                        <span class="cf-set-acc__summary"><?= Yii::t('ThiscoveryFormsModule.base', 'Message and optional button when they cannot submit again.') ?></span>
                    </summary>
                    <div class="cf-set-acc__body">
                    <div class="form-group cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Already submitted message') ?>
                            <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
                        </label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Shown when this person has already submitted and multiple submissions are not allowed. Use {formName} for the form title. Leave empty for the default message.', ['formName' => '{formName}'])]) ?>
                        <div class="cf-rich-editor" data-cf-rich-editor>
                            <?= EditorField::widget([
                                'model' => $formModel,
                                'attribute' => 'already_submitted_message',
                                'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'You have already submitted {formName}. Multiple submissions are not allowed', ['formName' => '{formName}']),
                                'height' => 180,
                                'profile' => 'simple',
                            ]) ?>
                        </div>
                    </div>
                    <div class="cf-checks">
                        <div class="cf-check-setting">
                            <label>
                                <?= Html::activeCheckbox($formModel, 'already_submitted_button_enabled', ['label' => false]) ?>
                                <?= Yii::t('ThiscoveryFormsModule.base', 'Show a button under the message') ?>
                            </label>
                            <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Optional button on the already-submitted screen. Leave the URL blank to send people to the forms list.')]) ?>
                        </div>
                    </div>
                    <div class="row g-3" data-cf-already-button-fields>
                        <div class="col-md-6 form-group cf-field">
                            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Button label') ?></label>
                            <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Text on the button on the already-submitted screen.')]) ?>
                            <?= Html::activeTextInput($formModel, 'already_submitted_button_label', [
                                'class' => 'form-control',
                                'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Continue'),
                            ]) ?>
                        </div>
                        <div class="col-md-6 form-group cf-field">
                            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Button URL') ?>
                                <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
                            </label>
                            <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Where the already-submitted button goes. Leave blank to open the forms list.')]) ?>
                            <?= Html::activeTextInput($formModel, 'already_submitted_button_url', [
                                'class' => 'form-control',
                                'placeholder' => 'https://… or /path',
                            ]) ?>
                        </div>
                    </div>
                    </div>
                </details>
            </section>

            <section class="cf-settings-pane<?= $activeSection === 'access' ? ' is-active' : '' ?>" data-cf-settings-pane="access" role="tabpanel"<?= $activeSection === 'access' ? '' : ' hidden' ?>>
                <h3 class="cf-settings-pane__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Who can take part') ?></h3>
                <?php ob_start(); ?>
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
                </div>
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'Submissions'),
                    ob_get_clean(),
                    true,
                    Yii::t('ThiscoveryFormsModule.base', 'Multiple submissions and anonymous fill.')
                ) ?>
                <?php ob_start(); ?>
                <div class="cf-checks">
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
                </div>
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'Editing and progress'),
                    ob_get_clean(),
                    false,
                    Yii::t('ThiscoveryFormsModule.base', 'Edit after submit, save and resume, and incomplete responses.')
                ) ?>
                <?php if ($formModel->isSurvey() || $formModel->isLongitudinal() || $formModel->isEq5d()): ?>
                <?php ob_start(); ?>
                <div class="cf-checks">
                    <div class="cf-check-setting">
                        <label>
                            <?= Html::activeCheckbox($formModel, 'use_waves', ['label' => false]) ?>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Use waves') ?>
                        </label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Use waves for repeating measures on the same survey. Save the form, then set up the panel and waves on the Panel & waves tab.')]) ?>
                    </div>
                    <div class="form-group cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Where waves live') ?></label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Per survey: open and close waves on this form. Per panel: forms that share a panel share the same wave calendar.')]) ?>
                        <?= Html::activeDropDownList($formModel, 'wave_scope', \humhub\modules\thiscoveryForms\models\ModuleSettings::waveScopeLabels(), [
                            'class' => 'form-control',
                        ]) ?>
                    </div>
                </div>
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'Waves'),
                    ob_get_clean(),
                    false,
                    Yii::t('ThiscoveryFormsModule.base', 'Repeating measures on this survey or a shared panel calendar.')
                ) ?>
                <?php endif; ?>
            </section>

            <section class="cf-settings-pane<?= $activeSection === 'display' ? ' is-active' : '' ?>" data-cf-settings-pane="display" role="tabpanel"<?= $activeSection === 'display' ? '' : ' hidden' ?>>
                <h3 class="cf-settings-pane__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Participant display') ?></h3>
                <?php ob_start(); ?>
                <?php
                $displayOpts = [
                    '' => Yii::t('ThiscoveryFormsModule.base', 'Use site default'),
                    '1' => Yii::t('ThiscoveryFormsModule.base', 'Show'),
                    '0' => Yii::t('ThiscoveryFormsModule.base', 'Hide'),
                ];
                $displayGuides = [
                    'show_title' => Yii::t('ThiscoveryFormsModule.base', 'Shows the form title at the top of the fill page. Hide it if the title is already clear from the page or space context.'),
                    'show_description' => Yii::t('ThiscoveryFormsModule.base', 'Shows the form description under the title on the fill page. Hide it when the description is only for managers or lists.'),
                    'show_progress' => Yii::t('ThiscoveryFormsModule.base', 'Shows a progress bar for how much of the form is complete. Most useful on multi-page forms.'),
                    'show_page_indicator' => Yii::t('ThiscoveryFormsModule.base', 'Shows page numbers (for example Page 2 of 5) on multi-page forms. Has no effect on a single-page form.'),
                ];
                $display = is_array($formModel->display) ? $formModel->display : [];
                foreach (\humhub\modules\thiscoveryForms\services\DisplaySettings::KEYS as $dKey):
                ?>
                    <div class="form-group cf-field">
                        <label class="cf-label"><?= Html::encode(\humhub\modules\thiscoveryForms\services\DisplaySettings::labels()[$dKey] ?? $dKey) ?></label>
                        <?= $this->render('_setting_guide', ['text' => $displayGuides[$dKey] ?? '']) ?>
                        <?= Html::dropDownList('CustomForm[display][' . $dKey . ']', $display[$dKey] ?? '', $displayOpts, [
                            'class' => 'form-control',
                        ]) ?>
                    </div>
                <?php endforeach; ?>
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'Fill page'),
                    ob_get_clean(),
                    true,
                    Yii::t('ThiscoveryFormsModule.base', 'Leave as site default, or show or hide the title, description, progress, and page numbers.')
                ) ?>
            </section>

            <section class="cf-settings-pane<?= $activeSection === 'sharing' ? ' is-active' : '' ?>" data-cf-settings-pane="sharing" role="tabpanel"<?= $activeSection === 'sharing' ? '' : ' hidden' ?>>
                <h3 class="cf-settings-pane__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Sharing and display') ?></h3>
                <?php ob_start(); ?>
                <div class="cf-checks">
                    <div class="cf-check-setting">
                        <label>
                            <?= Html::activeCheckbox($formModel, 'public_dashboard_enabled', ['label' => false]) ?>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Share dashboard without sign-in') ?>
                        </label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Creates a secret link on the Share tab so people can view charts without logging in. Anyone with the link can see the dashboard.')]) ?>
                    </div>
                </div>
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'Dashboard link'),
                    ob_get_clean(),
                    (bool)$formModel->public_dashboard_enabled,
                    Yii::t('ThiscoveryFormsModule.base', 'Secret link so people can view charts without signing in.')
                ) ?>
                <?php ob_start(); ?>
                <div class="cf-checks">
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
                </div>
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'Menu and header'),
                    ob_get_clean(),
                    false,
                    Yii::t('ThiscoveryFormsModule.base', 'Side menu link, and filling the form without the site header.')
                ) ?>
                <?php if ($isPoll): ?>
                <?php ob_start(); ?>
                <div class="cf-checks">
                    <div class="cf-check-setting">
                        <label>
                            <?= Html::activeCheckbox($formModel, 'show_results', ['label' => false]) ?>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Show results after voting') ?>
                        </label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'After someone votes, show the poll totals on the fill page.')]) ?>
                    </div>
                </div>
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'Poll results'),
                    ob_get_clean(),
                    false,
                    Yii::t('ThiscoveryFormsModule.base', 'Show totals on the fill page after someone votes.')
                ) ?>
                <?php endif; ?>
            </section>

            <section class="cf-settings-pane<?= $activeSection === 'enrol' ? ' is-active' : '' ?>" data-cf-settings-pane="enrol" role="tabpanel"<?= $activeSection === 'enrol' ? '' : ' hidden' ?>>
                <h3 class="cf-settings-pane__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Panel enrolment') ?></h3>
                <div class="cf-enrol-panel" data-cf-enrol>
                    <?php ob_start(); ?>
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
                    <?= $fold(
                        Yii::t('ThiscoveryFormsModule.base', 'Which panel'),
                        ob_get_clean(),
                        true,
                        Yii::t('ThiscoveryFormsModule.base', 'Do nothing, add completers to an existing panel, or create one.')
                    ) ?>
                    <?php ob_start(); ?>
                    <div class="cf-checks">
                        <div class="cf-check-setting">
                            <label>
                                <?= Html::activeCheckbox($formModel, 'log_panel_activity', ['label' => false]) ?>
                                <?= Yii::t('ThiscoveryFormsModule.base', 'Record each completion on the panel') ?>
                            </label>
                            <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'When this form uses a panel, completions are listed on matching members automatically (invite link, signed-in email, or an email field on the form). Tick this to also record on the enrolment panel chosen above.')]) ?>
                        </div>
                    </div>
                    <?= $fold(
                        Yii::t('ThiscoveryFormsModule.base', 'Activity log'),
                        ob_get_clean(),
                        false,
                        Yii::t('ThiscoveryFormsModule.base', 'Also record each completion on the enrolment panel.')
                    ) ?>
                </div>
            </section>

            <section class="cf-settings-pane<?= $activeSection === 'email' ? ' is-active' : '' ?>" data-cf-settings-pane="email" role="tabpanel"<?= $activeSection === 'email' ? '' : ' hidden' ?>>
                <h3 class="cf-settings-pane__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Email templates') ?></h3>
                <?php ob_start(); ?>
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
                </div>
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'Messages'),
                    ob_get_clean(),
                    true,
                    Yii::t('ThiscoveryFormsModule.base', 'Invite, wave, and post-completion templates.')
                ) ?>
                <?php ob_start(); ?>
                <div class="row g-3">
                    <div class="col-md-8 form-group cf-field">
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
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'Reminders'),
                    ob_get_clean(),
                    false,
                    Yii::t('ThiscoveryFormsModule.base', 'Template and how many days a wave stays open before a reminder.')
                ) ?>
            </section>

            <section class="cf-settings-pane<?= $activeSection === 'languages' ? ' is-active' : '' ?>" data-cf-settings-pane="languages" role="tabpanel"<?= $activeSection === 'languages' ? '' : ' hidden' ?>>
                <h3 class="cf-settings-pane__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Languages') ?></h3>
                <?php ob_start(); ?>
                <div class="form-group cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Source language') ?></label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'The source language is what you write in the builder. Extra languages are edited on the Translations tab.')]) ?>
                        <?= Html::activeDropDownList(
                            $formModel,
                            'source_language',
                            \humhub\modules\thiscoveryForms\services\TranslationService::selectableLanguageLabels($formModel),
                            ['class' => 'form-control', 'style' => 'max-width: 20rem']
                        ) ?>
                </div>
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'Source language'),
                    ob_get_clean(),
                    true,
                    Yii::t('ThiscoveryFormsModule.base', 'The language you write in the builder.')
                ) ?>
                <?php ob_start(); ?>
                <div class="form-group cf-field">
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
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'Enabled languages'),
                    ob_get_clean(),
                    false,
                    Yii::t('ThiscoveryFormsModule.base', 'Languages people can switch to on the fill page.')
                ) ?>
            </section>

            <section class="cf-settings-pane<?= $activeSection === 'actions' ? ' is-active' : '' ?>" data-cf-settings-pane="actions" role="tabpanel"<?= $activeSection === 'actions' ? '' : ' hidden' ?>>
                <h3 class="cf-settings-pane__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Actions and functions') ?></h3>
                <?php ob_start(); ?>
                <div class="cf-field mb-3">
                    <span class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Custom functions and variables') ?></span>
                    <?= $this->render('_setting_guide', [
                        'html' => '<p>' . Html::encode(Yii::t('ThiscoveryFormsModule.base', 'A named formula is written once and reused as fn:name. It uses the same formula language as a calculated question.')) . '</p>'
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
                <button type="button" class="btn btn-sm btn-light" data-cf-add-fn>
                    <i class="fa fa-plus"></i> <?= Yii::t('ThiscoveryFormsModule.base', 'Add function') ?>
                </button>
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'Custom functions'),
                    ob_get_clean(),
                    $hasFn,
                    Yii::t('ThiscoveryFormsModule.base', 'Named formulas reused as fn:name.')
                ) ?>
                <?php ob_start(); ?>
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
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'On submit'),
                    ob_get_clean(),
                    false,
                    Yii::t('ThiscoveryFormsModule.base', 'Actions that run after the last page.')
                ) ?>
            </section>

            <?php if ($formModel->isConsensus()): ?>
            <section class="cf-settings-pane<?= $activeSection === 'consensus' ? ' is-active' : '' ?>" data-cf-settings-pane="consensus" role="tabpanel"<?= $activeSection === 'consensus' ? '' : ' hidden' ?>>
                <h3 class="cf-settings-pane__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Consensus') ?></h3>
                <?php ob_start(); ?>
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
                    <div class="col-md-3 form-group cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Agree from') ?></label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Codes inside this band are added together. Leave both boxes empty to keep using the single most common code.')]) ?>
                        <?= Html::activeTextInput($formModel, 'consensus_agree_from', ['class' => 'form-control']) ?>
                    </div>
                    <div class="col-md-3 form-group cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Agree to') ?></label>
                        <?= Html::activeTextInput($formModel, 'consensus_agree_to', ['class' => 'form-control']) ?>
                    </div>
                    <div class="col-md-3 form-group cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Disagree from') ?></label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'If the disagree band also reaches the threshold, the item is not frozen.')]) ?>
                        <?= Html::activeTextInput($formModel, 'consensus_disagree_from', ['class' => 'form-control']) ?>
                    </div>
                    <div class="col-md-3 form-group cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Disagree to') ?></label>
                        <?= Html::activeTextInput($formModel, 'consensus_disagree_to', ['class' => 'form-control']) ?>
                    </div>
                    <div class="col-md-6 form-group cf-field">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Codes to exclude') ?></label>
                        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Comma-separated codes left out of the share, such as N/A.')]) ?>
                        <?= Html::activeTextInput($formModel, 'consensus_exclude_codes', ['class' => 'form-control']) ?>
                    </div>
                </div>
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'Scoring'),
                    ob_get_clean(),
                    true,
                    Yii::t('ThiscoveryFormsModule.base', 'Identity, threshold, and the agree and disagree bands.')
                ) ?>
                <?php ob_start(); ?>
                <div class="cf-checks">
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
                <?= $fold(
                    Yii::t('ThiscoveryFormsModule.base', 'After consensus'),
                    ob_get_clean(),
                    false,
                    Yii::t('ThiscoveryFormsModule.base', 'Freeze items that reach the threshold, and require a comment.')
                ) ?>
            </section>
            <?php endif; ?>
</div>
