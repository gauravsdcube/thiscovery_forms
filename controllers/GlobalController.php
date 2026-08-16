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
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class GlobalController extends Controller
{
    use FillResumeTrait;
    use StudioTrait;
    use ProgrammeTrait;
    use ApprovalTrait;

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
                'save-template', 'export-questions', 'import-questions', 'sample-questions',
                'library-list', 'library-save', 'library-delete', 'library-insert',
                'panel-save', 'panel-add-member', 'panel-remove-member', 'panel-invite',
                'wave-save', 'wave-status',
                'round-save', 'round-status', 'round-publish', 'round-delphi',
                'translations-save', 'export-translations', 'import-translations',
                'stage-save', 'stage-delete', 'stage-move',
                'catalogue', 'project', 'answer-approve', 'answer-changes', 'answer-archive',
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

        $provider = new ActiveDataProvider([
            'query' => $query->with('fields'),
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('index', [
            'dataProvider' => $provider,
            'canCreate' => Yii::$app->user->can(CreateGlobalForm::class),
            'templates' => Yii::$app->user->can(CreateGlobalForm::class)
                ? CustomForm::findAvailableTemplates(null)
                : [],
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
        $request = Yii::$app->request;

        if ($request->isPost) {
            if (!$form->load($request->post())) {
                Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Invalid form data.'));
            } elseif (!$form->save()) {
                Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Could not save the form.'));
            } else {
                $fieldRows = $request->post('fields', []);
                if (!is_array($fieldRows)) {
                    $fieldRows = [];
                }
                if (!$form->saveFieldsFromPost($fieldRows)) {
                    Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Form saved, but some fields could not be stored.'));
                } else {
                    Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Form saved.'));
                    return $this->redirect(Url::toView($form));
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
        $draft = $form->allowsResume() ? $this->resolveDraftFromRequest($form) : null;
        if ($draft) {
            $existing = $draft;
        }

        if (!$this->canContinueDraft($form, $existing)) {
            throw new ForbiddenHttpException();
        }

        $submit->loadValuesFromRequest(Yii::$app->request->post());
        $ctx = $this->fillContext($form);
        $this->applyFillContext($form, $submit, $ctx);
        $anonymous = $form->allowsAnonymous() || (Yii::$app->user->isGuest && $ctx->tokenAccess);
        $wasNewComplete = !$existing || $existing->isInProgress();
        $answer = $submit->save($existing, $anonymous);

        if (!$answer) {
            Yii::$app->session->setFlash('error', implode(' ', $submit->getErrorSummary(true)));
            return $this->renderFillView($form, $submit, $existing, [
                'editingAnswer' => $existing && $existing->isComplete(),
            ]);
        }

        $this->afterCompleteSave($form, $ctx, $answer, $anonymous);

        if ($wasNewComplete && !$anonymous && !$form->isProject()) {
            $this->notifySubmission($form, $answer);
        }

        return $this->render('@thiscovery-forms/views/form/thankyou', [
            'formModel' => $form,
            'contentContainer' => null,
            'answer' => $form->isProject() ? $answer : null,
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

    public function actionAnswers($id)
    {
        $form = $this->findForm($id);
        if (!$form->canViewAnswers()) {
            throw new ForbiddenHttpException();
        }

        $provider = new ActiveDataProvider([
            'query' => $form->getAnswers()->with(['user', 'answerFields', 'wave', 'round', 'panelMember', 'currentStage']),
            'pagination' => ['pageSize' => 30],
        ]);

        return $this->render('@thiscovery-forms/views/form/answers', [
            'formModel' => $form,
            'dataProvider' => $provider,
            'contentContainer' => null,
        ]);
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

        $csv = (new ExportService())->toCsv($form);
        $filename = 'form-' . $form->id . '-' . date('Ymd-His') . '.csv';

        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        Yii::$app->response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return "\xEF\xBB\xBF" . $csv;
    }

    public function actionDelete($id)
    {
        $form = $this->findForm($id);
        if (!$form->canManage()) {
            throw new ForbiddenHttpException();
        }
        if (!Yii::$app->request->isPost) {
            throw new HttpException(405);
        }

        $form->delete();
        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Form deleted.'));
        return $this->redirect(Url::toIndex(null));
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
