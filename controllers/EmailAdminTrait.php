<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\FormEmailTemplate;
use humhub\modules\thiscoveryForms\services\EmailTemplateService;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;

trait EmailAdminTrait
{
    abstract protected function requireManagePanels(): void;

    abstract protected function panelContainer();

    abstract protected function panelContainerId(): ?int;

    abstract protected function renderModuleView(string $view, array $params = []);

    public function actionEmailTemplates()
    {
        $this->requireManagePanels();
        $q = trim((string)Yii::$app->request->get('q', ''));
        $query = FormEmailTemplate::find()->orderBy(['title' => SORT_ASC]);
        $containerId = $this->panelContainerId();
        if ($containerId) {
            $query->andWhere(['contentcontainer_id' => $containerId]);
        } else {
            $query->andWhere(['contentcontainer_id' => null]);
        }
        if ($q !== '') {
            $query->andWhere([
                'or',
                ['like', 'title', $q],
                ['like', 'subject', $q],
            ]);
        }
        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 25],
            'sort' => [
                'defaultOrder' => ['title' => SORT_ASC],
                'attributes' => ['title', 'subject', 'updated_at', 'created_at', 'id'],
            ],
        ]);
        return $this->renderModuleView('email-template/index', [
            'dataProvider' => $provider,
            'filters' => ['q' => $q],
        ]);
    }

    public function actionEmailTemplateEdit($id = null)
    {
        $this->requireManagePanels();
        if ($this instanceof AdminController && !Yii::$app->request->isPost) {
            return $this->redirect(Url::toEmailTemplateEdit($this->panelContainer(), $id ? (int)$id : null));
        }
        $service = new EmailTemplateService();
        if ($id) {
            $template = $service->findOwned($id, $this->panelContainerId());
            if (!$template) {
                throw new NotFoundHttpException();
            }
            $isNew = false;
        } else {
            $template = new FormEmailTemplate([
                'contentcontainer_id' => $this->panelContainerId(),
            ]);
            $isNew = true;
        }

        if (Yii::$app->request->isPost) {
            $posted = Yii::$app->request->post();
            if (isset($posted['FormEmailTemplate']) && is_array($posted['FormEmailTemplate'])) {
                $posted['FormEmailTemplate'] = FormEmailTemplate::decodePostedFields($posted['FormEmailTemplate']);
            }
            $template->load($posted);
            $template->contentcontainer_id = $this->panelContainerId();
            $template->title = trim((string)$template->title);
            $template->subject = trim((string)$template->subject);
            if ($template->save()) {
                Yii::$app->session->setFlash('success', $isNew
                    ? Yii::t('ThiscoveryFormsModule.base', 'Email template created.')
                    : Yii::t('ThiscoveryFormsModule.base', 'Email template saved.'));
                return $this->redirect(Url::toEmailTemplateIndex($this->panelContainer()));
            }
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Could not save the email template.'));
        }

        return $this->renderModuleView('email-template/edit', [
            'template' => $template,
            'isNew' => $isNew,
        ]);
    }

    public function actionEmailTemplateDelete($id)
    {
        $this->requireManagePanels();
        if ($this instanceof AdminController && !Yii::$app->request->isPost) {
            return $this->redirect(Url::toEmailTemplateDelete($id, $this->panelContainer()));
        }
        $template = (new EmailTemplateService())->findOwned($id, $this->panelContainerId());
        if (!$template) {
            throw new NotFoundHttpException();
        }
        if (!Yii::$app->request->isPost) {
            return $this->redirect(Url::toEmailTemplateIndex($this->panelContainer()));
        }
        $template->delete();
        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Email template deleted.'));
        return $this->redirect(Url::toEmailTemplateIndex($this->panelContainer()));
    }
}
