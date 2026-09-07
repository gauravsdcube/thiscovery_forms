<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\services\FormVersionAdapter;
use humhub\modules\thiscoveryForms\services\FormVersionService;
use humhub\modules\thiscoveryVersioning\services\VersioningService;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Studio versioning actions (publish / restore / delete).
 */
trait VersioningTrait
{
    public function actionPublishVersion($id)
    {
        $form = $this->findForm($id);
        $this->assertVersionManage($form);
        if (!FormVersionService::isAvailable()) {
            throw new NotFoundHttpException();
        }

        $revisionId = (int)Yii::$app->request->post('revision_id', 0) ?: null;
        try {
            $edition = (new FormVersionService())->publish($form, $revisionId);
            Yii::$app->session->setFlash(
                'success',
                Yii::t(
                    'ThiscoveryFormsModule.base',
                    'Published edition #{n}.',
                    ['n' => $edition ? $edition->edition_number : '?']
                )
            );
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(Url::toEdit($form) . '?tab=versions');
    }

    public function actionRestoreVersion($id)
    {
        $form = $this->findForm($id);
        $this->assertVersionManage($form);
        if (!FormVersionService::isAvailable()) {
            throw new NotFoundHttpException();
        }

        $revisionId = (int)Yii::$app->request->post('revision_id', 0);
        try {
            (new VersioningService())->restoreRevision(
                FormVersionAdapter::OWNER_TYPE,
                (int)$form->id,
                $revisionId
            );
            Yii::$app->session->setFlash(
                'success',
                Yii::t('ThiscoveryFormsModule.base', 'Working draft restored. Publish when you want participants to use it.')
            );
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(Url::toEdit($form) . '?tab=versions');
    }

    public function actionDeleteRevision($id)
    {
        $form = $this->findForm($id);
        $this->assertVersionManage($form);
        if (!FormVersionService::isAvailable()) {
            throw new NotFoundHttpException();
        }

        $revisionId = (int)Yii::$app->request->post('revision_id', 0);
        try {
            (new VersioningService())->deleteRevision(
                FormVersionAdapter::OWNER_TYPE,
                (int)$form->id,
                $revisionId
            );
            Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Revision deleted.'));
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(Url::toEdit($form) . '?tab=versions');
    }

    public function actionDeleteEdition($id)
    {
        $form = $this->findForm($id);
        $this->assertVersionManage($form);
        if (!FormVersionService::isAvailable()) {
            throw new NotFoundHttpException();
        }

        $editionId = (int)Yii::$app->request->post('edition_id', 0);
        try {
            (new VersioningService())->deleteEdition(
                FormVersionAdapter::OWNER_TYPE,
                (int)$form->id,
                $editionId
            );
            Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Edition deleted.'));
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(Url::toEdit($form) . '?tab=versions');
    }

    protected function assertVersionManage(CustomForm $form): void
    {
        if (!$form->canManage()) {
            throw new ForbiddenHttpException();
        }
        if (!Yii::$app->request->isPost) {
            throw new ForbiddenHttpException();
        }
    }
}
