<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormFolder;
use humhub\modules\thiscoveryForms\services\FolderService;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

trait FolderTrait
{
    protected function folderContainer()
    {
        return property_exists($this, 'contentContainer') ? $this->contentContainer : null;
    }

    protected function findFolder($id): FormFolder
    {
        $folder = FormFolder::findForContainer($this->folderContainer())->andWhere(['id' => (int)$id])->one();
        if (!$folder) {
            throw new NotFoundHttpException();
        }
        return $folder;
    }

    protected function findMovableForm($id): CustomForm
    {
        $container = $this->folderContainer();
        if ($container) {
            $form = CustomForm::find()->contentContainer($container)->andWhere(['custom_form.id' => (int)$id])->one();
        } else {
            $form = CustomForm::find()->joinWith('content')
                ->andWhere(['custom_form.id' => (int)$id])
                ->andWhere(['content.contentcontainer_id' => null])
                ->one();
        }
        if (!$form) {
            throw new NotFoundHttpException();
        }
        return $form;
    }

    public function actionFolderEdit($id = null)
    {
        $container = $this->folderContainer();
        $parentId = (int)Yii::$app->request->get('parent', 0);
        if ($id) {
            $folder = $this->findFolder($id);
            if (!FolderService::canManage($folder)) {
                throw new ForbiddenHttpException();
            }
        } else {
            $parent = $parentId ? $this->findFolder($parentId) : null;
            if ($parent && !FolderService::canManage($parent) && !FolderService::isFolderAdmin($container)) {
                throw new ForbiddenHttpException();
            }
            if (!$parent && !FolderService::isFolderAdmin($container) && !FolderService::canCreateFolders($container)) {
                throw new ForbiddenHttpException();
            }
            $folder = new FormFolder([
                'contentcontainer_id' => $container ? (int)$container->contentcontainer_id : null,
                'parent_id' => $parent ? (int)$parent->id : null,
                'inherit_acl' => $parent ? 1 : 1,
            ]);
        }

        $request = Yii::$app->request;
        if ($request->isPost) {
            $folder->load($request->post());
            $folder->contentcontainer_id = $container ? (int)$container->contentcontainer_id : null;
            if (!isset($request->post('FormFolder')['inherit_acl'])) {
                $folder->inherit_acl = 0;
            }
            if ($folder->save()) {
                if (!(int)$folder->inherit_acl) {
                    FolderService::saveAcl($folder, $request->post('acl', []));
                } else {
                    FolderService::saveAcl($folder, []);
                }
                Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Folder saved.'));
                return $this->redirect(Url::toManageIndex($container, ['folder' => $folder->id]));
            }
        }

        $aclMap = [];
        foreach ($folder->acls as $acl) {
            if ($acl->group_id) {
                $aclMap[(int)$acl->group_id] = $acl;
            }
        }

        return $this->render('@thiscovery-forms/views/form/folder_edit', [
            'folder' => $folder,
            'isNew' => $folder->isNewRecord,
            'contentContainer' => $container,
            'parentOptions' => FolderService::treeOptions($container, null, FolderService::ACCESS_MANAGE, $folder->isNewRecord ? null : (int)$folder->id),
            'groups' => FolderService::permissionGroups(),
            'aclMap' => $aclMap,
        ]);
    }

    public function actionFolderDelete($id)
    {
        if (!Yii::$app->request->isPost) {
            throw new ForbiddenHttpException();
        }
        $folder = $this->findFolder($id);
        if (!FolderService::canManage($folder) && !FolderService::isFolderAdmin($this->folderContainer())) {
            throw new ForbiddenHttpException();
        }
        $parentId = (int)$folder->parent_id;
        $folder->delete();
        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Folder deleted. Forms in it were moved to Unfiled.'));
        return $this->redirect(Url::toManageIndex($this->folderContainer(), $parentId ? ['folder' => $parentId] : []));
    }

    public function actionMoveForm($id)
    {
        if (!Yii::$app->request->isPost) {
            throw new ForbiddenHttpException();
        }
        $form = $this->findMovableForm($id);
        if (!$form->canManage()) {
            throw new ForbiddenHttpException();
        }
        $folderId = (int)Yii::$app->request->post('folder_id', 0);
        $container = $this->folderContainer();
        if ($folderId > 0) {
            $folder = $this->findFolder($folderId);
            if (!FolderService::canCreateIn($folder) && !FolderService::canManage($folder)) {
                throw new ForbiddenHttpException();
            }
            $form->folder_id = (int)$folder->id;
        } else {
            $form->folder_id = null;
        }
        Yii::$app->db->createCommand()->update('custom_form', [
            'folder_id' => $folderId > 0 ? $folderId : null,
        ], ['id' => (int)$form->id])->execute();
        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Form moved.'));
        return $this->redirect(Url::toManageIndex($container, $folderId > 0 ? ['folder' => $folderId] : []));
    }
}
