<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\file\libs\FileHelper;
use humhub\modules\file\models\File;
use humhub\modules\file\models\FileUpload;
use humhub\modules\file\libs\ImageHelper;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\UatSubmission;
use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\services\UatCatalogService;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\Html;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Public UAT result form + admin review of submissions.
 */
trait UatTrait
{
    /**
     * Public tester form (guests allowed).
     */
    public function actionUatForm()
    {
        $model = new UatSubmission();
        $model->kind = UatSubmission::KIND_RESULT;
        $model->environment_url = Yii::$app->request->hostInfo . Yii::$app->request->baseUrl;
        /** @var Module $module */
        $module = Yii::$app->getModule('thiscovery-forms');
        $model->module_version = $module->getVersion();

        $prefillId = trim((string)Yii::$app->request->get('test_id', ''));
        if ($prefillId !== '') {
            $cat = UatCatalogService::find($prefillId);
            if ($cat) {
                $model->test_id = $cat['id'];
                $this->applyCatalogToModel($model, $cat);
            }
        }

        if (!Yii::$app->user->isGuest && Yii::$app->user->identity) {
            $model->tester_name = Yii::$app->user->identity->displayName;
            $model->tester_email = Yii::$app->user->identity->email;
        }

        if (Yii::$app->request->isPost) {
            $model->load(Yii::$app->request->post());
            $model->evidenceUpload = UploadedFile::getInstance($model, 'evidenceUpload');

            if ($model->kind === UatSubmission::KIND_RESULT && $model->test_id) {
                $cat = UatCatalogService::find((string)$model->test_id);
                if ($cat) {
                    $this->applyCatalogToModel($model, $cat);
                } else {
                    $model->addError('test_id', Yii::t('ThiscoveryFormsModule.base', 'Unknown Test ID.'));
                }
            }

            if (!$model->hasErrors() && $model->validate()) {
                try {
                    if ($model->save(false)) {
                        $this->storeEvidence($model);
                        Yii::$app->session->setFlash(
                            'success',
                            Yii::t('ThiscoveryFormsModule.base', 'Thank you — your UAT submission was recorded.')
                        );
                        return $this->redirect(Url::toUat(['done' => 1]));
                    }
                    Yii::error('UAT save returned false: ' . json_encode($model->getErrors()), 'thiscovery-forms');
                } catch (\Throwable $e) {
                    Yii::error('UAT save failed: ' . $e->getMessage(), 'thiscovery-forms');
                    $model->addError('kind', Yii::t('ThiscoveryFormsModule.base', 'Could not save the submission. Please try again or tell an administrator.'));
                }
            }
        }

        $container = method_exists($this, 'helpContainer') ? $this->helpContainer() : null;
        $catalog = UatCatalogService::all();
        $groupedOptions = [];
        foreach ($catalog as $row) {
            $feat = $row['feature'] !== '' ? $row['feature'] : 'Other';
            $groupedOptions[$feat][$row['id']] = $row['id'] . ' — ' . $row['scenario'];
        }

        return $this->render('@thiscovery-forms/views/uat/form', [
            'model' => $model,
            'catalog' => $catalog,
            'groupedOptions' => $groupedOptions,
            'scenarioCount' => count($catalog),
            'done' => (string)Yii::$app->request->get('done', '') === '1',
            'contentContainer' => $container,
        ]);
    }

    /**
     * JSON catalog lookup for the tester form.
     */
    public function actionUatCatalog($id = null)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $id = trim((string)($id ?? Yii::$app->request->get('id', '')));
        if ($id === '') {
            return ['ok' => true, 'items' => UatCatalogService::all()];
        }
        $row = UatCatalogService::find($id);
        if (!$row) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        return ['ok' => true, 'item' => $row];
    }

    /**
     * Public download of the UAT scenarios CSV (no login required).
     */
    public function actionUatCsv()
    {
        $path = UatCatalogService::csvPath();
        if (!is_readable($path)) {
            throw new NotFoundHttpException();
        }
        return Yii::$app->response->sendFile($path, 'uat-scenarios.csv', [
            'mimeType' => 'text/csv',
            'inline' => false,
        ]);
    }

    /**
     * Guest-safe evidence upload used by the UAT form (AJAX optional).
     */
    public function actionUatUpload()
    {
        $this->forcePostRequest();
        Yii::$app->response->format = Response::FORMAT_JSON;

        $files = [];
        foreach (UploadedFile::getInstancesByName('files') as $uploaded) {
            $file = new FileUpload();
            $file->setUploadedFile($uploaded);
            $file->show_in_stream = false;
            if (Yii::$app->user->isGuest) {
                $file->created_by = null;
                $file->updated_by = null;
            }
            if ($file->save()) {
                ImageHelper::downscaleImage($file);
                $files[] = array_merge(['error' => false], FileHelper::getFileInfos($file));
            } else {
                $errorMessage = $file->getErrors('uploadedFile')
                    ?: [Yii::t('ThiscoveryFormsModule.base', 'Could not upload the file.')];
                $files[] = [
                    'error' => true,
                    'errors' => $errorMessage,
                    'name' => Html::encode((string)$file->file_name),
                ];
            }
        }
        return ['files' => $files];
    }

    /**
     * Serve evidence attached to a UAT submission (reviewers only).
     */
    public function actionUatFile($guid)
    {
        if (!$this->canReviewUat()) {
            throw new ForbiddenHttpException();
        }
        $file = File::findOne(['guid' => $guid]);
        if (!$file) {
            throw new NotFoundHttpException();
        }
        $owned = UatSubmission::find()->where(['evidence_guid' => $guid])->exists();
        if (!$owned) {
            throw new ForbiddenHttpException();
        }
        $filePath = $file->store->get();
        if (!$filePath || !is_file($filePath)) {
            throw new NotFoundHttpException();
        }
        return Yii::$app->response->sendFile($filePath, $file->file_name, [
            'mimeType' => $file->mime_type ?: 'application/octet-stream',
            'inline' => false,
        ]);
    }

    /**
     * Admin / creator review of UAT submissions.
     */
    public function actionUatResults()
    {
        if (!$this->canReviewUat()) {
            throw new ForbiddenHttpException();
        }

        $query = UatSubmission::find()->orderBy(['created_at' => SORT_DESC]);
        $kind = trim((string)Yii::$app->request->get('kind', ''));
        $status = trim((string)Yii::$app->request->get('status', ''));
        $result = trim((string)Yii::$app->request->get('result', ''));
        if ($kind !== '') {
            $query->andWhere(['kind' => $kind]);
        }
        if ($status !== '') {
            $query->andWhere(['status' => $status]);
        }
        if ($result !== '') {
            $query->andWhere(['result' => $result]);
        }

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 40],
        ]);

        $container = method_exists($this, 'helpContainer') ? $this->helpContainer() : null;

        return $this->render('@thiscovery-forms/views/uat/results', [
            'dataProvider' => $provider,
            'kind' => $kind,
            'status' => $status,
            'result' => $result,
            'scenarioCount' => UatCatalogService::count(),
            'contentContainer' => $container,
        ]);
    }

    public function actionUatView($id)
    {
        if (!$this->canReviewUat()) {
            throw new ForbiddenHttpException();
        }
        $model = UatSubmission::findOne((int)$id);
        if (!$model) {
            throw new NotFoundHttpException();
        }

        $container = method_exists($this, 'helpContainer') ? $this->helpContainer() : null;

        if (Yii::$app->request->isPost) {
            $model->status = (string)Yii::$app->request->post('status', $model->status);
            $model->admin_notes = (string)Yii::$app->request->post('admin_notes', $model->admin_notes);
            if ($model->save(false)) {
                $this->view->saved();
                return $this->redirect(Url::toUatView($model->id, $container));
            }
        }

        return $this->render('@thiscovery-forms/views/uat/view', [
            'model' => $model,
            'contentContainer' => $container,
        ]);
    }

    protected function canReviewUat(): bool
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }
        if (method_exists($this, 'canViewHelp') && $this->canViewHelp()) {
            return true;
        }
        return Yii::$app->user->isAdmin();
    }

    /**
     * @param array<string, string> $cat
     */
    protected function applyCatalogToModel(UatSubmission $model, array $cat): void
    {
        $model->test_id = $cat['id'];
        $model->feature = $cat['feature'];
        $model->scenario = $cat['scenario'];
        $model->explanation = $cat['explanation'];
        $model->preconditions = $cat['preconditions'];
        $model->steps = $cat['steps'];
        $model->expected_behaviour = $cat['expected'];
        $model->priority = $cat['priority'];
        $model->roles = $cat['roles'];
    }

    protected function storeEvidence(UatSubmission $model): void
    {
        $guid = trim((string)Yii::$app->request->post('evidence_guid', ''));
        if ($guid !== '') {
            $file = File::findOne(['guid' => $guid]);
            if ($file) {
                $model->evidence_guid = $file->guid;
                $model->fileManager->attach($file->guid);
                $model->save(false, ['evidence_guid', 'updated_at', 'updated_by']);
                return;
            }
        }

        if ($model->evidenceUpload) {
            $file = new FileUpload();
            $file->setUploadedFile($model->evidenceUpload);
            $file->show_in_stream = false;
            if (Yii::$app->user->isGuest) {
                $file->created_by = null;
                $file->updated_by = null;
            }
            if ($file->save()) {
                ImageHelper::downscaleImage($file);
                $model->evidence_guid = $file->guid;
                $model->fileManager->attach($file->guid);
                $model->save(false, ['evidence_guid', 'updated_at', 'updated_by']);
            }
        }
    }
}
