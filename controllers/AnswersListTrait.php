<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\services\AnswerListService;
use humhub\modules\thiscoveryForms\services\ErasureService;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

trait AnswersListTrait
{
    abstract protected function findForm($id): CustomForm;

    public function actionAnswers($id)
    {
        $form = $this->findForm($id);
        if (!$form->canViewAnswers()) {
            throw new ForbiddenHttpException();
        }

        [$provider, $filters] = AnswerListService::provider($form, Yii::$app->request->queryParams);

        $container = property_exists($this, 'contentContainer') ? $this->contentContainer : null;

        return $this->render('@thiscovery-forms/views/form/answers', [
            'formModel' => $form,
            'dataProvider' => $provider,
            'filters' => $filters,
            'contentContainer' => $container,
            'selectedAnswerId' => (int)Yii::$app->request->get('answer', 0),
        ]);
    }

    public function actionAnswerDetail($id, $answerId)
    {
        $form = $this->findForm($id);
        if (!$form->canViewAnswers()) {
            throw new ForbiddenHttpException();
        }

        $answer = AnswerListService::findAnswer($form, (int)$answerId);
        if (!$answer) {
            throw new NotFoundHttpException();
        }

        $html = $this->renderPartial('@thiscovery-forms/views/form/_answer_detail', [
            'formModel' => $form,
            'answer' => $answer,
            'canManage' => $form->canManage(),
            'canDecideAnalysis' => $form->canDecideAnalysis(),
        ]);

        return $this->asJson([
            'success' => true,
            'html' => $html,
        ]);
    }

    /**
     * Erase one response (GOV-4): pseudonymise keeps the answers for research and removes the
     * identity; delete removes the response altogether. Managers only, POST, with a reason.
     */
    public function actionAnswerErase($id, $answerId)
    {
        $form = $this->findForm($id);
        if (!$form->canManage()) {
            throw new ForbiddenHttpException();
        }
        if (!Yii::$app->request->isPost) {
            return $this->redirect(Url::toAnswers($form));
        }
        $answer = AnswerListService::findAnswer($form, (int)$answerId);
        if (!$answer) {
            throw new NotFoundHttpException();
        }
        $mode = (string)Yii::$app->request->post('mode', '');
        $reason = trim((string)Yii::$app->request->post('reason', ''));
        if (!in_array($mode, [ErasureService::MODE_PSEUDONYMISE, ErasureService::MODE_DELETE], true) || $reason === '') {
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Choose how to erase the response and give a reason.'));
            return $this->redirect(Url::toAnswers($form, ['answer' => (int)$answer->id]));
        }
        $actor = Yii::$app->user->isGuest ? null : (int)Yii::$app->user->id;
        $service = new ErasureService();
        if ($mode === ErasureService::MODE_DELETE) {
            $service->deleteAnswer($answer, $actor, $reason);
            Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'The response was deleted.'));
            return $this->redirect(Url::toAnswers($form));
        }
        $service->pseudonymiseAnswer($answer, $actor, $reason);
        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'The response was kept without the person\'s identity.'));
        return $this->redirect(Url::toAnswers($form, ['answer' => (int)$answer->id]));
    }
}
