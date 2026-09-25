<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAccessToken;
use humhub\modules\thiscoveryForms\models\FormIntegrityMeta;
use humhub\modules\thiscoveryForms\services\integrity\IntegrityService;
use humhub\modules\thiscoveryForms\services\integrity\IntegritySettings;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

trait IntegrityTrait
{
    abstract protected function findForm($id): CustomForm;

    public function actionIntegrity($id)
    {
        $form = $this->findForm($id);
        if (!$form->canViewAnswers()) {
            throw new ForbiddenHttpException();
        }
        $stats = (new IntegrityService())->dashboard($form);
        $container = property_exists($this, 'contentContainer') ? $this->contentContainer : null;
        return $this->render('@thiscovery-forms/views/form/integrity-dashboard', [
            'formModel' => $form,
            'stats' => $stats,
            'contentContainer' => $container,
            'canManage' => $form->canManage(),
        ]);
    }

    public function actionIntegrityStatus($id, $answerId)
    {
        $form = $this->findForm($id);
        if (!$form->canDecideAnalysis()) {
            throw new ForbiddenHttpException();
        }
        if (!Yii::$app->request->isPost) {
            throw new ForbiddenHttpException();
        }
        $meta = FormIntegrityMeta::findOne(['answer_id' => (int)$answerId, 'form_id' => $form->id]);
        if (!$meta) {
            throw new NotFoundHttpException();
        }
        $analysis = (string)Yii::$app->request->post('analysis_status', '');
        $status = $form->canManage()
            ? (string)Yii::$app->request->post('integrity_status', '')
            : $meta->getEffectiveStatus();
        if (!isset(FormIntegrityMeta::statusLabels()[$status]) || !isset(FormIntegrityMeta::analysisLabels()[$analysis])) {
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Invalid status.'));
            return $this->redirect(Url::toAnswers($form, ['answer' => $answerId]));
        }
        $reason = trim((string)Yii::$app->request->post('reason', ''));
        if ($analysis === FormIntegrityMeta::ANALYSIS_EXCLUDED && $reason === '') {
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Give a reason when excluding a response from analysis.'));
            return $this->redirect(Url::toAnswers($form, ['answer' => $answerId]));
        }
        (new IntegrityService())->overrideStatus($form, $meta, $status, $analysis, $reason);
        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Analysis decision saved.'));
        return $this->redirect(Url::toAnswers($form, ['answer' => $answerId]));
    }

    public function actionIntegrityNote($id, $answerId)
    {
        $form = $this->findForm($id);
        if (!$form->canDecideAnalysis()) {
            throw new ForbiddenHttpException();
        }
        if (!Yii::$app->request->isPost) {
            throw new ForbiddenHttpException();
        }
        $meta = FormIntegrityMeta::findOne(['answer_id' => (int)$answerId, 'form_id' => $form->id]);
        if (!$meta) {
            throw new NotFoundHttpException();
        }
        (new IntegrityService())->addNote($meta, (string)Yii::$app->request->post('note', ''));
        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Note saved.'));
        return $this->redirect(Url::toAnswers($form, ['answer' => $answerId]));
    }

    public function actionAccessTokens($id)
    {
        $form = $this->findForm($id);
        if (!$form->canManage()) {
            throw new ForbiddenHttpException();
        }
        $service = new IntegrityService();
        if (Yii::$app->request->isPost) {
            $revoke = (int)Yii::$app->request->post('revoke', 0);
            if ($revoke) {
                $row = FormAccessToken::findOne(['id' => $revoke, 'form_id' => $form->id]);
                if ($row) {
                    $row->delete();
                }
                Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Invitation link removed.'));
                return $this->redirect(Url::toAccessTokens($form));
            }
            $count = (int)Yii::$app->request->post('count', 5);
            $oneTime = Yii::$app->request->post('one_time', '1') === '1';
            $label = trim((string)Yii::$app->request->post('label', ''));
            $expiresDays = (int)Yii::$app->request->post('expires_days', 0);
            $created = $service->generateAccessTokens(
                $form,
                $count,
                $oneTime,
                $label !== '' ? $label : null,
                $expiresDays > 0 ? $expiresDays : null
            );
            $lines = [];
            foreach ($created['plaintext'] as $raw) {
                $lines[] = Url::toUniqueInvite($form, $raw);
            }
            Yii::$app->response->format = Response::FORMAT_RAW;
            Yii::$app->response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
            Yii::$app->response->headers->set('Content-Disposition', 'attachment; filename="survey-invites-' . $form->id . '.csv"');
            return "link\n" . implode("\n", $lines) . "\n";
        }
        $tokens = FormAccessToken::find()->where(['form_id' => $form->id])->orderBy(['id' => SORT_DESC])->all();
        return $this->render('@thiscovery-forms/views/form/access-tokens', [
            'formModel' => $form,
            'tokens' => $tokens,
            'settings' => IntegritySettings::forForm($form),
        ]);
    }
}
