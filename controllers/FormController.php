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
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class FormController extends ContentContainerController
{
    use FillResumeTrait;

    protected function getAccessRules()
    {
        return [
            ['guestAccess' => ['view', 'save-progress', 'resume', 'email-resume']],
        ];
    }

    public function actionIndex()
    {
        $probe = new CustomForm($this->contentContainer);
        if (!$probe->canCreate() && !$probe->canManage() && !CustomForm::find()->contentContainer($this->contentContainer)->count()) {
            throw new ForbiddenHttpException();
        }

        $provider = new ActiveDataProvider([
            'query' => CustomForm::find()->contentContainer($this->contentContainer)->readable()->with('fields'),
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('index', [
            'dataProvider' => $provider,
            'contentContainer' => $this->contentContainer,
            'canCreate' => $probe->canCreate(),
        ]);
    }

    public function actionCreate()
    {
        $form = new CustomForm($this->contentContainer);
        if (!$form->canCreate()) {
            throw new ForbiddenHttpException();
        }

        $form->status = CustomForm::STATUS_DRAFT;
        $form->answers_visibility = CustomForm::ANSWERS_MANAGERS;

        return $this->handleEdit($form, true);
    }

    public function actionEdit($id)
    {
        $form = $this->findForm($id);
        if (!$form->canManage()) {
            throw new ForbiddenHttpException();
        }

        return $this->handleEdit($form, false);
    }

    protected function handleEdit(CustomForm $form, bool $isNew)
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

        return $this->render('edit', [
            'formModel' => $form,
            'isNew' => $isNew,
            'contentContainer' => $this->contentContainer,
            'fields' => $isNew ? [] : $form->fields,
        ]);
    }

    public function actionView($id)
    {
        $form = $this->findForm($id);
        $this->assertFillAccess($form);

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
        return $this->render('view', array_merge([
            'formModel' => $form,
            'submit' => $submit,
            'existing' => $existing,
            'contentContainer' => $this->contentContainer,
            'savedDraft' => null,
        ], $extra));
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

        return $this->renderFillView($form, $submit, $answer);
    }

    protected function handleSubmit(CustomForm $form, SubmitForm $submit, ?FormAnswer $existing)
    {
        $draft = $this->resolveDraftFromRequest($form);
        if ($draft) {
            $existing = $draft;
        }

        if (!$this->canContinueDraft($form, $existing)) {
            throw new ForbiddenHttpException();
        }

        $anonymous = $form->allowsAnonymous();
        $submit->loadValuesFromRequest(Yii::$app->request->post());
        $wasNewComplete = !$existing || $existing->isInProgress();
        $answer = $submit->save($existing, $anonymous);

        if (!$answer) {
            Yii::$app->session->setFlash('error', implode(' ', $submit->getErrorSummary(true)));
            return $this->renderFillView($form, $submit, $existing);
        }

        if ($anonymous) {
            $form->markGuestAnswered();
        }

        if ($wasNewComplete && !$anonymous) {
            $this->notifySubmission($form, $answer);
        }

        return $this->render('thankyou', [
            'formModel' => $form,
            'contentContainer' => $this->contentContainer,
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

    public function actionAnswers($id)
    {
        $form = $this->findForm($id);
        if (!$form->canViewAnswers()) {
            throw new ForbiddenHttpException();
        }

        $provider = new ActiveDataProvider([
            'query' => $form->getAnswers()->with(['user', 'answerFields']),
            'pagination' => ['pageSize' => 30],
        ]);

        return $this->render('answers', [
            'formModel' => $form,
            'dataProvider' => $provider,
            'contentContainer' => $this->contentContainer,
        ]);
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
        ]);
    }

    public function actionOverview()
    {
        $probe = new CustomForm($this->contentContainer);
        if (!$probe->canManage() && !$probe->canCreate()) {
            $hasVisible = false;
            foreach (CustomForm::find()->contentContainer($this->contentContainer)->all() as $form) {
                if ($form->canViewAnswers()) {
                    $hasVisible = true;
                    break;
                }
            }
            if (!$hasVisible) {
                throw new ForbiddenHttpException();
            }
        }

        $forms = CustomForm::find()->contentContainer($this->contentContainer)->all();
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
        return $this->redirect(Url::toIndex($this->contentContainer));
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
