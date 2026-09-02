<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\components\Controller;
use humhub\components\access\ControllerAccess;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\thiscoveryForms\notifications\FormAnsweredNotification;
use humhub\modules\thiscoveryForms\permissions\AnswerGlobalForm;
use humhub\modules\thiscoveryForms\permissions\CreateGlobalForm;
use humhub\modules\thiscoveryForms\permissions\ManageGlobalForm;
use humhub\modules\thiscoveryForms\services\DashboardService;
use humhub\modules\thiscoveryForms\services\ExportService;
use humhub\modules\thiscoveryForms\services\FolderService;
use humhub\modules\thiscoveryForms\services\FormListService;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class GlobalController extends Controller
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

    public $subLayout = '@thiscovery-forms/views/layouts/default';

    /**
     * Allow anonymous form fill without requiring network-wide guest mode.
     * Other actions still require login via access rules.
     */
    protected $access = ControllerAccess::class;

    protected function getAccessRules()
    {
        return [
            ['login', 'actions' => [
                'index', 'create', 'edit', 'edit-answer', 'answers',
                'dashboard', 'overview', 'export', 'delete',
                'integrity', 'integrity-status', 'integrity-note', 'access-tokens',
                'save-template', 'export-questions', 'import-questions', 'sample-questions',
                'library-list', 'library-save', 'library-delete', 'library-insert',
                'insert-health-status',
                'panel-save', 'panel-add-member', 'panel-remove-member', 'panel-invite',
                'wave-save', 'wave-status',
                'round-save', 'round-status', 'round-publish', 'round-delphi',
                'translations-save', 'generate-translations', 'export-translations', 'import-translations',
                'stage-save', 'stage-delete', 'stage-move',
                'catalogue', 'project', 'answer-approve', 'answer-changes', 'answer-archive',
                'answer-detail',
                'folder-edit', 'folder-delete', 'move-form',
                'panels', 'panel-edit', 'panel-view', 'panel-delete', 'panel-member',
                'panel-member-add', 'panel-member-remove', 'panel-import', 'panel-sample',
                'panel-wave-save', 'panel-wave-status',
                'email-templates', 'email-template-edit', 'email-template-delete',
                'regenerate-preview', 'regenerate-dashboard-share',
                'help',
            ]],
        ];
    }

    public function actionIndex()
    {
        $canManage = Yii::$app->user->can(ManageGlobalForm::class) || Yii::$app->user->can(CreateGlobalForm::class);
        $canAnswer = Yii::$app->user->can(AnswerGlobalForm::class);

        if (!$canManage && !$canAnswer) {
            throw new ForbiddenHttpException();
        }

        $query = CustomForm::findLive()->joinWith('content')
            ->andWhere(['content.contentcontainer_id' => null]);

        if (!$canManage) {
            $query->andWhere(['custom_form.status' => CustomForm::STATUS_OPEN]);
        }

        [$provider, $filters] = FormListService::provider($query, Yii::$app->request->queryParams, null);

        return $this->render('index', [
            'dataProvider' => $provider,
            'filters' => $filters,
            'canCreate' => Yii::$app->user->can(CreateGlobalForm::class),
            'templates' => Yii::$app->user->can(CreateGlobalForm::class)
                ? CustomForm::findAvailableTemplates(null)
                : [],
            'folderBrowse' => FolderService::browse(null, Yii::$app->request->queryParams),
            'canManagePanels' => $canManage,
            'canViewHelp' => $this->canViewHelp(),
        ]);
    }

    protected function prepareNewForm(): CustomForm
    {
        $form = new CustomForm();
        $form->content->visibility = \humhub\modules\content\models\Content::VISIBILITY_PUBLIC;
        return $form;
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
        // Keep Administration left menu when editing from the admin Forms area.
        $this->subLayout = '@humhub/modules/admin/views/layouts/main';

        $request = Yii::$app->request;

        if ($request->isPost) {
            if (!$form->load($request->post())) {
                Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Invalid form data.'));
            } elseif (!$form->save()) {
                Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Could not save the form.'));
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

        return $this->render('@thiscovery-forms/views/form/edit', [
            'formModel' => $form,
            'isNew' => $isNew,
            'contentContainer' => null,
            'fields' => $isNew ? $seedFields : $form->fields,
        ]);
    }

    public function actionView($id = null)
    {
        // Missing id used to end up as a vague unauthorized/login redirect to the guest homepage.
        if ($id === null || $id === '' || !ctype_digit((string)$id)) {
            throw new NotFoundHttpException(Yii::t(
                'ThiscoveryFormsModule.base',
                'This form link is incomplete. Please use the full share URL from the form Share tab.'
            ));
        }

        $form = $this->findForm($id);
        $this->assertFillAccess($form);

        if (Yii::$app->user->isGuest) {
            // Log first-hit diagnostics while we investigate intermittent guest redirects.
            Yii::info(sprintf(
                'guest form view id=%s uri=%s secure=%s xfproto=%s ua=%s',
                $id,
                Yii::$app->request->absoluteUrl,
                Yii::$app->request->isSecureConnection ? '1' : '0',
                Yii::$app->request->headers->get('X-Forwarded-Proto', '-'),
                substr((string)Yii::$app->request->userAgent, 0, 120)
            ), 'thiscovery-forms');
        }

        $submit = new SubmitForm(['form' => $form]);
        $existing = $this->resolveFillExisting($form, $submit);

        if (Yii::$app->request->isPost) {
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
        if (!$this->isPreviewMode($form)) {
            (new \humhub\modules\thiscoveryForms\services\integrity\IntegrityService())->onFillOpen($form, $existing);
        }
        return $this->render('@thiscovery-forms/views/form/view', array_merge([
            'formModel' => $form,
            'submit' => $submit,
            'existing' => $existing,
            'contentContainer' => null,
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
        return $this->render('@thiscovery-forms/views/form/thankyou', [
            'formModel' => $form,
            'contentContainer' => null,
            'answer' => $form->isProject() && !$isPreview ? $answer : null,
            'isPreview' => $isPreview,
        ]);
    }

    protected function notifySubmission(CustomForm $form, FormAnswer $answer): void
    {
        try {
            $identity = Yii::$app->user->getIdentity();
            $targets = array_filter(
                $form->getNotificationTargets(),
                static fn($user) => $identity && (int)$user->id !== (int)$identity->id
            );
            if ($targets && $identity) {
                Yii::createObject(['class' => FormAnsweredNotification::class])
                    ->from($identity)
                    ->about($answer)
                    ->sendBulk($targets);
            }
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

        return $this->render('@thiscovery-forms/views/form/dashboard', [
            'formModel' => $form,
            'stats' => $stats,
            'contentContainer' => null,
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

        return $this->render('@thiscovery-forms/views/form/dashboard', [
            'formModel' => $form,
            'stats' => $stats,
            'contentContainer' => null,
            'isPublic' => true,
        ]);
    }

    public function actionOverview()
    {
        $canManage = Yii::$app->user->can(ManageGlobalForm::class) || Yii::$app->user->can(CreateGlobalForm::class);
        if (!$canManage) {
            throw new ForbiddenHttpException();
        }

        $forms = CustomForm::findLive()->joinWith('content')
            ->andWhere(['content.contentcontainer_id' => null])
            ->all();
        $stats = (new DashboardService())->getOverview($forms);

        return $this->render('@thiscovery-forms/views/form/overview', [
            'stats' => $stats,
            'contentContainer' => null,
            'canCreate' => Yii::$app->user->can(CreateGlobalForm::class),
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
        $form = CustomForm::find()->joinWith('content')
            ->andWhere(['custom_form.id' => $id])
            ->andWhere(['content.contentcontainer_id' => null])
            ->one();

        if (!$form) {
            throw new NotFoundHttpException();
        }

        return $form;
    }
}
