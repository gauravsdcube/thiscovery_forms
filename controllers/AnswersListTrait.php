<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\services\AnswerListService;
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
}
