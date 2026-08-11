<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\thiscoveryForms\services\ResumeService;
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

    protected function assertFillAccess(CustomForm $form): void
    {
        if (Yii::$app->user->isGuest) {
            \humhub\modules\thiscoveryForms\helpers\GuestAccess::assertCanView($form);
            if (!$form->isGlobal() && !$form->content->canView()) {
                throw new ForbiddenHttpException(Yii::t(
                    'ThiscoveryFormsModule.base',
                    'This form is not publicly accessible in this space.'
                ));
            }
        } elseif (!$form->content->canView()) {
            throw new ForbiddenHttpException();
        }

        if ($form->isDraft() && !$form->canManage()) {
            throw new ForbiddenHttpException(Yii::t('ThiscoveryFormsModule.base', 'This form is still a draft.'));
        }
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
     * Load draft for fill: resume code wins, else logged-in user's in-progress answer.
     */
    protected function resolveFillExisting(CustomForm $form, SubmitForm $submit): ?FormAnswer
    {
        $draft = $this->resolveDraftFromRequest($form);
        if ($draft) {
            $submit->loadFromAnswer($draft);
            return $draft;
        }

        if (!$form->allow_multiple && !$form->allowsAnonymous()) {
            $complete = $form->getUserAnswer();
            if ($complete) {
                $submit->loadFromAnswer($complete);
                return $complete;
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
            return false;
        }

        return $form->canEditOwnAnswer($existing) || $form->canManage();
    }

    protected function handleSaveProgress(CustomForm $form, SubmitForm $submit, ?FormAnswer $existing)
    {
        if (!$this->canContinueDraft($form, $existing)) {
            throw new ForbiddenHttpException();
        }

        $anonymous = $form->allowsAnonymous();
        $submit->loadValuesFromRequest(Yii::$app->request->post());
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
}
