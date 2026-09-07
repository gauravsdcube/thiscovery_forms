<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\thiscoveryForms\notifications\FormAnsweredNotification;
use humhub\modules\thiscoveryForms\services\DashboardService;
use humhub\modules\thiscoveryForms\services\ExportService;
use humhub\modules\thiscoveryForms\services\FolderService;
use humhub\modules\thiscoveryForms\services\FormListService;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class FormController extends ContentContainerController
{
    use FillResumeTrait;
    use StudioTrait;
    use ProgrammeTrait;
    use ApprovalTrait;
    use AnswersListTrait;
    use IntegrityTrait;
    use FolderTrait;
    use PanelAdminTrait;
    use EmailAdminTrait;
    use HelpTrait;
    use VersioningTrait;

    protected function getAccessRules()
    {
        return [
            ['guestAccess' => ['view', 'save-progress', 'resume', 'email-resume', 'submit-json', 'public-dashboard', 'run-actions', 'upload', 'delete-file', 'form-file']],
        ];
    }

    protected function prepareNewForm(): CustomForm
    {
        return new CustomForm($this->contentContainer);
    }

    public function actionIndex()
    {
        $probe = new CustomForm($this->contentContainer);
        if (!$probe->canCreate() && !$probe->canManage() && !CustomForm::findLive()->contentContainer($this->contentContainer)->count()) {
            throw new ForbiddenHttpException();
        }

        $query = CustomForm::findLive()->contentContainer($this->contentContainer)->readable();
        [$provider, $filters] = FormListService::provider($query, Yii::$app->request->queryParams, $this->contentContainer);

        return $this->render('index', [
            'dataProvider' => $provider,
            'filters' => $filters,
            'contentContainer' => $this->contentContainer,
            'canCreate' => $probe->canCreate(),
            'templates' => $probe->canCreate() || $probe->canManage()
                ? CustomForm::findAvailableTemplates($this->contentContainer)
                : [],
            'folderBrowse' => FolderService::browse($this->contentContainer, Yii::$app->request->queryParams),
            'canManagePanels' => $probe->canCreate() || $probe->canManage(),
            'canViewHelp' => $this->canViewHelp(),
        ]);
    }

    public function actionEdit($id)
    {
        $form = $this->findForm($id);
        if (!$form->canManage()) {
            throw new ForbiddenHttpException();
        }

        return $this->handleEdit($form, false);
    }

    protected function handleEdit(CustomForm $form, bool $isNew, array $seedFields = [])
    {
        $request = Yii::$app->request;

        if ($request->isPost) {
            if (!$form->load($request->post())) {
                Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Invalid form data.'));
            } elseif (!$form->save()) {
                $errors = $form->getFirstErrors();
                Yii::$app->session->setFlash(
                    'error',
                    $errors
                        ? implode(' ', $errors)
                        : Yii::t('ThiscoveryFormsModule.base', 'Could not save the form.')
                );
            } else {
                $fieldError = null;
                $fieldRows = $this->postedFieldRows($fieldError);
                if ($fieldRows === null) {
                    Yii::$app->session->setFlash('error', $fieldError);
                } elseif (!$form->saveFieldsFromPost($fieldRows)) {
                    Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Form saved, but some fields could not be stored.'));
                } else {
                    $integrityPost = Yii::$app->request->post('integrity');
                    if (is_array($integrityPost)) {
                        \humhub\modules\thiscoveryForms\services\integrity\IntegritySettings::saveForm($form, $integrityPost);
                    }
                    try {
                        (new \humhub\modules\thiscoveryForms\services\FormVersionService())->recordSave($form);
                    } catch (\Throwable $e) {
                        Yii::warning('Thiscovery Forms revision save failed: ' . $e->getMessage(), 'thiscovery-forms');
                    }
                    Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Form saved.'));
                    if (class_exists(\humhub\modules\thiscoveryTranslate\services\FormsHook::class)) {
                        \humhub\modules\thiscoveryTranslate\services\FormsHook::queueFormTranslation((int)$form->id);
                        $pub = \humhub\modules\thiscoveryTranslate\services\FormsHook::checkPublishReady($form);
                        if (!empty($pub['message'])) {
                            if (!$pub['ok']) {
                                $form->status = \humhub\modules\thiscoveryForms\models\CustomForm::STATUS_DRAFT;
                                $form->save(false, ['status']);
                                Yii::$app->session->setFlash('error', $pub['message'] . ' ' . Yii::t('ThiscoveryFormsModule.base', 'Form kept as draft until translations are ready.'));
                            } else {
                                Yii::$app->session->setFlash('warning', $pub['message']);
                            }
                        }
                    }
                    return $this->redirectAfterStudioSave($form);
                }
            }
        }

        return $this->render('edit', [
            'formModel' => $form,
            'isNew' => $isNew,
            'contentContainer' => $this->contentContainer,
            'fields' => $isNew ? $seedFields : $form->fields,
        ]);
    }

    public function actionView($id)
    {
        $form = $this->findForm($id);
        $this->assertFillAccess($form);

        $submit = new SubmitForm(['form' => $form]);
        $existing = $this->resolveFillExisting($form, $submit);

        if (Yii::$app->request->isPost && !Yii::$app->request->post('integrity_open_challenge')) {
            return $this->handleSubmit($form, $submit, $existing);
        }

        return $this->renderFillView($form, $submit, $existing);
    }

    public function actionSaveProgress($id)
    {
        $form = $this->findForm($id);
        $this->assertFillAccess($form);

        if (!Yii::$app->request->isPost) {
            return $this->redirect(Url::toView($form));
        }

        $submit = new SubmitForm(['form' => $form]);
        $existing = $this->resolveFillExisting($form, $submit);

        return $this->handleSaveProgress($form, $submit, $existing);
    }

    public function actionResume($id)
    {
        $form = $this->findForm($id);
        $this->assertFillAccess($form);

        return $this->handleResumeLookup($form);
    }

    public function actionEmailResume($id)
    {
        $form = $this->findForm($id);
        $this->assertFillAccess($form);

        return $this->handleEmailResumeCode($form);
    }

    protected function renderFillView(
        CustomForm $form,
        SubmitForm $submit,
        ?FormAnswer $existing,
        array $extra = []
    ) {
        $this->applyFillLayout($form);
        $preview = $this->isPreviewMode($form);
        try {
            (new \humhub\modules\thiscoveryForms\services\FormVersionService())
                ->applyFillDefinition($form, $existing, $preview);
            $submit->form = $form;
        } catch (\Throwable $e) {
            Yii::warning('Thiscovery Forms edition hydrate failed: ' . $e->getMessage(), 'thiscovery-forms');
        }
        $openCaptchaError = null;
        if (!$preview) {
            $svc = new \humhub\modules\thiscoveryForms\services\integrity\IntegrityService();
            if (Yii::$app->request->isPost && Yii::$app->request->post('integrity_open_challenge')) {
                if (!$svc->verifyOpenCaptcha($form, Yii::$app->request->post())) {
                    $openCaptchaError = Yii::t('ThiscoveryFormsModule.base', 'Please complete the verification check and try again.');
                }
            }
            if ($svc->prepareFillOpen($form, $existing) === 'challenge') {
                return $this->render('@thiscovery-forms/views/form/_integrity_open_captcha', [
                    'formModel' => $form,
                    'integritySettings' => \humhub\modules\thiscoveryForms\services\integrity\IntegritySettings::forForm($form),
                    'error' => $openCaptchaError,
                    'contentContainer' => $this->contentContainer,
                ]);
            }
        }
        return $this->render('view', array_merge([
            'formModel' => $form,
            'submit' => $submit,
            'existing' => $existing,
            'contentContainer' => $this->contentContainer,
            'savedDraft' => null,
        ], $this->fillViewExtras($form, $this->fillContext($form)), $extra));
    }

    public function actionEditAnswer($id, $answerId)
    {
        $form = $this->findForm($id);
        $answer = FormAnswer::findOne(['id' => $answerId, 'form_id' => $form->id]);
        if (!$answer) {
            throw new NotFoundHttpException();
        }

        if (!$form->canEditOwnAnswer($answer) && !$form->canManage()) {
            throw new ForbiddenHttpException();
        }

        $submit = new SubmitForm(['form' => $form]);
        $submit->loadFromAnswer($answer);

        if (Yii::$app->request->isPost) {
            return $this->handleSubmit($form, $submit, $answer);
        }

        return $this->renderFillView($form, $submit, $answer, ['editingAnswer' => true]);
    }

    protected function handleSubmit(CustomForm $form, SubmitForm $submit, ?FormAnswer $existing)
    {
        $draft = ($form->allowsResume() || $form->keepsPartials())
            ? $this->resolveDraftFromRequest($form)
            : null;
        if ($draft) {
            $existing = $draft;
        }

        $this->applyEditionForFill($form, $existing);
        $submit->form = $form;

        if (!$this->canContinueDraft($form, $existing)) {
            throw new ForbiddenHttpException();
        }

        $submit->loadValuesFromRequest(Yii::$app->request->post());
        $ctx = $this->fillContext($form);
        $this->applyFillContext($form, $submit, $ctx);
        $isPreview = $this->isPreviewMode($form);
        if (!$isPreview) {
            $gate = (new \humhub\modules\thiscoveryForms\services\integrity\IntegrityService())->gateSubmit($form, Yii::$app->request->post(), $ctx);
            if ($gate) {
                Yii::$app->session->setFlash('error', $gate);
                return $this->renderFillView($form, $submit, $existing, [
                    'editingAnswer' => $existing && $existing->isComplete(),
                ]);
            }
        }
        $anonymous = $isPreview || $form->allowsAnonymous() || (Yii::$app->user->isGuest && $ctx->tokenAccess);
        $wasNewComplete = !$existing || $existing->isInProgress();
        $answer = $submit->save($existing, $anonymous, false, $isPreview);

        if (!$answer) {
            Yii::$app->session->setFlash('error', implode(' ', $submit->getErrorSummary(true)));
            return $this->renderFillView($form, $submit, $existing, [
                'editingAnswer' => $existing && $existing->isComplete(),
            ]);
        }

        if ($isPreview) {
            Yii::$app->session->set($this->previewAnswerSessionKey($form), (int)$answer->id);
        }
        $this->afterCompleteSave($form, $ctx, $answer, $anonymous);

        if (!$isPreview && $wasNewComplete && !$anonymous && !$form->isProject()) {
            $this->notifySubmission($form, $answer);
        }

        $this->applyFillLayout($form);
        if (!$isPreview && $form->usesCompletionRedirect()) {
            return $this->redirect($form->getCompletionRedirectUrl());
        }
        return $this->render('thankyou', [
            'formModel' => $form,
            'contentContainer' => $this->contentContainer,
            'answer' => $form->isProject() && !$isPreview ? $answer : null,
            'isPreview' => $isPreview,
        ]);
    }

    protected function notifySubmission(CustomForm $form, FormAnswer $answer): void
    {
        $identity = Yii::$app->user->getIdentity();
        if (!$identity) {
            return;
        }

        $targets = array_filter(
            $form->getNotificationTargets(),
            static fn($user) => (int)$user->id !== (int)$identity->id
        );

        if (!$targets) {
            return;
        }

        try {
            Yii::createObject(['class' => FormAnsweredNotification::class])
                ->from($identity)
                ->about($answer)
                ->sendBulk($targets);
        } catch (\Throwable $e) {
            Yii::error('Thiscovery Forms notification failed: ' . $e->getMessage(), 'thiscovery-forms');
        }
    }

    public function actionDashboard($id)
    {
        $form = $this->findForm($id);
        if (!$form->canViewAnswers()) {
            throw new ForbiddenHttpException();
        }

        $stats = (new DashboardService())->getFormDashboard($form);

        return $this->render('dashboard', [
            'formModel' => $form,
            'stats' => $stats,
            'contentContainer' => $this->contentContainer,
            'isPublic' => false,
        ]);
    }

    public function actionPublicDashboard($id)
    {
        $form = $this->findForm($id);
        $share = (string)Yii::$app->request->get('share', '');
        if (!$form->isValidPublicDashboardToken($share)) {
            throw new ForbiddenHttpException(Yii::t('ThiscoveryFormsModule.base', 'This dashboard link is not available.'));
        }

        $stats = (new DashboardService())->getFormDashboard($form);

        return $this->render('dashboard', [
            'formModel' => $form,
            'stats' => $stats,
            'contentContainer' => $this->contentContainer,
            'isPublic' => true,
        ]);
    }

    public function actionOverview()
    {
        $probe = new CustomForm($this->contentContainer);
        if (!$probe->canManage() && !$probe->canCreate()) {
            $hasVisible = false;
            foreach (CustomForm::findLive()->contentContainer($this->contentContainer)->all() as $form) {
                if ($form->canViewAnswers()) {
                    $hasVisible = true;
                    break;
                }
            }
            if (!$hasVisible) {
                throw new ForbiddenHttpException();
            }
        }

        $forms = CustomForm::findLive()->contentContainer($this->contentContainer)->all();
        $visible = array_values(array_filter($forms, static fn(CustomForm $f) => $f->canViewAnswers() || $f->canManage()));
        $stats = (new DashboardService())->getOverview($visible);

        return $this->render('overview', [
            'stats' => $stats,
            'contentContainer' => $this->contentContainer,
            'canCreate' => $probe->canCreate(),
        ]);
    }

    public function actionExport($id)
    {
        $form = $this->findForm($id);
        if (!$form->canViewAnswers()) {
            throw new ForbiddenHttpException();
        }

        $csv = (new ExportService())->toCsv($form, Yii::$app->request->queryParams);
        $filename = 'form-' . $form->id . '-' . date('Ymd-His') . '.csv';

        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        Yii::$app->response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return "\xEF\xBB\xBF" . $csv;
    }

    public function actionDelete($id)
    {
        return $this->deleteManagedForm($this->findForm($id));
    }

    protected function findForm($id): CustomForm
    {
        $form = CustomForm::find()->contentContainer($this->contentContainer)->andWhere(['custom_form.id' => $id])->one();
        if (!$form) {
            throw new NotFoundHttpException();
        }
        return $form;
    }
}
