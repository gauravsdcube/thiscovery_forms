<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\helpers\Html;
use humhub\modules\file\libs\FileHelper;
use humhub\modules\file\libs\ImageHelper;
use humhub\modules\file\models\File;
use humhub\modules\file\models\FileUpload;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\thiscoveryForms\services\FillContext;
use humhub\modules\thiscoveryForms\services\FillContextService;
use humhub\modules\thiscoveryForms\services\ResumeService;
use humhub\modules\thiscoveryForms\services\TranslationService;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Shared save-progress / resume-by-code actions for global and space form controllers.
 */
trait FillResumeTrait
{
    protected function resumeService(): ResumeService
    {
        return new ResumeService();
    }

    protected function fillContext(CustomForm $form): FillContext
    {
        return (new FillContextService())->resolve($form);
    }

    protected function applyFillContext(CustomForm $form, SubmitForm $submit, FillContext $ctx): void
    {
        $submit->waveId = $ctx->wave->id ?? null;
        $submit->roundId = $ctx->round->id ?? null;
        $submit->panelMemberId = $ctx->member->id ?? null;
        $submit->weight = $ctx->member ? (float)$ctx->member->weight : 1;
    }

    /**
     * Fill, preview, and thank-you without HumHub top bars or space chrome.
     *
     * PJAX only replaces #layout-content, so a headerless layout never applies (and
     * leaving it never restores the site chrome) unless this is a full document load.
     */
    protected function applyFillLayout(CustomForm $form): void
    {
        if (!$form->hidesHumhubHeader()) {
            return;
        }

        if (Yii::$app->request->isPjax) {
            $url = Yii::$app->request->absoluteUrl;
            Yii::$app->response->content = \humhub\helpers\Html::script(
                'window.location.replace(' . \yii\helpers\Json::htmlEncode($url) . ');'
            );
            Yii::$app->end();
        }

        $this->layout = '@thiscovery-forms/views/layouts/fill-standalone';
        $this->subLayout = null;
        if ($form->title) {
            $this->view->setPageTitle($form->title);
        }
    }

    protected function isPreviewMode(CustomForm $form): bool
    {
        $token = trim((string)Yii::$app->request->get('preview', Yii::$app->request->post('preview', '')));
        return $form->isValidTestToken($token);
    }

    protected function previewAnswerSessionKey(CustomForm $form): string
    {
        return 'cf_preview_answer_' . (int)$form->id;
    }

    protected function partialAnswerSessionKey(CustomForm $form): string
    {
        return 'cf_partial_answer_' . (int)$form->id;
    }

    protected function allowsProgressSave(CustomForm $form): bool
    {
        return $form->allowsResume() || $form->keepsPartials();
    }

    protected function assertFillAccess(CustomForm $form): void
    {
        if ($this->isPreviewMode($form)) {
            return;
        }

        $token = trim((string)Yii::$app->request->get('token', Yii::$app->request->post('panel_token', '')));
        $ctx = $this->fillContext($form);
        $tokenOk = $token !== '' && $ctx->tokenAccess && $ctx->member;

        if (Yii::$app->user->isGuest) {
            if ($tokenOk) {
                if ($form->isDraft() && !$form->canManage()) {
                    throw new ForbiddenHttpException(Yii::t('ThiscoveryFormsModule.base', 'This form is still a draft.'));
                }
                return;
            }
            if (!$form->allowsAnonymous()) {
                Yii::$app->user->loginRequired();
                Yii::$app->end();
            }
            \humhub\modules\thiscoveryForms\helpers\GuestAccess::assertCanView($form);
            if (!$form->isGlobal() && !$form->content->canView()) {
                throw new ForbiddenHttpException(Yii::t(
                    'ThiscoveryFormsModule.base',
                    'This form is not publicly accessible in this space.'
                ));
            }
        } elseif (!$form->content->canView() && !$tokenOk) {
            throw new ForbiddenHttpException();
        }

        if ($form->isDraft() && !$form->canManage()) {
            throw new ForbiddenHttpException(Yii::t('ThiscoveryFormsModule.base', 'This form is still a draft.'));
        }

        $integrity = new \humhub\modules\thiscoveryForms\services\integrity\IntegrityService();
        $accessError = $integrity->checkAccess($form, $ctx);
        if ($accessError && !$form->canManage()) {
            throw new ForbiddenHttpException($accessError);
        }
    }

    protected function assertResumeEnabled(CustomForm $form): void
    {
        if (!$this->allowsProgressSave($form)) {
            throw new ForbiddenHttpException(Yii::t(
                'ThiscoveryFormsModule.base',
                'Save and resume is not enabled for this form.'
            ));
        }
    }

    protected function isStartNewRequest(): bool
    {
        return (string)Yii::$app->request->get('start', Yii::$app->request->post('start', '')) === 'new';
    }

    protected function isContinueOwnRequest(): bool
    {
        return (string)Yii::$app->request->get('continue', '') === '1';
    }

    protected function hasResumeIntent(CustomForm $form): bool
    {
        if (!$form->allowsResume()) {
            return false;
        }
        if ($this->isContinueOwnRequest()) {
            return true;
        }
        $code = (string)(Yii::$app->request->post('resume_code')
            ?: Yii::$app->request->get('resume', ''));
        return $code !== '';
    }

    protected function resolveOwnInProgress(CustomForm $form): ?FormAnswer
    {
        $ctx = $this->fillContext($form);
        if ($form->usesWaves() || $form->isConsensus()) {
            $column = $form->usesWaves() ? 'wave_id' : 'round_id';
            $scopeId = $form->usesWaves() ? ($ctx->wave->id ?? null) : ($ctx->round->id ?? null);
            $scoped = (new FillContextService())->findScopedAnswer($form, $ctx, $scopeId, $column);
            return ($scoped && $scoped->isInProgress()) ? $scoped : null;
        }

        return $form->getUserInProgressAnswer();
    }

    protected function resolveDraftFromRequest(CustomForm $form): ?FormAnswer
    {
        $code = (string)(Yii::$app->request->post('resume_code')
            ?: Yii::$app->request->get('resume', ''));
        if ($code === '') {
            return null;
        }

        return $this->resumeService()->findDraftByCode($form, $code);
    }

    /**
     * Load draft for fill: resume code wins, else scoped wave/round answer, else logged-in user's answer.
     */
    protected function resolveFillExisting(CustomForm $form, SubmitForm $submit): ?FormAnswer
    {
        if ($this->isPreviewMode($form)) {
            if (!$form->allowsResume() || !$this->hasResumeIntent($form)) {
                $this->forgetProgressDraft($form);
                return null;
            }
            $draft = $this->resolveDraftFromRequest($form);
            if ($draft && $draft->isTest()) {
                $submit->loadFromAnswer($draft);
                return $draft;
            }
            if ($this->isContinueOwnRequest()) {
                $sid = (int)Yii::$app->session->get($this->previewAnswerSessionKey($form), 0);
                if ($sid > 0) {
                    $ans = FormAnswer::findOne(['id' => $sid, 'form_id' => $form->id, 'is_test' => 1]);
                    if ($ans && $ans->isInProgress()) {
                        $submit->loadFromAnswer($ans);
                        return $ans;
                    }
                }
            }
            return null;
        }

        $draft = $this->resolveDraftFromRequest($form);
        if ($draft && $form->allowsResume() && $this->hasResumeIntent($form)) {
            $submit->loadFromAnswer($draft);
            return $draft;
        }

        if ($form->allowsResume() && $this->isContinueOwnRequest()) {
            $own = $this->resolveOwnInProgress($form);
            if ($own) {
                $submit->loadFromAnswer($own);
                return $own;
            }
        }

        $ctx = $this->fillContext($form);
        $startNew = $this->isStartNewRequest();
        $skipInProgress = !$this->hasResumeIntent($form);

        if ($form->usesWaves() || $form->isConsensus()) {
            $column = $form->usesWaves() ? 'wave_id' : 'round_id';
            $scopeId = $form->usesWaves() ? ($ctx->wave->id ?? null) : ($ctx->round->id ?? null);
            $scoped = (new FillContextService())->findScopedAnswer($form, $ctx, $scopeId, $column);
            if ($scoped) {
                if ($skipInProgress && $scoped->isInProgress() && !$startNew) {
                    return null;
                }
                if ($startNew && $scoped->isInProgress()) {
                    return null;
                }
                $submit->loadFromAnswer($scoped);
                return $scoped;
            }
            if ($form->isConsensus() && $ctx->previousRoundAnswer && !$startNew) {
                $submit->loadFromAnswer($ctx->previousRoundAnswer);
            }
            return null;
        }

        if ($startNew && $form->allowsResume()) {
            return null;
        }

        if (!$form->allow_multiple && !$form->allowsAnonymous()) {
            $complete = $form->getUserAnswer();
            if ($complete) {
                $submit->loadFromAnswer($complete);
                return $complete;
            }
            if ($skipInProgress) {
                return null;
            }
            $inProgress = $form->getUserInProgressAnswer();
            if ($inProgress) {
                $submit->loadFromAnswer($inProgress);
                return $inProgress;
            }
        }

        return null;
    }

    protected function canContinueDraft(CustomForm $form, ?FormAnswer $existing): bool
    {
        if ($this->isPreviewMode($form)) {
            return !$existing || $existing->isTest();
        }

        $ctx = $this->fillContext($form);
        if ($form->usesWaves() || $form->isConsensus()) {
            if ($ctx->blockReason && !$form->canManage()) {
                return false;
            }
            if (!$existing) {
                return $form->isOpen() && ($form->canAnswer() || $ctx->tokenAccess || $ctx->member || ($form->usesWaves() && $form->allowsAnonymous()));
            }
        }

        if (!$existing) {
            return $form->canAnswer();
        }
        if ($existing->isInProgress()) {
            if (!$form->isOpen() && !$form->canManage()) {
                return false;
            }
            $code = (string)(Yii::$app->request->post('resume_code')
                ?: Yii::$app->request->get('resume', ''));
            if ($code !== '' && $this->resumeService()->normalizeCode($code) === $existing->resume_code) {
                return true;
            }
            $user = Yii::$app->user->getIdentity();
            if ($user && (int)$existing->created_by === (int)$user->id) {
                return true;
            }
            $ctx = $this->fillContext($form);
            if ($ctx->member && (int)$existing->panel_member_id === (int)$ctx->member->id) {
                return true;
            }
            return false;
        }

        return $form->canEditOwnAnswer($existing) || $form->canManage();
    }

    /**
     * Draft used by autosave / save-progress: resume code, session, or this user's open draft.
     */
    protected function resolveProgressDraft(CustomForm $form): ?FormAnswer
    {
        if ($this->isPreviewMode($form)) {
            $draft = $this->resolveDraftFromRequest($form);
            if ($draft && $draft->isTest() && $draft->isInProgress()) {
                return $draft;
            }
            $sid = (int)Yii::$app->session->get($this->previewAnswerSessionKey($form), 0);
            if ($sid > 0) {
                $ans = FormAnswer::findOne(['id' => $sid, 'form_id' => $form->id, 'is_test' => 1]);
                return ($ans && $ans->isInProgress()) ? $ans : null;
            }
            return null;
        }

        $draft = $this->resolveDraftFromRequest($form);
        if ($draft && $draft->isInProgress()) {
            return $draft;
        }

        $sid = (int)Yii::$app->session->get($this->partialAnswerSessionKey($form), 0);
        if ($sid > 0) {
            $ans = FormAnswer::findOne(['id' => $sid, 'form_id' => $form->id, 'is_test' => 0]);
            if ($ans && $ans->isInProgress()) {
                return $ans;
            }
        }

        return $this->resolveOwnInProgress($form);
    }

    protected function rememberProgressDraft(CustomForm $form, FormAnswer $answer): void
    {
        $key = $this->isPreviewMode($form)
            ? $this->previewAnswerSessionKey($form)
            : $this->partialAnswerSessionKey($form);
        Yii::$app->session->set($key, (int)$answer->id);
    }

    protected function forgetProgressDraft(CustomForm $form): void
    {
        Yii::$app->session->remove($this->partialAnswerSessionKey($form));
        Yii::$app->session->remove($this->previewAnswerSessionKey($form));
    }

    /**
     * Keep a single in-progress draft per person per form (and wave/round).
     * When $keepCurrent is false, remove all of this person's in-progress drafts (after submit).
     */
    protected function pruneUserInProgressDrafts(CustomForm $form, FormAnswer $answer, bool $keepCurrent = false): void
    {
        $userId = $answer->created_by ?: (!Yii::$app->user->isGuest ? Yii::$app->user->id : null);
        if (!$userId) {
            return;
        }
        $query = FormAnswer::find()->where([
            'form_id' => $form->id,
            'created_by' => $userId,
            'status' => FormAnswer::STATUS_IN_PROGRESS,
            'is_test' => $answer->isTest() ? 1 : 0,
        ]);
        if ($keepCurrent) {
            $query->andWhere(['<>', 'id', $answer->id]);
        }
        if ($answer->wave_id) {
            $query->andWhere(['wave_id' => $answer->wave_id]);
        }
        if ($answer->round_id) {
            $query->andWhere(['round_id' => $answer->round_id]);
        }
        foreach ($query->all() as $dup) {
            $dup->delete();
        }
    }

    protected function handleSaveProgress(CustomForm $form, SubmitForm $submit, ?FormAnswer $existing)
    {
        $this->assertResumeEnabled($form);

        if (!$existing || !$existing->isInProgress()) {
            $existing = $this->resolveProgressDraft($form) ?: $existing;
        }

        if (!$this->canContinueDraft($form, $existing)) {
            throw new ForbiddenHttpException();
        }

        $submit->loadValuesFromRequest(Yii::$app->request->post());
        $ctx = $this->fillContext($form);
        $this->applyFillContext($form, $submit, $ctx);
        $anonymous = $form->allowsAnonymous() || (Yii::$app->user->isGuest && $ctx->tokenAccess);
        $email = trim((string)Yii::$app->request->post('resume_email', ''));
        $page = Yii::$app->request->post('current_page');
        $currentPage = ($page !== null && $page !== '') ? (int)$page : null;

        if ($existing && !$existing->isInProgress()) {
            Yii::$app->session->setFlash('error', Yii::t(
                'ThiscoveryFormsModule.base',
                'This response has already been submitted.'
            ));
            return $this->redirect(Url::toView($form));
        }

        $answer = $this->resumeService()->saveDraft(
            $form,
            $submit,
            $existing && $existing->isInProgress() ? $existing : null,
            $anonymous,
            $email !== '' ? $email : null,
            $currentPage,
            $this->isPreviewMode($form)
        );

        if ($answer) {
            $answer->setVars($this->postedActionVars($answer));
            $answer->save(false, ['vars_json', 'updated_at']);
            $this->rememberProgressDraft($form, $answer);
            $this->pruneUserInProgressDrafts($form, $answer, true);
            if (!$this->isPreviewMode($form)) {
                (new \humhub\modules\thiscoveryForms\services\integrity\IntegrityService())->onProgress($form, $answer, Yii::$app->request->post());
            }
        }

        if (!$answer) {
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return [
                    'success' => false,
                    'errors' => $submit->getErrorSummary(true),
                ];
            }
            Yii::$app->session->setFlash('error', implode(' ', $submit->getErrorSummary(true))
                ?: Yii::t('ThiscoveryFormsModule.base', 'Could not save your progress. Please try again.'));
            return $this->renderFillView($form, $submit, $existing);
        }

        if ($this->isPreviewMode($form)) {
            Yii::$app->session->set($this->previewAnswerSessionKey($form), (int)$answer->id);
        }

        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return [
                'success' => true,
                'resume_code' => (string)$answer->resume_code,
            ];
        }

        $sendEmail = (bool)Yii::$app->request->post('email_code', false);
        $emailSent = false;
        if ($sendEmail && $email !== '') {
            $emailSent = $this->resumeService()->sendResumeEmail($form, $answer, $email);
            if (!$emailSent) {
                Yii::$app->session->setFlash('error', Yii::t(
                    'ThiscoveryFormsModule.base',
                    'Progress was saved, but the email could not be sent. Please copy your code below.'
                ));
            } else {
                Yii::$app->session->setFlash('success', Yii::t(
                    'ThiscoveryFormsModule.base',
                    'Progress saved and resume code emailed to {email}.',
                    ['email' => $email]
                ));
            }
        } else {
            Yii::$app->session->setFlash('success', Yii::t(
                'ThiscoveryFormsModule.base',
                'Progress saved. Copy your resume code to continue later.'
            ));
        }

        $submit->loadFromAnswer($answer);

        return $this->renderFillView($form, $submit, $answer, [
            'savedDraft' => $answer,
            'emailSent' => $emailSent,
        ]);
    }

    /**
     * Email an existing draft's resume code again.
     *
     * @return string|Response
     */
    protected function handleEmailResumeCode(CustomForm $form)
    {
        $this->assertResumeEnabled($form);

        if (!Yii::$app->request->isPost) {
            return $this->redirect(Url::toView($form));
        }

        $code = (string)Yii::$app->request->post('resume_code', '');
        $email = trim((string)Yii::$app->request->post('resume_email', ''));
        $answer = $this->resumeService()->findDraftByCode($form, $code);

        if (!$answer) {
            Yii::$app->session->setFlash('error', Yii::t(
                'ThiscoveryFormsModule.base',
                'No saved response found for that code.'
            ));
            return $this->redirect(Url::toView($form));
        }

        if (!$this->resumeService()->sendResumeEmail($form, $answer, $email)) {
            Yii::$app->session->setFlash('error', Yii::t(
                'ThiscoveryFormsModule.base',
                'Could not send the email. Check the address and try again.'
            ));
        } else {
            Yii::$app->session->setFlash('success', Yii::t(
                'ThiscoveryFormsModule.base',
                'Resume code emailed to {email}.',
                ['email' => $email]
            ));
        }

        return $this->redirect(Url::toResume($form, $answer->resume_code));
    }

    /**
     * @return string|Response
     */
    protected function handleResumeLookup(CustomForm $form)
    {
        $this->assertResumeEnabled($form);

        $code = (string)(Yii::$app->request->post('resume_code')
            ?: Yii::$app->request->get('code', ''));
        $answer = $this->resumeService()->findDraftByCode($form, $code);

        if (!$answer) {
            Yii::$app->session->setFlash('error', Yii::t(
                'ThiscoveryFormsModule.base',
                'No saved response found for that code.'
            ));
            return $this->redirect(Url::toView($form));
        }

        return $this->redirect(Url::toResume($form, $answer->resume_code));
    }

    /**
     * Render the fill page with optional extra view vars.
     */
    abstract protected function renderFillView(
        CustomForm $form,
        SubmitForm $submit,
        ?FormAnswer $existing,
        array $extra = []
    );

    protected function fillViewExtras(CustomForm $form, FillContext $ctx): array
    {
        (new TranslationService())->overlay($form, $ctx->language);
        $token = trim((string)Yii::$app->request->get('token', Yii::$app->request->post('panel_token', '')));
        $ownDraft = $form->allowsResume() ? $this->resolveOwnInProgress($form) : null;

        return [
            'fillContext' => $ctx,
            'panelToken' => $ctx->tokenAccess ? ($ctx->member->token ?? $token) : $token,
            'ownDraft' => $ownDraft,
            'startNew' => $this->isStartNewRequest(),
            'isPreview' => $this->isPreviewMode($form),
            'accessToken' => trim((string)Yii::$app->request->get('access', Yii::$app->request->post('access_token', ''))),
            'showCaptcha' => (new \humhub\modules\thiscoveryForms\services\integrity\IntegrityService())->shouldShowCaptchaWidget($form),
            'integrityEnabled' => (new \humhub\modules\thiscoveryForms\services\integrity\IntegrityService())->isEnabled($form),
            'integritySettings' => \humhub\modules\thiscoveryForms\services\integrity\IntegritySettings::forForm($form),
        ];
    }

    protected function afterCompleteSave(CustomForm $form, FillContext $ctx, $answer, bool $anonymous): void
    {
        $isTest = $answer instanceof FormAnswer && $answer->isTest();
        if ($anonymous && !$isTest) {
            $form->markGuestAnswered($ctx->wave->id ?? null, $ctx->round->id ?? null);
        }
        if ($ctx->member && !$isTest) {
            $ctx->member->markConsent();
        }
        if ($form->isProject() && $answer instanceof FormAnswer && !$anonymous && !$isTest) {
            (new \humhub\modules\thiscoveryForms\services\ApprovalWorkflowService())->submitForReview($answer);
        }
        if ($answer instanceof FormAnswer) {
            if (!$isTest) {
                $this->forgetProgressDraft($form);
                $this->pruneUserInProgressDrafts($form, $answer);
            }
            // Persist participant language for research audit (never overwrite free-text).
            try {
                $vars = $answer->getVars();
                $vars['response_language'] = (string)$ctx->language;
                $answer->setVars($vars);
                $answer->save(false, ['vars_json', 'updated_at']);
            } catch (\Throwable $e) {
            }
            (new \humhub\modules\thiscoveryForms\services\PanelService())->handleCompletion($form, $answer);
            if (!$isTest) {
                (new \humhub\modules\thiscoveryForms\services\integrity\IntegrityService())->onComplete(
                    $form,
                    $answer,
                    Yii::$app->request->post(),
                    $ctx
                );
                $this->runSubmitActions($form, $ctx, $answer);
            }
        }
    }

    protected function postedActionVars(?FormAnswer $answer = null): array
    {
        $vars = $answer ? $answer->getVars() : [];
        $raw = Yii::$app->request->post('action_vars', '');
        if (is_array($raw)) {
            $extra = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $extra = is_array($decoded) ? $decoded : [];
        } else {
            $extra = [];
        }
        foreach ($extra as $key => $value) {
            $name = \humhub\modules\thiscoveryForms\services\FormActionService::sanitizeName((string)$key);
            if ($name !== '') {
                $vars[$name] = is_scalar($value) ? (string)$value : '';
            }
        }
        return $vars;
    }

    protected function runSubmitActions(CustomForm $form, FillContext $ctx, FormAnswer $answer): void
    {
        $actions = $form->submit_actions ?: $form->getSetting('submit_actions', []);
        if (!$actions) {
            return;
        }
        (new \humhub\modules\thiscoveryForms\services\FormActionService())->run(
            $form,
            is_array($actions) ? $actions : [],
            $answer->getValuesMap(),
            $this->postedActionVars($answer),
            $answer,
            $ctx->member,
            null,
            $this->isPreviewMode($form)
        );
    }

    public function actionRunActions($id)
    {
        $form = $this->findForm($id);
        $this->assertFillAccess($form);
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return ['ok' => false, 'error' => Yii::t('ThiscoveryFormsModule.base', 'Invalid request.')];
        }
        $trigger = (string)Yii::$app->request->post('trigger', '');
        $fieldId = (int)Yii::$app->request->post('field_id', 0);
        $submit = new SubmitForm(['form' => $form]);
        $submit->scenario = SubmitForm::SCENARIO_DRAFT;
        $submit->loadValuesFromRequest(Yii::$app->request->post());
        $ctx = $this->fillContext($form);
        $this->applyFillContext($form, $submit, $ctx);
        $answer = $this->resolveFillExisting($form, $submit);
        $source = null;
        if ($fieldId) {
            foreach ($form->fields as $candidate) {
                if ((int)$candidate->id === $fieldId) {
                    $source = $candidate;
                    break;
                }
            }
        }
        $actions = [];
        if (in_array($trigger, ['field', 'page'], true) && $source) {
            $actions = $source->getActions();
        } elseif ($trigger === 'submit') {
            $actions = $form->submit_actions ?: $form->getSetting('submit_actions', []);
        }
        $result = (new \humhub\modules\thiscoveryForms\services\FormActionService())->run(
            $form,
            is_array($actions) ? $actions : [],
            $submit->values,
            $this->postedActionVars($answer instanceof FormAnswer ? $answer : null),
            $answer instanceof FormAnswer ? $answer : null,
            $ctx->member,
            $source,
            $this->isPreviewMode($form)
        );
        return ['ok' => true] + $result;
    }

    /**
     * Guest-safe file upload for fill (HumHub /file/file/upload requires login).
     */
    public function actionUpload($id)
    {
        $form = $this->findForm($id);
        $this->assertFillAccess($form);
        $this->forcePostRequest();
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!$this->formAcceptsUploads($form)) {
            throw new ForbiddenHttpException();
        }

        $files = [];
        foreach (UploadedFile::getInstancesByName('files') as $uploaded) {
            $file = new FileUpload();
            $file->setUploadedFile($uploaded);
            $file->show_in_stream = false;
            if (Yii::$app->user->isGuest) {
                $file->created_by = null;
                $file->updated_by = null;
            }
            if ($file->save()) {
                ImageHelper::downscaleImage($file);
                $files[] = array_merge(['error' => false], FileHelper::getFileInfos($file));
            } else {
                $errorMessage = $file->getErrors('uploadedFile');
                if (!$errorMessage) {
                    $errorMessage = Yii::t('ThiscoveryFormsModule.base', 'Could not upload the file.');
                }
                $files[] = [
                    'error' => true,
                    'errors' => $errorMessage,
                    'name' => Html::encode((string)$file->file_name),
                    'size' => Html::encode((string)$file->size),
                ];
            }
        }

        return ['files' => $files];
    }

    public function actionDeleteFile($id)
    {
        $form = $this->findForm($id);
        $this->assertFillAccess($form);
        $this->forcePostRequest();
        Yii::$app->response->format = Response::FORMAT_JSON;

        $guid = trim((string)Yii::$app->request->post('guid', ''));
        if ($guid === '') {
            return ['success' => true];
        }

        $file = File::findOne(['guid' => $guid]);
        if (!$file) {
            return ['success' => true];
        }

        if ($file->isAssigned()) {
            $object = $file->getPolymorphicRelation();
            $allowed = $object instanceof FormAnswer && (int)$object->form_id === (int)$form->id;
            if (!$allowed) {
                throw new ForbiddenHttpException();
            }
        }

        $file->delete();
        return ['success' => true];
    }

    protected function formAcceptsUploads(CustomForm $form): bool
    {
        foreach ($form->fields as $field) {
            if (in_array($field->type, [FormField::TYPE_FILE, FormField::TYPE_IMAGE_AREA], true)) {
                return true;
            }
        }
        return false;
    }
}
