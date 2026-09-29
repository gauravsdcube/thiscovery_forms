<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\services\QuotaService;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class QuotaController extends ContentContainerController
{
    public function actionIndex($id)
    {
        $form = $this->findForm($id);
        $this->requireManage($form);
        $svc = new QuotaService();
        if (Yii::$app->request->isPost) {
            $quotaId = (int)Yii::$app->request->post('quota_id', 0);
            if (Yii::$app->request->post('close')) {
                $svc->setStatus($form, $quotaId, 'closed');
            } elseif (Yii::$app->request->post('open')) {
                $svc->setStatus($form, $quotaId, 'open');
            } elseif (Yii::$app->request->post('target') !== null) {
                $saved = $svc->saveQuota($form, $quotaId, [
                    'name' => (string)(Yii::$app->request->post('name') ?: ($svc->quota($quotaId, (int)$form->id)['name'] ?? '')),
                    'target' => Yii::$app->request->post('target'),
                    'reason' => Yii::$app->request->post('reason', ''),
                    'actor_id' => (int)Yii::$app->user->id ?: null,
                    'rules' => $svc->quota($quotaId, (int)$form->id)['rules_json'] ?? [],
                    'count_policy' => $svc->quota($quotaId, (int)$form->id)['count_policy'] ?? 'complete',
                    'reserve' => $svc->quota($quotaId, (int)$form->id)['reserve'] ?? 0,
                    'reserve_minutes' => $svc->quota($quotaId, (int)$form->id)['reserve_minutes'] ?? 60,
                    'action' => $svc->quota($quotaId, (int)$form->id)['action'] ?? 'end',
                    'action_message' => $svc->quota($quotaId, (int)$form->id)['action_message'] ?? '',
                    'action_url' => $svc->quota($quotaId, (int)$form->id)['action_url'] ?? '',
                    'action_page_key' => $svc->quota($quotaId, (int)$form->id)['action_page_key'] ?? '',
                    'check_page_key' => $svc->quota($quotaId, (int)$form->id)['check_page_key'] ?? '',
                    'status' => $svc->quota($quotaId, (int)$form->id)['status'] ?? 'open',
                    'parent_id' => $svc->quota($quotaId, (int)$form->id)['parent_id'] ?? null,
                    'wave_id' => $svc->quota($quotaId, (int)$form->id)['wave_id'] ?? null,
                ]);
                if (!$saved) {
                    Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'A target change needs a reason.'));
                }
            }
            return $this->redirect($form->content->container->createUrl('/thiscovery-forms/quota/index', ['id' => $form->id]));
        }
        return $this->render('index', [
            'formModel' => $form,
            'rows' => $svc->summary($form),
        ]);
    }

    public function actionEdit($id, $quotaId = null)
    {
        $form = $this->findForm($id);
        $this->requireManage($form);
        $svc = new QuotaService();
        $quota = null;
        if ($quotaId) {
            $quota = $svc->quota((int)$quotaId, (int)$form->id);
            if (!$quota) {
                throw new NotFoundHttpException();
            }
        }
        if (Yii::$app->request->isPost) {
            $host = trim((string)Yii::$app->request->post('host', ''));
            if ($host !== '' && Yii::$app->request->post('rules_json', null) === null) {
                $svc->addHost($form, $host);
                return $this->redirect($form->content->container->createUrl('/thiscovery-forms/quota/edit', [
                    'id' => $form->id,
                    'quotaId' => $quotaId,
                ]));
            }
            if ($host !== '') {
                $svc->addHost($form, $host);
            }
            $saved = $svc->saveQuota($form, $quota ? (int)$quota['id'] : null, [
                'name' => Yii::$app->request->post('name'),
                'target' => Yii::$app->request->post('target'),
                'reason' => Yii::$app->request->post('reason', ''),
                'actor_id' => (int)Yii::$app->user->id ?: null,
                'wave_id' => Yii::$app->request->post('wave_id'),
                'parent_id' => Yii::$app->request->post('parent_id'),
                'rules' => Yii::$app->request->post('rules_json', ''),
                'count_policy' => Yii::$app->request->post('count_policy', 'complete'),
                'reserve' => Yii::$app->request->post('reserve', 0),
                'reserve_minutes' => Yii::$app->request->post('reserve_minutes', 60),
                'action' => Yii::$app->request->post('action', 'end'),
                'action_message' => Yii::$app->request->post('action_message', ''),
                'action_url' => Yii::$app->request->post('action_url', ''),
                'action_page_key' => Yii::$app->request->post('action_page_key', ''),
                'check_page_key' => Yii::$app->request->post('check_page_key', ''),
                'status' => Yii::$app->request->post('status', 'open'),
                'sort_order' => Yii::$app->request->post('sort_order', 0),
            ]);
            if (!$saved) {
                Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'The quota could not be saved. A target change needs a reason.'));
                return $this->redirect($form->content->container->createUrl('/thiscovery-forms/quota/edit', [
                    'id' => $form->id,
                    'quotaId' => $quotaId,
                ]));
            }
            $errors = $svc->authoringErrors($form);
            $mine = array_values(array_filter($errors, static fn($message) => str_contains($message, (string)$saved['name'])));
            if ($mine) {
                Yii::$app->session->setFlash('error', implode(' ', $mine));
            }
            return $this->redirect($form->content->container->createUrl('/thiscovery-forms/quota/index', ['id' => $form->id]));
        }
        return $this->render('edit', [
            'formModel' => $form,
            'quota' => $quota,
            'quotas' => $svc->quotas((int)$form->id),
        ]);
    }

    protected function findForm($id): CustomForm
    {
        $form = CustomForm::find()->contentContainer($this->contentContainer)->andWhere(['custom_form.id' => $id])->one();
        if (!$form) {
            throw new NotFoundHttpException();
        }
        return $form;
    }

    protected function requireManage(CustomForm $form): void
    {
        if (!$form->canManage()) {
            throw new ForbiddenHttpException();
        }
    }
}
