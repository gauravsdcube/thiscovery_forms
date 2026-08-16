<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormApprovalStage;
use humhub\modules\thiscoveryForms\services\ApprovalWorkflowService;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

trait ApprovalTrait
{
    abstract protected function findForm($id): CustomForm;

    protected function approvalService(): ApprovalWorkflowService
    {
        return new ApprovalWorkflowService();
    }

    protected function redirectApprovalStudio(CustomForm $form)
    {
        return $this->redirect(Url::toEdit($form) . '?tab=approval');
    }

    public function actionStageSave($id)
    {
        $form = $this->findForm($id);
        if (!$form->canManage() || !$form->isProject()) {
            throw new ForbiddenHttpException();
        }
        if (!Yii::$app->request->isPost) {
            return $this->redirectApprovalStudio($form);
        }

        $service = $this->approvalService();
        $stageId = (int)Yii::$app->request->post('stage_id', 0);
        if ($stageId) {
            $stage = FormApprovalStage::findOne(['id' => $stageId, 'form_id' => $form->id]);
            if (!$stage) {
                throw new NotFoundHttpException();
            }
        } else {
            $stage = $service->createStage($form, trim((string)Yii::$app->request->post('name', '')));
        }

        $service->saveStage($stage, Yii::$app->request->post());
        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Approval stage saved.'));
        return $this->redirectApprovalStudio($form);
    }

    public function actionStageDelete($id)
    {
        $form = $this->findForm($id);
        if (!$form->canManage() || !$form->isProject()) {
            throw new ForbiddenHttpException();
        }
        if (!Yii::$app->request->isPost) {
            return $this->redirectApprovalStudio($form);
        }
        $stage = FormApprovalStage::findOne(['id' => (int)Yii::$app->request->post('stage_id', 0), 'form_id' => $form->id]);
        if (!$stage) {
            throw new NotFoundHttpException();
        }
        $this->approvalService()->deleteStage($stage);
        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Approval stage removed.'));
        return $this->redirectApprovalStudio($form);
    }

    public function actionStageMove($id)
    {
        $form = $this->findForm($id);
        if (!$form->canManage() || !$form->isProject()) {
            throw new ForbiddenHttpException();
        }
        if (!Yii::$app->request->isPost) {
            return $this->redirectApprovalStudio($form);
        }
        $stage = FormApprovalStage::findOne(['id' => (int)Yii::$app->request->post('stage_id', 0), 'form_id' => $form->id]);
        if (!$stage) {
            throw new NotFoundHttpException();
        }
        $this->approvalService()->moveStage($stage, (string)Yii::$app->request->post('direction', 'down'));
        return $this->redirectApprovalStudio($form);
    }

    public function actionCatalogue($id)
    {
        $form = $this->findForm($id);
        if (!$form->isProject()) {
            throw new NotFoundHttpException();
        }
        $user = Yii::$app->user->getIdentity();
        if (!$user || (!$form->canViewAnswers($user) && !$form->canAnswerPermissionOnly($user) && !$form->canManage($user))) {
            throw new ForbiddenHttpException();
        }

        $query = $form->getAnswers()
            ->andWhere(['workflow_status' => FormAnswer::WORKFLOW_PUBLISHED])
            ->with(['user', 'answerFields']);

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('@thiscovery-forms/views/form/catalogue', [
            'formModel' => $form,
            'dataProvider' => $provider,
            'contentContainer' => property_exists($this, 'contentContainer') ? $this->contentContainer : null,
        ]);
    }

    public function actionProject($id, $answerId)
    {
        $form = $this->findForm($id);
        $answer = FormAnswer::findOne(['id' => $answerId, 'form_id' => $form->id]);
        if (!$answer || !$form->isProject()) {
            throw new NotFoundHttpException();
        }
        $service = $this->approvalService();
        if (!$service->canViewRecord($answer)) {
            throw new ForbiddenHttpException();
        }

        return $this->render('@thiscovery-forms/views/form/project', [
            'formModel' => $form,
            'answer' => $answer,
            'service' => $service,
            'contentContainer' => property_exists($this, 'contentContainer') ? $this->contentContainer : null,
        ]);
    }

    public function actionAnswerApprove($id, $answerId)
    {
        return $this->handleAnswerAction($id, $answerId, 'approve');
    }

    public function actionAnswerChanges($id, $answerId)
    {
        return $this->handleAnswerAction($id, $answerId, 'changes');
    }

    public function actionAnswerArchive($id, $answerId)
    {
        return $this->handleAnswerAction($id, $answerId, 'archive');
    }

    protected function handleAnswerAction($id, $answerId, string $action)
    {
        $form = $this->findForm($id);
        $answer = FormAnswer::findOne(['id' => $answerId, 'form_id' => $form->id]);
        if (!$answer || !$form->isProject()) {
            throw new NotFoundHttpException();
        }
        if (!Yii::$app->request->isPost) {
            return $this->redirect(Url::toProject($form, $answer));
        }

        $user = Yii::$app->user->getIdentity();
        if (!$user) {
            throw new ForbiddenHttpException();
        }

        $comment = trim((string)Yii::$app->request->post('comment', ''));
        $service = $this->approvalService();
        $ok = match ($action) {
            'approve' => $service->approve($answer, $user, $comment),
            'changes' => $service->requestChanges($answer, $user, $comment),
            'archive' => $service->archive($answer, $user),
            default => false,
        };

        if ($ok) {
            Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Project updated.'));
        } else {
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'You cannot update this project.'));
        }

        return $this->redirect(Url::toProject($form, $answer));
    }
}
