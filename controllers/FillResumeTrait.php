<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\thiscoveryForms\services\FillContext;
use humhub\modules\thiscoveryForms\services\FillContextService;
use humhub\modules\thiscoveryForms\services\ResumeService;
use humhub\modules\thiscoveryForms\services\TranslationService;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

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

    protected function assertFillAccess(CustomForm $form): void
    {
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
    }

    protected function assertResumeEnabled(CustomForm $form): void
    {
        if (!$form->allowsResume()) {
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
        if ($form->isLongitudinal() || $form->isConsensus()) {
            $column = $form->isLongitudinal() ? 'wave_id' : 'round_id';
            $scopeId = $form->isLongitudinal() ? ($ctx->wave->id ?? null) : ($ctx->round->id ?? null);
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
        $draft = $this->resolveDraftFromRequest($form);
        if ($draft && $form->allowsResume()) {
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
        $resumeOn = $form->allowsResume();
        $startNew = $this->isStartNewRequest();
        $skipInProgress = $resumeOn && !$this->hasResumeIntent($form);

        if ($form->isLongitudinal() || $form->isConsensus()) {
            $column = $form->isLongitudinal() ? 'wave_id' : 'round_id';
            $scopeId = $form->isLongitudinal() ? ($ctx->wave->id ?? null) : ($ctx->round->id ?? null);
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

        if ($startNew && $resumeOn) {
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
        $ctx = $this->fillContext($form);
        if ($form->isLongitudinal() || $form->isConsensus()) {
            if ($ctx->blockReason && !$form->canManage()) {
                return false;
            }
            if (!$existing) {
                return $form->isOpen() && ($form->canAnswer() || $ctx->tokenAccess || $ctx->member);
            }
        }

        if (!$existing) {
            return $form->canAnswer();
        }
        if ($existing->isInProgress()) {
            // Possession of the resume code (already resolved) authorizes continue.
            $code = (string)(Yii::$app->request->post('resume_code')
                ?: Yii::$app->request->get('resume', ''));
            if ($code !== '' && $this->resumeService()->normalizeCode($code) === $existing->resume_code) {
                return $form->isOpen();
            }
            if (!$form->allowsAnonymous() && !$existing->isAnonymous()) {
                $user = Yii::$app->user->getIdentity();
                return $user && (int)$existing->created_by === (int)$user->id && $form->isOpen();
            }
            $ctx = $this->fillContext($form);
            if ($ctx->member && (int)$existing->panel_member_id === (int)$ctx->member->id) {
                return $form->isOpen();
            }
            return false;
        }

        return $form->canEditOwnAnswer($existing) || $form->canManage();
    }

    protected function handleSaveProgress(CustomForm $form, SubmitForm $submit, ?FormAnswer $existing)
    {
        $this->assertResumeEnabled($form);

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
            $currentPage
        );

        if (!$answer) {
            Yii::$app->session->setFlash('error', implode(' ', $submit->getErrorSummary(true))
                ?: Yii::t('ThiscoveryFormsModule.base', 'Could not save your progress. Please try again.'));
            return $this->renderFillView($form, $submit, $existing);
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
        ];
    }

    protected function afterCompleteSave(CustomForm $form, FillContext $ctx, $answer, bool $anonymous): void
    {
        if ($anonymous) {
            $form->markGuestAnswered($ctx->wave->id ?? null, $ctx->round->id ?? null);
        }
        if ($ctx->member) {
            $ctx->member->markConsent();
        }
        if ($form->isProject() && $answer instanceof FormAnswer && !$anonymous) {
            (new \humhub\modules\thiscoveryForms\services\ApprovalWorkflowService())->submitForReview($answer);
        }
    }
}
