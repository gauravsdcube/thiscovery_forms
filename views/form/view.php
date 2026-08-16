<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\RichHtml;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\thiscoveryForms\services\FormPager;
use humhub\modules\thiscoveryForms\services\HtmlSanitizer;
use humhub\modules\thiscoveryForms\services\TranslationService;
use humhub\modules\thiscoveryForms\services\VariableSubstitutor;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;
use yii\helpers\Json;

/** @var CustomForm $formModel */
/** @var SubmitForm $submit */
/** @var FormAnswer|null $existing */
/** @var FormAnswer|null $savedDraft */
/** @var $contentContainer */
/** @var \humhub\modules\thiscoveryForms\services\FillContext|null $fillContext */
/** @var string $panelToken */

$savedDraft = $savedDraft ?? null;
$fillContext = $fillContext ?? null;
$panelToken = $panelToken ?? (string)Yii::$app->request->get('token', '');
$ownDraft = $ownDraft ?? null;
$startNew = !empty($startNew);
$editingAnswer = !empty($editingAnswer);

ThiscoveryFormsAsset::register($this);

$pager = (new FormPager())->buildPages($formModel->fields);
$pages = $pager['pages'];
$pageKeyIndex = $pager['pageKeyIndex'];

$pagePayload = [];
$pipe = new VariableSubstitutor();
$user = Yii::$app->user->identity;
foreach ($pages as $page) {
    $branches = [];
    if ($page['break'] instanceof FormField) {
        $branches = $page['break']->getPageBreakConfig()['branches'];
    }
    $fieldLogic = [];
    foreach ($page['items'] as $item) {
        $logic = $item->getLogic();
        if (!empty($logic['rules'])) {
            $fieldLogic[] = [
                'fieldId' => (int)$item->id,
                'logic' => $logic,
            ];
        }
    }
    $pagePayload[] = [
        'index' => $page['index'],
        'pageKey' => $page['pageKey'],
        'title' => $page['title'],
        'branches' => $branches,
        'fieldLogic' => $fieldLogic,
        'fieldIds' => array_map(static fn(FormField $f) => (int)$f->id, $page['items']),
    ];
}

$this->registerJsConfig('thiscoveryForms', [
    'pages' => $pagePayload,
    'pageKeyIndex' => $pageKeyIndex,
    'pageLabel' => Yii::t('ThiscoveryFormsModule.base', 'Page {current} of {total}'),
    'nextLabel' => Yii::t('ThiscoveryFormsModule.base', 'Next'),
    'backLabel' => Yii::t('ThiscoveryFormsModule.base', 'Back'),
    'requiredPage' => Yii::t('ThiscoveryFormsModule.base', 'Please complete the required fields on this page.'),
    'specifyLabel' => Yii::t('ThiscoveryFormsModule.base', 'Please specify'),
    'specifyPlaceholder' => Yii::t('ThiscoveryFormsModule.base', 'Type your answer'),
    'copiedLabel' => Yii::t('ThiscoveryFormsModule.base', 'Copied'),
    'copyFailedLabel' => Yii::t('ThiscoveryFormsModule.base', 'Could not copy'),
    'startPage' => ($existing && $existing->isInProgress() && $existing->current_page !== null)
        ? (int)$existing->current_page
        : 0,
]);
$this->registerJs('humhub.require("thiscoveryForms").initFill("#cf-fill");', \yii\web\View::POS_READY);

$isDraft = $existing && $existing->isInProgress();
$canSubmit = $isDraft
    ? $formModel->isOpen()
    : ($existing
        ? ($formModel->canEditOwnAnswer($existing) || $formModel->canManage())
        : ($formModel->canAnswer() || ($fillContext && ($fillContext->tokenAccess || $fillContext->member))));
if ($fillContext && $fillContext->blockReason && !$formModel->canManage() && !($existing && $existing->isComplete() && ($formModel->canEditOwnAnswer($existing) || $formModel->canManage()))) {
    $canSubmit = $existing && $existing->isInProgress() ? $canSubmit : false;
}
$resumeEnabled = $formModel->allowsResume();
$identifiedBlocked = !$formModel->allow_multiple
    && !$formModel->allowsAnonymous()
    && !$formModel->isLongitudinal()
    && !$formModel->isConsensus()
    && $formModel->hasUserAnswered()
    && !$isDraft;
if ($resumeEnabled && !$editingAnswer && $existing && $existing->isComplete() && !$formModel->allow_multiple) {
    $canSubmit = false;
}
$canSaveProgress = $resumeEnabled && $canSubmit && $formModel->isOpen() && (!$existing || $isDraft);

$answerableCount = 0;
foreach ($formModel->fields as $f) {
    if ($f->collectsAnswer()) {
        $answerableCount++;
    }
}
$multiPage = count($pages) > 1;
$questionNum = 0;
$customCss = $formModel->getSafeCustomCss();
$alreadyAnsweredAnon = $formModel->allowsAnonymous()
    && !$formModel->allow_multiple
    && $formModel->hasGuestAnswered($fillContext?->wave?->id, $fillContext?->round?->id)
    && !$isDraft;
$showToolbar = $formModel->canManage() || $formModel->canViewAnswers();
$resumeCode = ($existing && $existing->isInProgress()) ? (string)$existing->resume_code : '';
$hasResumeIntent = $isDraft && (
    (string)Yii::$app->request->get('resume', '') !== ''
    || (string)Yii::$app->request->get('continue', '') === '1'
    || $savedDraft
);
$showResumeGate = $resumeEnabled
    && $formModel->isOpen()
    && !$editingAnswer
    && !$startNew
    && !$hasResumeIntent
    && !$identifiedBlocked
    && !($existing && $existing->isComplete());
$alreadySubmittedMessage = $formModel->getAlreadySubmittedMessage();
?>

<div class="cf-fill-page" id="cf-fill" data-cf-multipage="<?= $multiPage ? '1' : '0' ?>">
    <?php if ($customCss !== ''): ?>
        <style type="text/css"><?= $customCss ?></style>
    <?php endif; ?>

    <?php if ($showToolbar): ?>
        <div class="cf-fill-toolbar">
            <div class="cf-fill-toolbar__actions">
                <?php if ($formModel->canManage()): ?>
                    <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Edit'))
                        ->link(Url::toEdit($formModel))->sm()->icon('pencil') ?>
                <?php endif; ?>
                <?php if ($formModel->canViewAnswers()): ?>
                    <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Dashboard'))
                        ->link(Url::toDashboard($formModel))->sm()->icon('bar-chart') ?>
                    <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Answers'))
                        ->link(Url::toAnswers($formModel))->sm()->icon('list') ?>
                <?php endif; ?>
                <?php if ($formModel->isProject()): ?>
                    <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Catalogue'))
                        ->link(Url::toCatalogue($formModel))->sm()->icon('folder-open') ?>
                <?php endif; ?>
            </div>
            <?php if ($formModel->canManage()): ?>
                <div class="cf-fill-toolbar__danger">
                    <?= Html::beginForm(Url::toDelete($formModel), 'post', ['class' => 'cf-fill-toolbar__delete']) ?>
                    <?= Button::danger(Yii::t('ThiscoveryFormsModule.base', 'Delete form'))
                        ->confirm(Yii::t('ThiscoveryFormsModule.base', 'Delete this form and all submissions?'))
                        ->submit()
                        ->sm() ?>
                    <?= Html::endForm() ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="cf-fill-body thiscovery-forms-fill">
        <header class="cf-fill-hero">
            <h1 class="cf-fill-hero__title"><?= Html::encode($formModel->title) ?></h1>
            <?php
            $enabledLangs = $formModel->getEnabledLanguages();
            if (count($enabledLangs) > 1):
                $langLabels = TranslationService::languageLabels();
                $currentLang = $fillContext->language ?? $formModel->getSourceLanguage();
            ?>
                <div class="cf-lang-switch" role="navigation" aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Language')) ?>">
                    <?php foreach ($enabledLangs as $code): ?>
                        <a class="cf-lang-switch__item<?= $code === $currentLang ? ' is-active' : '' ?>"
                           href="<?= Html::encode(Url::toFillLanguage($formModel, $code)) ?>">
                            <?= Html::encode($langLabels[$code] ?? $code) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (trim((string)$formModel->description) !== ''): ?>
                <div class="cf-fill-hero__desc"><?= nl2br(Html::encode($formModel->description)) ?></div>
            <?php endif; ?>
        </header>
        <?php if ($fillContext && $fillContext->wave): ?>
            <div class="alert alert-info cf-wave-banner">
                <?= Html::encode($fillContext->wave->getDisplayTitle()) ?>
            </div>
        <?php endif; ?>
        <?php if ($fillContext && $fillContext->round): ?>
            <div class="alert alert-info cf-round-banner">
                <?= Html::encode($fillContext->round->getDisplayTitle()) ?>
            </div>
        <?php endif; ?>
        <?php if ($fillContext && $fillContext->roundSummaryHtml): ?>
            <div class="cf-round-summary richtext-output">
                <?= (new HtmlSanitizer())->sanitize(RichHtml::toHtml($fillContext->roundSummaryHtml)) ?>
            </div>
        <?php endif; ?>
        <?php if ($fillContext && $fillContext->previousRoundAnswer): ?>
            <div class="cf-prev-answer">
                <strong><?= Yii::t('ThiscoveryFormsModule.base', 'Your previous answer') ?></strong>
                <p class="text-muted mb-0"><?= Yii::t('ThiscoveryFormsModule.base', 'You can revise it in this round.') ?></p>
            </div>
        <?php endif; ?>
        <?php if ($formModel->isClosed()): ?>
            <div class="alert alert-info"><?= Yii::t('ThiscoveryFormsModule.base', 'This form is closed and no longer accepts submissions.') ?></div>
        <?php elseif ($formModel->isDraft()): ?>
            <div class="alert alert-warning"><?= Yii::t('ThiscoveryFormsModule.base', 'This form is still a draft.') ?></div>
        <?php endif; ?>

        <?php if ($answerableCount > 0 && $canSubmit && !$showResumeGate): ?>
            <div class="cf-fill-progress-wrap">
                <div class="cf-fill-progress" aria-hidden="true">
                    <div class="cf-fill-progress__bar" data-cf-progress-bar style="width:0%"></div>
                </div>
                <div class="cf-fill-progress__meta">
                    <span class="cf-fill-progress__label">
                        <span data-cf-progress-text>0 / <?= (int)$answerableCount ?></span>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'fields completed') ?>
                    </span>
                    <?php if ($multiPage): ?>
                        <span class="cf-page-indicator" data-cf-page-indicator></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($savedDraft && $savedDraft->resume_code): ?>
            <div class="cf-resume-saved" data-cf-resume-saved>
                <div class="cf-resume-saved__title">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Your progress is saved') ?>
                </div>
                <p class="cf-resume-saved__help">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Use this code to continue later. Anyone with the code can open your saved response.') ?>
                </p>
                <div class="cf-resume-code-row">
                    <code class="cf-resume-code" data-cf-resume-code><?= Html::encode($savedDraft->resume_code) ?></code>
                    <button type="button" class="btn btn-light btn-sm" data-cf-copy-code>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Copy code') ?>
                    </button>
                </div>
                <?= Html::beginForm(Url::toEmailResume($formModel), 'post', ['class' => 'cf-resume-email-form']) ?>
                    <?= Html::hiddenInput('resume_code', $savedDraft->resume_code) ?>
                    <label class="cf-label" for="cf-resume-email">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Email me this code') ?>
                    </label>
                    <div class="cf-resume-email-row">
                        <?= Html::input('email', 'resume_email', $savedDraft->resume_email, [
                            'id' => 'cf-resume-email',
                            'class' => 'form-control',
                            'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'you@example.com'),
                            'required' => true,
                        ]) ?>
                        <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Send email'))->submit()->sm() ?>
                    </div>
                <?= Html::endForm() ?>
            </div>
        <?php endif; ?>

        <?php if ($isDraft && $resumeCode): ?>
            <div class="alert alert-info cf-resume-banner">
                <?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Continuing saved response {code}', [
                    'code' => $resumeCode,
                ])) ?>
            </div>
        <?php endif; ?>

        <?php if ($showResumeGate): ?>
            <?= $this->render('_resume_gate', [
                'formModel' => $formModel,
                'ownDraft' => $ownDraft,
                'panelToken' => $panelToken,
            ]) ?>
        <?php elseif ($canSubmit): ?>
            <?= Html::beginForm('', 'post', [
                'class' => 'cf-fill-form',
                'data-cf-fill-form' => true,
                'data-cf-save-url' => Url::toSaveProgress($formModel),
            ]) ?>
            <?php if ($resumeCode): ?>
                <?= Html::hiddenInput('resume_code', $resumeCode) ?>
            <?php endif; ?>
            <?php if ($startNew): ?>
                <?= Html::hiddenInput('start', 'new') ?>
            <?php endif; ?>
            <?php if ($panelToken !== ''): ?>
                <?= Html::hiddenInput('panel_token', $panelToken) ?>
            <?php endif; ?>
            <?= Html::hiddenInput('current_page', '0', ['data-cf-current-page' => true]) ?>

            <?php foreach ($pages as $page): ?>
                <div class="cf-form-page<?= $page['index'] === 0 ? ' is-active' : '' ?>"
                     data-cf-page="<?= (int)$page['index'] ?>"
                     data-cf-page-key="<?= Html::encode((string)$page['pageKey']) ?>"
                     data-cf-branches="<?= Html::encode(Json::encode($page['break'] ? $page['break']->getPageBreakConfig()['branches'] : [])) ?>">
                    <?php if (!empty($page['title'])): ?>
                        <?php $pageTitle = $pipe->substitutePlain((string)$page['title'], $user, $formModel, $submit->values, $formModel->fields); ?>
                        <h2 class="cf-form-page__title" data-cf-pipe="<?= Html::encode((string)$page['title']) ?>"><?= $pageTitle ?></h2>
                    <?php endif; ?>

                    <?php foreach ($page['items'] as $field): ?>
                        <?php
                        $value = $submit->values[$field->id] ?? '';
                        $attrs = [
                            'class' => 'cf-question',
                            'data-cf-conditional' => true,
                            'data-cf-field-id' => $field->id,
                            'data-cf-field-type' => $field->type,
                        ];
                        if ($field->collectsAnswer()) {
                            $attrs['data-cf-answerable'] = '1';
                        }
                        $logic = $field->getLogic();
                        if (!empty($logic['rules'])) {
                            $attrs['data-cf-logic'] = Json::encode($logic);
                        }
                        $frozen = $fillContext && in_array((int)$field->id, $fillContext->frozenFieldIds, true);
                        if ($frozen) {
                            $attrs['data-cf-frozen'] = '1';
                            $prev = $fillContext->previousRoundAnswer;
                            if ($prev) {
                                $value = $prev->getValuesMap()[$field->id] ?? $value;
                            }
                        }
                        if ($field->hasCondition() && $field->condition_field_id) {
                            $attrs['data-cf-depends'] = $field->condition_field_id;
                            $attrs['data-cf-operator'] = $field->condition_operator;
                            $attrs['data-cf-value'] = $field->condition_value;
                        }
                        if ($field->collectsAnswer()) {
                            $questionNum++;
                        }
                        ?>
                        <div <?= Html::renderTagAttributes($attrs) ?>>
                            <?= $this->render('_field_fill', [
                                'field' => $field,
                                'value' => $value,
                                'displayIndex' => $questionNum,
                                'formModel' => $formModel,
                                'allValues' => $submit->values,
                                'allFields' => $formModel->fields,
                                'justifications' => $submit->justifications,
                                'frozen' => !empty($frozen),
                            ]) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="cf-fill-nav">
                <?php if ($multiPage): ?>
                    <button type="button" class="btn btn-light" data-cf-page-back style="display:none">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Back') ?>
                    </button>
                    <button type="button" class="btn btn-primary" data-cf-page-next>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Next') ?>
                    </button>
                <?php endif; ?>
                <?php if ($canSaveProgress): ?>
                    <button type="button" class="btn btn-light" data-cf-save-progress>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Save & continue later') ?>
                    </button>
                <?php endif; ?>
                <div class="cf-fill-submit" <?= $multiPage ? 'style="display:none"' : '' ?> data-cf-submit-wrap>
                    <?= Button::save($existing && !$isDraft
                        ? Yii::t('ThiscoveryFormsModule.base', 'Update submission')
                        : ($formModel->isProject()
                            ? Yii::t('ThiscoveryFormsModule.base', 'Submit for review')
                            : Yii::t('ThiscoveryFormsModule.base', 'Submit')))
                        ->submit()
                        ->cssClass('btn-lg') ?>
                </div>
            </div>

            <?= Html::endForm() ?>

            <?php if ($canSaveProgress): ?>
                <div class="cf-save-panel" data-cf-save-panel hidden>
                    <div class="cf-save-panel__inner">
                        <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Save & continue later') ?></h3>
                        <p><?= Yii::t('ThiscoveryFormsModule.base', 'We will save your answers and give you a resume code. Optionally email the code to yourself.') ?></p>
                        <label class="cf-label" for="cf-save-email">
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Email (optional)') ?>
                        </label>
                        <input type="email" id="cf-save-email" class="form-control" data-cf-save-email
                               placeholder="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'you@example.com')) ?>">
                        <label class="cf-check">
                            <input type="checkbox" data-cf-save-email-toggle>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Email me the resume code') ?>
                        </label>
                        <div class="cf-save-panel__actions">
                            <button type="button" class="btn btn-light" data-cf-save-cancel>
                                <?= Yii::t('ThiscoveryFormsModule.base', 'Cancel') ?>
                            </button>
                            <button type="button" class="btn btn-primary" data-cf-save-confirm>
                                <?= Yii::t('ThiscoveryFormsModule.base', 'Save progress') ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php elseif ($existing && !$isDraft): ?>
            <div class="cf-fill-done">
                <i class="fa fa-check-circle"></i>
                <h3><?= Html::encode($alreadySubmittedMessage) ?></h3>
                <?php if ($formModel->canEditOwnAnswer($existing)): ?>
                    <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Edit your submission'))
                        ->link(Url::toEditAnswer($formModel, $existing)) ?>
                <?php endif; ?>
            </div>
        <?php elseif ($alreadyAnsweredAnon || $identifiedBlocked): ?>
            <div class="cf-fill-done">
                <i class="fa fa-check-circle"></i>
                <h3><?= Html::encode($alreadySubmittedMessage) ?></h3>
            </div>
        <?php elseif ($formModel->isOpen()): ?>
            <div class="alert alert-warning"><?= Html::encode($fillContext?->blockReason ?? Yii::t('ThiscoveryFormsModule.base', 'You are not allowed to submit this form.')) ?></div>
        <?php endif; ?>
    </div>
</div>
