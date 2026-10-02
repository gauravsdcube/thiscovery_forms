<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormLibraryItem;
use humhub\modules\thiscoveryForms\models\FormFolder;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\thiscoveryForms\notifications\FormAnsweredNotification;
use humhub\modules\thiscoveryForms\services\DashboardService;
use humhub\modules\thiscoveryForms\services\EmailTemplateService;
use humhub\modules\thiscoveryForms\services\FolderService;
use humhub\modules\thiscoveryForms\services\FormCloneService;
use humhub\modules\thiscoveryForms\services\QuestionImportExportService;
use Yii;
use yii\helpers\Html;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Shared create-wizard, templates, library, import/export, and JSON poll submit.
 */
trait StudioTrait
{
    abstract protected function findForm($id): CustomForm;

    abstract protected function handleEdit(CustomForm $form, bool $isNew, array $seedFields = []);

    abstract protected function prepareNewForm(): CustomForm;

    abstract protected function notifySubmission(CustomForm $form, FormAnswer $answer): void;

    /**
     * Field rows from the builder. Prefer the JSON payload so PHP max_input_vars
     * cannot truncate a long form.
     *
     * @param string|null $error Set when fields_json is present but invalid
     * @return array|null Null when the JSON payload cannot be read
     */
    protected function postedFieldRows(?string &$error = null): ?array
    {
        $json = Yii::$app->request->post('fields_json');
        if (is_string($json) && $json !== '') {
            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                $error = Yii::t('ThiscoveryFormsModule.base', 'Could not read the form fields. Try saving again.');
                return null;
            }
            return $decoded;
        }
        $rows = Yii::$app->request->post('fields', []);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Stay in the studio after save. Optionally reopen a tab, open the test preview, or publish.
     */
    protected function redirectAfterStudioSave(CustomForm $form)
    {
        $tab = trim((string)Yii::$app->request->post('studio_tab', ''));
        $after = (string)Yii::$app->request->post('after_save', 'stay');
        if ($after === 'preview') {
            return $this->redirect(Url::toPreview($form));
        }
        if ($after === 'publish') {
            $this->publishSavedDraft($form);
            $tab = 'versions';
        }
        $url = Url::toEdit($form);
        if ($tab !== '' && $tab !== 'builder') {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'tab=' . rawurlencode($tab);
            $section = trim((string)Yii::$app->request->post('studio_section', ''));
            if ($tab === 'settings' && $section !== '' && $section !== 'basics') {
                $url .= '&section=' . rawurlencode($section);
            }
        }
        return $this->redirect($url);
    }

    /**
     * Publish the revision created by the save that just ran (no duplicate snapshot).
     */
    protected function publishSavedDraft(CustomForm $form): void
    {
        if (!\humhub\modules\thiscoveryForms\services\FormVersionService::isAvailable() || !$form->id) {
            return;
        }
        try {
            $svc = new \humhub\modules\thiscoveryForms\services\FormVersionService();
            unset($form->fields);
            $form->refresh();
            $latest = $svc->versions()->latestRevision(
                \humhub\modules\thiscoveryForms\services\FormVersionAdapter::OWNER_TYPE,
                (int)$form->id
            );
            $edition = $svc->publish($form, $latest ? (int)$latest->id : null);
            if (!$edition) {
                return;
            }
            $publishedMessage = Yii::t(
                'ThiscoveryFormsModule.base',
                'Saved and published edition #{n}. Participants now see this version.',
                ['n' => $edition ? $edition->edition_number : '?']
            );
            $this->view->success($publishedMessage);
            Yii::$app->session->setFlash('success', $publishedMessage);
        } catch (\Throwable $e) {
            $this->view->error($e->getMessage());
            Yii::$app->session->setFlash('error', $e->getMessage());
        }
    }

    /**
     * Permanently remove a form (hard delete). ContentActiveRecord::delete() is only a
     * soft delete and would leave the form on the list.
     */
    /**
     * "Delete" moves the form to the trash (GOV-7). Nothing is lost until a manager deletes it
     * permanently from the trash.
     */
    protected function deleteManagedForm(CustomForm $form)
    {
        if (!$form->canManage()) {
            throw new ForbiddenHttpException();
        }
        if (!Yii::$app->request->isPost) {
            throw new HttpException(405);
        }
        $ok = false;
        try {
            $ok = $form->moveToTrash((string)Yii::$app->request->post('reason', ''));
        } catch (\Throwable $e) {
            Yii::error('Thiscovery Forms could not move form #' . $form->id . ' to the trash: ' . $e->getMessage(), 'thiscovery-forms');
        }
        Yii::$app->session->setFlash($ok ? 'success' : 'error', $ok
            ? Yii::t('ThiscoveryFormsModule.base', 'The form was moved to the trash. Its answers are kept, and it can be restored from the trash.')
            : Yii::t('ThiscoveryFormsModule.base', 'Could not delete the form.'));
        return $this->redirect(Url::toManageIndex($this->studioContainer()));
    }

    /** A form in this studio's trash. */
    protected function findTrashedForm($id): CustomForm
    {
        $query = CustomForm::find()->joinWith('content')
            ->andWhere(['custom_form.id' => (int)$id, 'content.state' => \humhub\modules\content\models\Content::STATE_DELETED]);
        $containerId = $this->studioContainerId();
        $query->andWhere(['content.contentcontainer_id' => $containerId]);
        $form = $query->one();
        if (!$form || !$form->canManage()) {
            throw new NotFoundHttpException();
        }
        return $form;
    }

    public function actionTrash()
    {
        $probe = $this->prepareNewForm();
        if (!$probe->canCreate() && !$probe->canManage()) {
            throw new ForbiddenHttpException();
        }
        $forms = CustomForm::find()->joinWith('content')
            ->andWhere(['content.state' => \humhub\modules\content\models\Content::STATE_DELETED])
            ->andWhere(['content.contentcontainer_id' => $this->studioContainerId()])
            ->andWhere(['custom_form.is_template' => 0])
            ->orderBy(['custom_form.id' => SORT_DESC])
            ->all();
        $forms = array_values(array_filter($forms, static fn(CustomForm $f) => $f->canManage()));
        return $this->render('@thiscovery-forms/views/form/trash', [
            'forms' => $forms,
            'contentContainer' => $this->studioContainer(),
        ]);
    }

    public function actionRestoreForm($id)
    {
        $this->forcePostRequest();
        $form = $this->findTrashedForm($id);
        $form->restoreFromTrash();
        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'The form was restored. It is closed: reopen it when you are ready.'));
        return $this->redirect(Url::toManageIndex($this->studioContainer()));
    }

    /**
     * Permanent delete, from the trash only, after typing the form's title (GOV-7).
     */
    public function actionPurgeForm($id)
    {
        $this->forcePostRequest();
        $form = $this->findTrashedForm($id);
        if (trim((string)Yii::$app->request->post('confirm_title', '')) !== trim((string)$form->title)) {
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Type the form title exactly to delete it permanently.'));
            return $this->redirect(Url::toTrash($this->studioContainer()));
        }
        $ok = false;
        try {
            $ok = $form->purge((string)Yii::$app->request->post('reason', ''));
        } catch (\Throwable $e) {
            Yii::error('Thiscovery Forms could not purge form #' . $form->id . ': ' . $e->getMessage(), 'thiscovery-forms');
        }
        if ($ok) {
            Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'The form and its answers were deleted permanently.'));
        } else {
            Yii::$app->session->setFlash('error', $form->hasErrors('id') ? implode(' ', $form->getErrors('id')) : Yii::t('ThiscoveryFormsModule.base', 'Could not delete the form.'));
        }
        return $this->redirect(Url::toTrash($this->studioContainer()));
    }

    protected function studioContainer()
    {
        return property_exists($this, 'contentContainer') ? $this->contentContainer : null;
    }

    protected function studioContainerId(): ?int
    {
        $container = $this->studioContainer();
        return $container ? (int)$container->contentcontainer_id : null;
    }

    public function actionCreate()
    {
        $form = $this->prepareNewForm();
        if (!$form->canCreate()) {
            throw new ForbiddenHttpException();
        }

        $templateId = (int)Yii::$app->request->get('template', 0);
        if ($templateId) {
            return $this->createFromTemplateId($templateId);
        }

        $kind = (string)Yii::$app->request->get('kind', '');
        if ($kind === '' || !isset(CustomForm::getKindLabels()[$kind])) {
            return $this->renderCreateWizard($form);
        }
        if (!CustomForm::isKindEnabled($kind)) {
            throw new ForbiddenHttpException(Yii::t(
                'ThiscoveryFormsModule.base',
                'This form type is disabled by an administrator.'
            ));
        }

        $form->kind = $kind;
        $form->status = CustomForm::STATUS_DRAFT;
        $form->answers_visibility = CustomForm::ANSWERS_MANAGERS;
        $form->allow_edit = 1;
        $form->applyKindDefaults();
        $this->applyCreateFolder($form);

        $seed = [];
        $starter = (string)Yii::$app->request->get('starter', '');
        if ($form->isEq5d()) {
            $seed = CustomForm::seedHealthStatusFields();
        } elseif ($starter === 'health-status' && !$form->isPoll()) {
            $seed = CustomForm::seedHealthStatusFields();
        } elseif ($form->isPoll()) {
            $seed = CustomForm::seedPollFields();
        } elseif ($form->isFeedback()) {
            $seed = CustomForm::seedFeedbackFields();
        }

        return $this->handleEdit($form, true, $seed);
    }

    protected function renderCreateWizard(CustomForm $form)
    {
        $container = $this->studioContainer();
        // Global (Administration) create wizard keeps the admin left menu,
        // unless configuration opens the whole Forms area full page.
        if ($container === null && !\humhub\modules\thiscoveryForms\Module::opensWithoutAdminMenu()) {
            $this->subLayout = '@humhub/modules/admin/views/layouts/main';
        }
        $templates = CustomForm::findAvailableTemplates($container);

        return $this->render('@thiscovery-forms/views/form/create', [
            'formModel' => $form,
            'contentContainer' => $container,
            'templates' => $templates,
            'folderId' => (int)Yii::$app->request->get('folder', 0),
        ]);
    }

    protected function applyCreateFolder(CustomForm $form): void
    {
        $folderId = (int)Yii::$app->request->get('folder', 0);
        if ($folderId < 1) {
            return;
        }
        $folder = FormFolder::findForContainer($this->studioContainer())->andWhere(['id' => $folderId])->one();
        if (!$folder) {
            return;
        }
        if (!FolderService::canCreateIn($folder) && !FolderService::canManage($folder) && !FolderService::isFolderAdmin($this->studioContainer())) {
            return;
        }
        $form->folder_id = (int)$folder->id;
    }

    protected function createFromTemplateId(int $templateId)
    {
        $template = CustomForm::findOne($templateId);
        if (!$template || !$template->isTemplate()) {
            throw new NotFoundHttpException();
        }
        if (!CustomForm::isKindEnabled((string)$template->kind)) {
            throw new ForbiddenHttpException(Yii::t(
                'ThiscoveryFormsModule.base',
                'This form type is disabled by an administrator.'
            ));
        }
        if (!$template->canManage() && !$this->prepareNewForm()->canCreate()) {
            throw new ForbiddenHttpException();
        }

        $clone = (new FormCloneService())->createFromTemplate($template, $this->studioContainer());
        if (!$clone) {
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Could not create a form from that template.'));
            return $this->redirect(Url::toCreate($this->studioContainer()));
        }
        $this->applyCreateFolder($clone);
        if ($clone->folder_id) {
            $clone->save(false, ['folder_id']);
        }

        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Form created from template. Review and save.'));
        return $this->redirect(Url::toEdit($clone));
    }

    public function actionSaveTemplate($id)
    {
        $form = $this->findForm($id);
        if (!$form->canManage()) {
            throw new ForbiddenHttpException();
        }
        if (!Yii::$app->request->isPost) {
            return $this->redirect(Url::toEdit($form));
        }

        $clone = (new FormCloneService())->saveAsTemplate($form);
        if (!$clone) {
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Could not save the template.'));
            return $this->redirect(Url::toEdit($form));
        }

        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Saved as template.'));
        return $this->redirect(Url::toEdit($clone));
    }

    public function actionRegenerateFillToken($id)
    {
        $form = $this->findForm($id);
        if (!$form->canManage()) {
            throw new ForbiddenHttpException();
        }
        if (Yii::$app->request->isPost) {
            $form->rotateFillToken();
            Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'A new fill link was created. The previous link no longer works.'));
        }
        return $this->redirect(Url::toEdit($form) . '?tab=share');
    }

    public function actionRegeneratePreview($id)
    {
        $form = $this->findForm($id);
        if (!$form->canManage()) {
            throw new ForbiddenHttpException();
        }
        if (Yii::$app->request->isPost) {
            $form->rotateTestToken();
            Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'A new preview link was created. The previous link no longer works.'));
        }
        return $this->redirect(Url::toEdit($form) . '?tab=share');
    }

    public function actionRegenerateDashboardShare($id)
    {
        $form = $this->findForm($id);
        if (!$form->canManage()) {
            throw new ForbiddenHttpException();
        }
        if (Yii::$app->request->isPost) {
            $form->rotatePublicDashboardToken();
            Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'A new dashboard share link was created. The previous link no longer works.'));
        }
        return $this->redirect(Url::toEdit($form) . '?tab=share');
    }

    public function actionExportQuestions($id)
    {
        $form = $this->findForm($id);
        if (!$form->canManage()) {
            throw new ForbiddenHttpException();
        }

        $format = strtolower((string)Yii::$app->request->get('format', 'json'));
        $service = new QuestionImportExportService();

        Yii::$app->response->format = Response::FORMAT_RAW;
        if ($format === 'csv') {
            $filename = 'questions-' . $form->id . '.csv';
            Yii::$app->response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
            Yii::$app->response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            return "\xEF\xBB\xBF" . $service->exportCsv($form);
        }

        $filename = 'questions-' . $form->id . '.json';
        Yii::$app->response->headers->set('Content-Type', 'application/json; charset=UTF-8');
        Yii::$app->response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        return $service->exportJsonString($form);
    }

    public function actionSampleQuestions()
    {
        $format = strtolower((string)Yii::$app->request->get('format', 'json'));
        $path = QuestionImportExportService::sampleFilePath($format);
        if ($path === null) {
            throw new NotFoundHttpException();
        }

        $isCsv = $format === 'csv';
        $filename = 'thiscovery-forms-questions-sample.' . ($isCsv ? 'csv' : 'json');
        $mime = $isCsv ? 'text/csv; charset=UTF-8' : 'application/json; charset=UTF-8';

        return Yii::$app->response->sendFile($path, $filename, [
            'mimeType' => $mime,
            'inline' => false,
        ]);
    }

    public function actionImportQuestions($id)
    {
        $form = $this->findForm($id);
        if (!$form->canManage()) {
            throw new ForbiddenHttpException();
        }
        $shareUrl = Url::toEdit($form, ['tab' => 'settings', 'section' => 'share']);
        if (!Yii::$app->request->isPost) {
            return $this->redirect($shareUrl);
        }

        $upload = UploadedFile::getInstanceByName('import_file');
        if (!$upload || $upload->hasError) {
            $msg = Yii::t('ThiscoveryFormsModule.base', 'Please choose a JSON or CSV file to import.');
            $this->view->error($msg);
            Yii::$app->session->setFlash('cf_import_notice', ['type' => 'error', 'message' => $msg]);
            return $this->redirect($shareUrl);
        }

        $raw = @file_get_contents($upload->tempName);
        if ($raw === false || $raw === '') {
            $msg = Yii::t('ThiscoveryFormsModule.base', 'Could not read the uploaded file.');
            $this->view->error($msg);
            Yii::$app->session->setFlash('cf_import_notice', ['type' => 'error', 'message' => $msg]);
            return $this->redirect($shareUrl);
        }

        $service = new QuestionImportExportService();
        $ext = strtolower((string)$upload->extension);
        $replace = (string)Yii::$app->request->post('replace_fields', '') === '1';
        $error = ($ext === 'csv')
            ? $service->importCsv($form, $raw, $replace)
            : $service->importJson($form, $raw, $replace);

        if ($error) {
            $this->view->error($error);
            Yii::$app->session->setFlash('cf_import_notice', ['type' => 'error', 'message' => $error]);
        } else {
            $msg = $replace
                ? Yii::t('ThiscoveryFormsModule.base', 'Existing questions were replaced with the imported file.')
                : Yii::t('ThiscoveryFormsModule.base', 'Questions imported.');
            $this->view->success($msg);
            Yii::$app->session->setFlash('cf_import_notice', ['type' => 'success', 'message' => $msg]);
        }

        return $this->redirect($shareUrl);
    }

    public function actionLibraryList()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $probe = $this->prepareNewForm();
        if (!$probe->canCreate() && !$probe->canManage()) {
            throw new ForbiddenHttpException();
        }

        $items = [];
        foreach (FormLibraryItem::findAvailable($this->studioContainerId()) as $item) {
            $items[] = [
                'id' => (int)$item->id,
                'type' => $item->type,
                'title' => $item->title,
                'global' => $item->isGlobal(),
                'canManage' => $item->canManage(),
                'fieldCount' => count($item->getFieldRows()),
            ];
        }

        return ['items' => $items];
    }

    public function actionLibrarySave()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return ['success' => false, 'error' => 'Method not allowed'];
        }

        $probe = $this->prepareNewForm();
        if (!$probe->canCreate()) {
            throw new ForbiddenHttpException();
        }

        $title = trim((string)Yii::$app->request->post('title', ''));
        $type = (string)Yii::$app->request->post('type', FormLibraryItem::TYPE_QUESTION);
        $fields = Yii::$app->request->post('fields', []);
        if (!is_array($fields)) {
            $fields = [];
        }
        $keys = Yii::$app->request->post('keys', []);
        if (is_array($keys) && $keys) {
            $keys = array_map('strval', $keys);
            $fields = array_filter($fields, static fn($_, $k) => in_array((string)$k, $keys, true), ARRAY_FILTER_USE_BOTH);
        }

        if ($title === '' || !$fields) {
            return [
                'success' => false,
                'error' => Yii::t('ThiscoveryFormsModule.base', 'Give the library item a title and include at least one field.'),
            ];
        }
        if (!isset(FormLibraryItem::getTypeLabels()[$type])) {
            $type = FormLibraryItem::TYPE_QUESTION;
        }
        if ($type === FormLibraryItem::TYPE_QUESTION && count($fields) > 1) {
            $type = FormLibraryItem::TYPE_BLOCK;
        }

        $item = new FormLibraryItem();
        $item->title = $title;
        $item->type = $type;
        $item->contentcontainer_id = $this->studioContainerId();
        $item->created_by = Yii::$app->user->id;
        $item->setPayload(['fields' => array_values($fields)]);
        if (!$item->save()) {
            return ['success' => false, 'error' => Yii::t('ThiscoveryFormsModule.base', 'Could not save the library item.')];
        }

        return [
            'success' => true,
            'item' => [
                'id' => (int)$item->id,
                'type' => $item->type,
                'title' => $item->title,
                'global' => $item->isGlobal(),
                'canManage' => true,
                'fieldCount' => count($item->getFieldRows()),
            ],
        ];
    }

    public function actionLibraryDelete($itemId = null)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return ['success' => false];
        }
        $itemId = (int)($itemId ?: Yii::$app->request->post('itemId', 0));
        $item = FormLibraryItem::findOne($itemId);
        if (!$item || !$item->canManage()) {
            throw new ForbiddenHttpException();
        }
        $item->delete();
        return ['success' => true];
    }

    public function actionLibraryInsert($itemId)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        // Inserting library questions is building a form: the same permission as listing them (V3-50).
        $probe = $this->prepareNewForm();
        if (!$probe->canCreate() && !$probe->canManage()) {
            throw new ForbiddenHttpException();
        }
        $item = FormLibraryItem::findOne((int)$itemId);
        if (!$item || !$item->isAvailableIn($this->studioContainerId())) {
            throw new NotFoundHttpException();
        }

        $html = '';
        $i = 0;
        $rows = $item->getFieldRows();
        $fields = [];
        foreach ($rows as $row) {
            $post = FormField::exportToPostRow(is_array($row) ? $row : []);
            if ($post === null) {
                continue;
            }
            $field = FormField::fromPostRow($post);
            $field->id = null;
            $fields[] = $field;
        }

        $hasVisible = false;
        foreach ($fields as $field) {
            if ($field->type !== FormField::TYPE_GROUP_END) {
                $hasVisible = true;
                break;
            }
        }
        if ($fields && !$hasVisible) {
            $group = FormField::fromPostRow([
                'type' => FormField::TYPE_QUESTION_GROUP,
                'label' => $item->title !== '' ? $item->title : FormField::defaultLabelForType(FormField::TYPE_QUESTION_GROUP),
            ]);
            $fields = [$group];
        }

        foreach ($fields as $field) {
            $key = 'lib' . uniqid() . $i;
            $html .= $this->renderPartial('@thiscovery-forms/views/form/_field_row', [
                'key' => $key,
                'field' => $field,
                'allFields' => $fields,
                'collapsed' => false,
                'allowedTypes' => null,
                'emailTemplateOptions' => (new EmailTemplateService())->optionsForContainer($this->studioContainerId()),
            ]);
            $i++;
        }

        return ['success' => true, 'html' => $html, 'count' => $i];
    }

    public function actionInsertHealthStatus()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $fields = CustomForm::seedHealthStatusFields();
        $html = '';
        $i = 0;
        foreach ($fields as $field) {
            $key = 'hs' . uniqid() . $i;
            $html .= $this->renderPartial('@thiscovery-forms/views/form/_field_row', [
                'key' => $key,
                'field' => $field,
                'allFields' => $fields,
                'collapsed' => true,
                'allowedTypes' => null,
                'emailTemplateOptions' => (new EmailTemplateService())->optionsForContainer($this->studioContainerId()),
            ]);
            $i++;
        }

        return ['success' => true, 'html' => $html, 'count' => $i];
    }

    public function actionSubmitJson($id = null)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $form = $this->findFillForm($id);
        $this->assertFillAccess($form);

        if (!Yii::$app->request->isPost) {
            return ['success' => false, 'errors' => [Yii::t('ThiscoveryFormsModule.base', 'Invalid form data.')]];
        }
        if (!$form->isPoll()) {
            return ['success' => false, 'errors' => [Yii::t('ThiscoveryFormsModule.base', 'This form cannot be submitted this way.')]];
        }

        $submit = new SubmitForm(['form' => $form]);
        $existing = $this->resolveFillExisting($form, $submit);
        $draft = $this->resolveDraftFromRequest($form);
        if ($draft) {
            $existing = $draft;
        }

        if (!$this->canContinueDraft($form, $existing)) {
            return ['success' => false, 'errors' => [Yii::t('ThiscoveryFormsModule.base', 'You are not allowed to submit this form.')]];
        }

        $submit->editingAnswer = $existing;
        $submit->loadValuesFromRequest(Yii::$app->request->post());
        $ctx = $this->fillContext($form);
        $this->applyFillContext($form, $submit, $ctx);
        $gate = (new \humhub\modules\thiscoveryForms\services\integrity\IntegrityService())->gateSubmit($form, Yii::$app->request->post(), $ctx, true);
        if ($gate) {
            return ['success' => false, 'errors' => [$gate]];
        }
        $anonymous = $form->submitAsAnonymous(false, (bool)$ctx->tokenAccess);
        $wasNewComplete = !$existing || $existing->isInProgress();
        $answer = $submit->save($existing, $anonymous);

        if (!$answer) {
            (new \humhub\modules\thiscoveryForms\services\integrity\IntegrityService())->refundAccessToken($form, $ctx);
            return ['success' => false, 'errors' => array_values($submit->getErrorSummary(true))];
        }

        (new \humhub\modules\thiscoveryForms\services\integrity\IntegrityService())->discardCaptchaResult($form);
        $this->afterCompleteSave($form, $ctx, $answer, $anonymous);

        if ($wasNewComplete && !$anonymous) {
            $this->notifySubmission($form, $answer);
        }

        $results = $form->showsPollResults()
            ? (new DashboardService())->getPollResults($form)
            : [];

        $html = $this->renderPartial('@thiscovery-forms/views/widgets/poll-results', [
            'results' => $results,
        ]);

        $thanks = '<p class="cf-poll-embed__thanks">'
            . Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Thanks for voting.'))
            . '</p>';

        return [
            'success' => true,
            'html' => $thanks . ($form->showsPollResults() ? $html : ''),
        ];
    }
}
