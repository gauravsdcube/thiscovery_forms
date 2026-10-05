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
use humhub\modules\thiscoveryForms\services\UploadGrant;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Shared save-progress / resume-by-code actions for global and space form controllers.
 */
trait FillResumeTrait
{
    /**
     * Shown after a page-exit quota ended or redirected the response (V3-47). It only renders
     * the messages the quota left in this session; there is nothing to fill.
     */
    public function actionQuotaClosed($id = null)
    {
        $form = $this->findFillForm($id);
        $this->applyFillLayout($form);
        $this->fillContext($form);
        return $this->render('thankyou', [
            'formModel' => $form,
            'contentContainer' => $this->contentContainer ?? null,
            'answer' => null,
            'isPreview' => $this->isPreviewMode($form),
        ]);
    }

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
        $submit->frozenFieldIds = $ctx->frozenFieldIds;
        $submit->previousValues = $ctx->previousRoundAnswer ? $ctx->previousRoundAnswer->getValuesMap() : [];
        $submit->applyServerOwnedValues();
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

    /**
     * The form for a respondent link. A fill token is the public address.
     * A numeric id still works for someone who is signed in, and for an old preview,
     * panel, or access link. A guest with only the id is refused.
     */
    protected function findFillForm($id = null): CustomForm
    {
        $token = trim((string)Yii::$app->request->get('t', Yii::$app->request->post('fill_token', '')));
        if ($token !== '') {
            $form = CustomForm::findByFillToken($token);
            if (!$form || !$this->fillFormInScope($form)) {
                throw new NotFoundHttpException();
            }
            CustomForm::assertNotTrashed($form);
            return $form;
        }

        if ($id === null || $id === '' || !ctype_digit((string)$id)) {
            throw new NotFoundHttpException(Yii::t(
                'ThiscoveryFormsModule.base',
                'This form link is incomplete. Please use the full share URL from the form Share tab.'
            ));
        }
        $form = $this->findForm($id);
        if (Yii::$app->user->isGuest) {
            $preview = trim((string)Yii::$app->request->get('preview', Yii::$app->request->post('preview', '')));
            $panel = trim((string)Yii::$app->request->get('token', Yii::$app->request->post('panel_token', '')));
            $access = trim((string)Yii::$app->request->get('access', ''));
            if (!$form->isValidTestToken($preview) && $panel === '' && $access === '') {
                throw new NotFoundHttpException();
            }
        }
        return $form;
    }

    protected function fillFormInScope(CustomForm $form): bool
    {
        if (property_exists($this, 'contentContainer') && $this->contentContainer) {
            return (int)$form->content->contentcontainer_id === (int)$this->contentContainer->contentcontainer_id;
        }
        return $form->isGlobal();
    }

    protected function isPreviewMode(CustomForm $form): bool
    {
        $token = trim((string)Yii::$app->request->get('preview', Yii::$app->request->post('preview', '')));
        return $form->isValidTestToken($token);
    }

    /**
     * Hydrate published/historical edition onto the in-memory form for fill & submit.
     */
    /**
     * Hydrate published/historical edition onto the in-memory form for fill & submit.
     *
     * In-progress responses keep the edition they started. Completed responses
     * only keep that edition when explicitly editing. A normal open of the live
     * URL uses the current published edition.
     */
    protected function applyEditionForFill(CustomForm $form, ?FormAnswer $existing = null, ?bool $lockToAnswerEdition = null): void
    {
        if ($lockToAnswerEdition === null) {
            $lockToAnswerEdition = $existing && $existing->isInProgress();
        }
        $lock = $lockToAnswerEdition && $existing && $existing->edition_id;
        try {
            (new \humhub\modules\thiscoveryForms\services\FormVersionService())
                ->applyFillDefinition($form, $lock ? $existing : null, $this->isPreviewMode($form));
        } catch (\Throwable $e) {
            Yii::warning('Thiscovery Forms edition hydrate failed: ' . $e->getMessage(), 'thiscovery-forms');
            (new \humhub\modules\thiscoveryForms\services\FormVersionService())->markEditionUnavailable($form);
        }
    }

    /**
     * @return string|null rendered page when the published edition did not load
     */
    protected function refuseUnavailableEdition(CustomForm $form)
    {
        if (empty($form->editionLoadFailed)) {
            return null;
        }
        $this->applyFillLayout($form);
        return $this->render('@thiscovery-forms/views/form/_edition_unavailable', [
            'formModel' => $form,
        ]);
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
    protected function resolveFillExisting(CustomForm $form, SubmitForm $submit, bool $load = true): ?FormAnswer
    {
        if ($this->isPreviewMode($form)) {
            if (!$form->allowsResume() || !$this->hasResumeIntent($form)) {
                if ($load) {
                    $this->forgetProgressDraft($form);
                }
                return null;
            }
            $draft = $this->resolveDraftFromRequest($form);
            if ($draft && $draft->isTest()) {
                if ($load) {
                    $submit->loadFromAnswer($draft);
                }
                return $draft;
            }
            if ($this->isContinueOwnRequest()) {
                $sid = (int)Yii::$app->session->get($this->previewAnswerSessionKey($form), 0);
                if ($sid > 0) {
                    $ans = FormAnswer::findOne(['id' => $sid, 'form_id' => $form->id, 'is_test' => 1]);
                    if ($ans && $ans->isInProgress()) {
                        if ($load) {
                            $submit->loadFromAnswer($ans);
                        }
                        return $ans;
                    }
                }
            }
            return null;
        }

        $draft = $this->resolveDraftFromRequest($form);
        if ($draft && $form->allowsResume() && $this->hasResumeIntent($form)) {
            if ($load) {
                $submit->loadFromAnswer($draft);
            }
            return $draft;
        }

        if ($form->allowsResume() && $this->isContinueOwnRequest()) {
            $own = $this->resolveOwnInProgress($form);
            if ($own) {
                if ($load) {
                    $submit->loadFromAnswer($own);
                }
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
                if ($load) {
                    $submit->loadFromAnswer($scoped);
                }
                return $scoped;
            }
            if ($form->isConsensus() && $ctx->previousRoundAnswer && !$startNew) {
                if ($load) {
                    $submit->loadFromAnswer($ctx->previousRoundAnswer);
                }
            }
            return null;
        }

        if ($startNew && $form->allowsResume()) {
            return null;
        }

        if (!$form->allow_multiple && !$form->allowsAnonymous()) {
            $complete = $form->getUserAnswer();
            if ($complete) {
                if ($load) {
                    $submit->loadFromAnswer($complete);
                }
                return $complete;
            }
            if ($skipInProgress) {
                return null;
            }
            $inProgress = $form->getUserInProgressAnswer();
            if ($inProgress) {
                if ($load) {
                    $submit->loadFromAnswer($inProgress);
                }
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
            if ($code !== '' && $this->resumeService()->matches($existing, $code)) {
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

        $this->applyEditionForFill($form, $existing, (bool)$existing);
        $submit->form = $form;

        if (!$this->canContinueDraft($form, $existing)) {
            throw new ForbiddenHttpException();
        }

        $submit->editingAnswer = $existing;
        $submit->loadValuesFromRequest(Yii::$app->request->post());
        $ctx = $this->fillContext($form);
        $this->applyFillContext($form, $submit, $ctx);
        $anonymous = $form->submitAsAnonymous(false, (bool)$ctx->tokenAccess);
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
            $answer->setVars($this->postedActionVars($form, $answer));
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
                'resume_code' => \humhub\modules\thiscoveryForms\services\ResumeService::plainFor($answer),
                'quota_message' => (string)$submit->quotaMessage,
                // A page-exit quota acts, not just notes (V3-47): end/redirect closes the response
                // and the page moves to the closing page; goto moves to the quota's page.
                'quota_halt' => (string)$submit->quotaHalt,
                'quota_page' => $submit->quotaHalt === 'goto' ? (int)$submit->quotaPage : null,
                'quota_closed_url' => in_array((string)$submit->quotaHalt, ['end', 'redirect'], true) ? Url::toQuotaClosed($form) : '',
                'roster_changed' => (bool)$submit->rosterChanged,
                'roster_key' => (string)$submit->rosterKey,
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
        $this->fillContext($form);

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

        if (!$this->resumeService()->sendResumeEmail($form, $answer, $email, $code)) {
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

        return $this->redirect(Url::toResume($form, $this->resumeService()->normalizeCode($code)));
    }

    /**
     * @return string|Response
     */
    protected function handleResumeLookup(CustomForm $form)
    {
        $this->assertResumeEnabled($form);
        $this->fillContext($form);

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

        return $this->redirect(Url::toResume($form, $this->resumeService()->normalizeCode($code)));
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
        $token = trim((string)Yii::$app->request->get('token', Yii::$app->request->post('panel_token', '')));
        $ownDraft = $form->allowsResume() ? $this->resolveOwnInProgress($form) : null;

        return [
            'fillContext' => $ctx,
            // The signed link token the page came with; the stored token never reaches the page (SEC-16).
            'panelToken' => $token,
            'ownDraft' => $ownDraft,
            'startNew' => $this->isStartNewRequest(),
            'isPreview' => $this->isPreviewMode($form),
            'accessToken' => $ctx->accessToken,
            'showCaptcha' => (new \humhub\modules\thiscoveryForms\services\integrity\IntegrityService())->shouldShowCaptchaWidget($form),
            'captchaProvider' => (string)((\humhub\modules\thiscoveryForms\services\integrity\IntegritySettings::forForm($form)['captcha_provider'] ?? 'altcha')),
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
            (new \humhub\modules\thiscoveryForms\services\PanelService())->handleCompletion($form, $answer, $ctx->member);
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

    protected function postedActionVars(CustomForm $form, ?FormAnswer $answer = null): array
    {
        $raw = Yii::$app->request->post('action_vars', '');
        if (is_array($raw)) {
            $extra = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $extra = is_array($decoded) ? $decoded : [];
        } else {
            $extra = [];
        }
        return \humhub\modules\thiscoveryForms\services\FormActionService::filterPostedVars(
            $form,
            $answer ? $answer->getVars() : [],
            $extra
        );
    }

    protected function runSubmitActions(CustomForm $form, FillContext $ctx, FormAnswer $answer): void
    {
        if (!$this->isPreviewMode($form)) {
            (new \humhub\modules\thiscoveryForms\services\FormActionService())->sendDeferred($form, $answer, $this->postedActionVars($form, $answer), $ctx->member);
        }
        $actions = $form->submit_actions ?: $form->getSetting('submit_actions', []);
        if (!$actions) {
            return;
        }
        (new \humhub\modules\thiscoveryForms\services\FormActionService())->run(
            $form,
            is_array($actions) ? $actions : [],
            $answer->getValuesMap(),
            $this->postedActionVars($form, $answer),
            $answer,
            $ctx->member,
            null,
            $this->isPreviewMode($form)
        );
    }

    public function actionRunActions($id = null)
    {
        $form = $this->findFillForm($id);
        $this->assertFillAccess($form);
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return ['ok' => false, 'error' => Yii::t('ThiscoveryFormsModule.base', 'Invalid request.')];
        }
        $trigger = (string)Yii::$app->request->post('trigger', '');
        $fieldId = (int)Yii::$app->request->post('field_id', 0);
        $ip = (string)(Yii::$app->request->userIP ?? '');
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
        if (\humhub\modules\thiscoveryForms\services\FormActionService::acceptsRunTrigger($trigger) && $source) {
            $actions = $source->getActions();
        }
        // Nothing to run: answer at once, without a draft or a place in the rate limit (V3-40).
        if (!is_array($actions) || $actions === []) {
            return ['ok' => true, 'vars' => [], 'gotoPageKey' => '', 'gotoEnd' => false];
        }
        if (\humhub\modules\thiscoveryForms\services\FormActionService::tooManyRuns((int)$form->id, $ip)) {
            return ['ok' => false, 'error' => Yii::t('ThiscoveryFormsModule.base', 'Too many requests. Please wait and try again.')];
        }
        $submit = new SubmitForm(['form' => $form]);
        $submit->scenario = SubmitForm::SCENARIO_DRAFT;
        $answer = $this->resolveFillExisting($form, $submit, false);
        $submit->editingAnswer = $answer;
        $submit->loadValuesFromRequest(Yii::$app->request->post());
        $ctx = $this->fillContext($form);
        $this->applyFillContext($form, $submit, $ctx);
        if (!$answer instanceof FormAnswer && !$this->isPreviewMode($form)) {
            $created = $submit->save(null, $form->submitAsAnonymous(false, (bool)$ctx->tokenAccess), true, false);
            if ($created instanceof FormAnswer) {
                $answer = $created;
                $submit->editingAnswer = $created;
                $this->rememberProgressDraft($form, $created);
            }
        }
        $runner = new \humhub\modules\thiscoveryForms\services\FormActionService();
        // A guest could otherwise send a branded email to any address by leaving a page, without
        // ever submitting; their page-exit emails wait for the submission (SEC-5).
        $runner->deferEmails = Yii::$app->user->isGuest;
        $result = $runner->run(
            $form,
            is_array($actions) ? $actions : [],
            $submit->values,
            $this->postedActionVars($form, $answer instanceof FormAnswer ? $answer : null),
            $answer instanceof FormAnswer ? $answer : null,
            $ctx->member,
            $source,
            $this->isPreviewMode($form),
            $trigger === 'page'
        );
        \humhub\modules\thiscoveryForms\services\FormActionService::rememberDeferred((int)$form->id, $runner->deferred);
        return ['ok' => true] + $result;
    }

    /**
     * Guest-safe file upload for fill (HumHub /file/file/upload requires login).
     */
    public function actionUpload($id = null)
    {
        $form = $this->findFillForm($id);
        $this->assertFillAccess($form);
        $this->forcePostRequest();
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!$this->formAcceptsUploads($form)) {
            throw new ForbiddenHttpException();
        }

        $fieldId = (int)Yii::$app->request->post('field_id', Yii::$app->request->get('field_id', 0));
        // The quota is per file question: a posted id that is not one on this form is refused,
        // so it cannot be varied to get a fresh allowance (V3-50).
        $fileField = null;
        foreach ($form->fields as $candidate) {
            if ((int)$candidate->id === $fieldId && $candidate->type === \humhub\modules\thiscoveryForms\models\FormField::TYPE_FILE) {
                $fileField = $candidate;
                break;
            }
        }
        if (!$fileField) {
            throw new ForbiddenHttpException();
        }
        $guest = Yii::$app->user->isGuest;
        $ip = (string)(Yii::$app->request->userIP ?? '');
        $files = [];
        $fileRules = $fileField->getFileRules();
        foreach (UploadedFile::getInstancesByName('files') as $uploaded) {
            // Type and content first (SEC-13), then size and quota.
            $reason = \humhub\modules\thiscoveryForms\services\UploadQuota::typeError(
                (string)$uploaded->name,
                (string)$uploaded->tempName,
                $fileRules['types']
            ) ?? \humhub\modules\thiscoveryForms\services\UploadQuota::allows(
                (int)$form->id,
                $fieldId,
                (int)$uploaded->size,
                $guest,
                $ip,
                $fileRules['maxMb'] !== null ? $fileRules['maxMb'] * 1048576 : null
            );
            if ($reason !== null) {
                $files[] = [
                    'error' => true,
                    'errors' => [$reason],
                    'name' => Html::encode((string)$uploaded->name),
                    'size' => Html::encode((string)$uploaded->size),
                ];
                continue;
            }
            $file = new FileUpload();
            $file->setUploadedFile($uploaded);
            $file->show_in_stream = false;
            if (Yii::$app->user->isGuest) {
                $file->created_by = null;
                $file->updated_by = null;
            }
            if ($file->save()) {
                ImageHelper::downscaleImage($file);
                UploadGrant::remember((int)$form->id, (string)$file->guid);
                \humhub\modules\thiscoveryForms\services\UploadQuota::record((int)$form->id, $fieldId, (int)$uploaded->size);
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

    public function actionDeleteFile($id = null)
    {
        $form = $this->findFillForm($id);
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

        $postedAnswerId = (int)Yii::$app->request->post('answer_id', 0);
        if ($postedAnswerId > 0) {
            $answer = FormAnswer::findOne(['id' => $postedAnswerId, 'form_id' => (int)$form->id]);
            $user = Yii::$app->user->getIdentity();
            if (!$answer || (!$form->canManage($user) && !$form->canEditOwnAnswer($answer, $user))) {
                throw new ForbiddenHttpException();
            }
        } else {
            $answer = $this->resolveFillExisting($form, new SubmitForm(['form' => $form]), false);
        }
        $editing = $answer instanceof FormAnswer
            && ($answer->status !== FormAnswer::STATUS_COMPLETE || $form->allowsEdit());
        if (!UploadGrant::mayRemove($form, $file, $answer instanceof FormAnswer ? $answer : null, $editing)) {
            throw new ForbiddenHttpException();
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

    /**
     * Serve a file used by a form without requiring login.
     * Rich-text editor uploads are often unattached (empty object_model);
     * those are still served when the GUID appears in the form definition.
     *
     * URL: /thiscovery-forms/global/form-file?id=<formId>&guid=<fileGuid>
     */
    public function actionFormFile($id = null, $guid = null)
    {
        $token = trim((string)Yii::$app->request->get('t', ''));
        if ($token !== '') {
            $form = $this->findFillForm(null);
        } else {
            $form = CustomForm::findOne((int)$id);
            if (!$form) {
                throw new NotFoundHttpException('Form not found.');
            }
            CustomForm::assertNotTrashed($form);
        }

        $this->assertFillAccess($form);

        $file = File::findOne(['guid' => $guid]);
        if (!$file) {
            throw new NotFoundHttpException('File not found.');
        }

        if (!$this->formMayServeFile($form, $file)) {
            throw new ForbiddenHttpException('File does not belong to this form.');
        }

        // A GET never changes anything: files are attached when the form is saved (SEC-15).

        $filePath = $file->store->get();
        if (!$filePath || !is_file($filePath)) {
            throw new NotFoundHttpException('File not available.');
        }

        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        // Only images and PDF display inline; SVG, HTML and anything else download, so a file
        // can never run script on this site. Private forms are not cached by shared caches (SEC-15).
        $mime = strtolower((string)($file->mime_type ?: 'application/octet-stream'));
        $inline = in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf'], true);
        $response->headers->set('Content-Type', $inline ? $mime : 'application/octet-stream');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
        $response->headers->set('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($file->file_name) . '"');
        $public = $form->allowsAnonymous() && $form->isOpen() && !$form->hidesIdentityFromManagers();
        $response->headers->set('Cache-Control', $public ? 'public, max-age=86400' : 'private, max-age=300');
        $response->stream = fopen($filePath, 'rb');
        return $response;
    }

    protected function formMayServeFile(CustomForm $form, File $file): bool
    {
        $formClass = get_class($form);
        if ($file->object_model === $formClass && (int)$file->object_id === (int)$form->getPrimaryKey()) {
            return true;
        }

        $guid = trim((string)$file->guid);
        if ($guid === '') {
            return false;
        }

        $haystacks = [
            (string)$form->description,
            (string)$form->thank_you_content,
        ];
        foreach ($form->fields as $field) {
            $haystacks[] = (string)$field->options_json;
            $haystacks[] = (string)$field->label;
        }
        foreach ($haystacks as $hay) {
            if ($hay !== '' && str_contains($hay, $guid)) {
                return true;
            }
        }

        return false;
    }
}
