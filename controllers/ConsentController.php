<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\permissions\ViewConsentRecords;
use humhub\modules\thiscoveryForms\services\ConsentService;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class ConsentController extends ContentContainerController
{
    protected function getAccessRules()
    {
        return [
            ['guestAccess' => ['withdraw']],
        ];
    }

    public function actionIndex($id)
    {
        $form = $this->findForm($id);
        $this->requireManage($form);
        $svc = new ConsentService();
        return $this->render('index', [
            'formModel' => $form,
            'documents' => $svc->documents((int)$form->id),
            'records' => $form->content->container->getPermissionManager()->can(ViewConsentRecords::class)
                ? $svc->records((int)$form->id)
                : [],
            'canViewRecords' => $form->content->container->getPermissionManager()->can(ViewConsentRecords::class),
        ]);
    }

    public function actionEdit($id, $documentId = null)
    {
        $form = $this->findForm($id);
        $this->requireManage($form);
        $svc = new ConsentService();
        $document = null;
        if ($documentId) {
            $document = $svc->document((int)$documentId, (int)$form->id);
            if (!$document) {
                throw new NotFoundHttpException();
            }
        }
        if (Yii::$app->request->isPost) {
            if (Yii::$app->request->post('new_version') && $document) {
                $next = $svc->newVersion($form, (int)$document['id']);
                return $this->redirect($form->content->container->createUrl('/thiscovery-forms/consent/edit', [
                    'id' => $form->id,
                    'documentId' => $next,
                ]));
            }
            $saved = $svc->saveDraft($form, $document ? (int)$document['id'] : null, [
                'title' => Yii::$app->request->post('title'),
                'body_html' => Yii::$app->request->post('body_html'),
                'approval_reference' => Yii::$app->request->post('approval_reference'),
                'effective_on' => Yii::$app->request->post('effective_on'),
                'items' => Yii::$app->request->post('items', []),
            ]);
            if ($saved && Yii::$app->request->post('publish')) {
                $errors = $svc->publish($form, (int)$saved['id']);
                if ($errors) {
                    Yii::$app->session->setFlash('error', implode(' ', $errors));
                }
            }
            return $this->redirect($form->content->container->createUrl('/thiscovery-forms/consent/index', ['id' => $form->id]));
        }
        return $this->render('edit', [
            'formModel' => $form,
            'document' => $document,
            'items' => $document ? $svc->items((int)$document['id']) : [],
        ]);
    }

    public function actionCertificate($id, $recordId)
    {
        $form = $this->findForm($id);
        if (!$form->content->container->getPermissionManager()->can(ViewConsentRecords::class)) {
            throw new ForbiddenHttpException();
        }
        $svc = new ConsentService();
        $record = null;
        foreach ($svc->records((int)$form->id) as $row) {
            if ((int)$row['id'] === (int)$recordId) {
                $record = $row;
                break;
            }
        }
        if (!$record || $record['answer_id'] === null) {
            throw new NotFoundHttpException();
        }
        return $this->render('certificate', [
            'formModel' => $form,
            'html' => $svc->certificateHtml($form, $record),
        ]);
    }

    public function actionWithdraw($id)
    {
        $form = $this->findForm($id);
        $svc = new ConsentService();
        $done = false;
        if (Yii::$app->request->isPost) {
            $token = trim((string)Yii::$app->request->post('token', ''));
            $recordId = (int)Yii::$app->request->post('record_id', 0);
            $scope = (string)Yii::$app->request->post('scope', 'stop_contact');
            $reason = trim((string)Yii::$app->request->post('reason', ''));
            if ($token !== '') {
                $done = $svc->withdrawByToken($token, $scope, $reason);
            } elseif ($recordId > 0 && $form->canManage() && $form->content->container->getPermissionManager()->can(ViewConsentRecords::class)) {
                foreach ($svc->records((int)$form->id) as $row) {
                    if ((int)$row['id'] === $recordId) {
                        $svc->withdrawRecord($row, $scope, $reason, (int)Yii::$app->user->id ?: null);
                        $done = true;
                        break;
                    }
                }
            }
        }
        return $this->render('withdraw', [
            'formModel' => $form,
            'done' => $done,
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
