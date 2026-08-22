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
     * Stay in the studio after save. Optionally reopen a tab or the test preview.
     */
    protected function redirectAfterStudioSave(CustomForm $form)
    {
        $tab = trim((string)Yii::$app->request->post('studio_tab', ''));
        $after = (string)Yii::$app->request->post('after_save', 'stay');
        if ($after === 'preview') {
            return $this->redirect(Url::toPreview($form));
        }
        $url = Url::toEdit($form);
        if ($tab !== '' && $tab !== 'builder') {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'tab=' . rawurlencode($tab);
        }
        return $this->redirect($url);
    }

    /**
     * Permanently remove a form (hard delete). ContentActiveRecord::delete() is only a
     * soft delete and would leave the form on the list.
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
            $ok = $form->hardDelete();
        } catch (\Throwable $e) {
            Yii::error('Thiscovery Forms could not delete form #' . $form->id . ': ' . $e->getMessage(), 'thiscovery-forms');
        }

        if ($ok) {
            Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Form deleted.'));
        } else {
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Could not delete the form.'));
        }

        return $this->redirect(Url::toManageIndex($this->studioContainer()));
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
        // Global (Administration) create wizard keeps the admin left menu.
        if ($container === null) {
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
        if (!Yii::$app->request->isPost) {
            return $this->redirect(Url::toEdit($form));
        }

        $upload = UploadedFile::getInstanceByName('import_file');
        if (!$upload || $upload->hasError) {
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Please choose a JSON or CSV file to import.'));
            return $this->redirect(Url::toEdit($form) . '#import');
        }

        $raw = @file_get_contents($upload->tempName);
        if ($raw === false || $raw === '') {
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Could not read the uploaded file.'));
            return $this->redirect(Url::toEdit($form));
        }

        $service = new QuestionImportExportService();
        $ext = strtolower((string)$upload->extension);
        $replace = (string)Yii::$app->request->post('replace_fields', '') === '1';
        $error = ($ext === 'csv')
            ? $service->importCsv($form, $raw, $replace)
            : $service->importJson($form, $raw, $replace);

        if ($error) {
            Yii::$app->session->setFlash('error', $error);
        } elseif ($replace) {
            Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Existing questions were replaced with the imported file.'));
        } else {
            Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Questions imported.'));
        }

        return $this->redirect(Url::toEdit($form));
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
        $item = FormLibraryItem::findOne((int)$itemId);
        if (!$item) {
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

    public function actionSubmitJson($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $form = $this->findForm($id);
        $this->assertFillAccess($form);

        if (!Yii::$app->request->isPost) {
            return ['success' => false, 'errors' => [Yii::t('ThiscoveryFormsModule.base', 'Invalid form data.')]];
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

        $submit->loadValuesFromRequest(Yii::$app->request->post());
        $ctx = $this->fillContext($form);
        $this->applyFillContext($form, $submit, $ctx);
        $anonymous = $form->allowsAnonymous() || (Yii::$app->user->isGuest && $ctx->tokenAccess);
        $wasNewComplete = !$existing || $existing->isInProgress();
        $answer = $submit->save($existing, $anonymous);

        if (!$answer) {
            return ['success' => false, 'errors' => array_values($submit->getErrorSummary(true))];
        }

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
