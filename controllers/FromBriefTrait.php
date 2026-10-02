<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\services\BriefChatService;
use humhub\modules\thiscoveryForms\services\DocumentTextExtractor;
use humhub\modules\thiscoveryForms\services\LlmUsageLogger;
use humhub\modules\thiscoveryForms\services\SurveyDesignMapper;
use humhub\modules\thiscoveryForms\services\SurveyFromBriefService;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Create survey from brief / Word / PDF.
 */
trait FromBriefTrait
{
    public function actionCreateFromBrief()
    {
        if (!Module::isFromBriefEnabled()) {
            throw new NotFoundHttpException();
        }
        $probe = $this->prepareNewForm();
        if (!$probe->canCreate()) {
            throw new ForbiddenHttpException();
        }

        $container = $this->studioContainer();
        if ($container === null && !Module::opensWithoutAdminMenu()) {
            $this->subLayout = '@humhub/modules/admin/views/layouts/main';
        }

        $service = new SurveyFromBriefService();
        $llmOn = Module::isFromBriefLlmEnabled();
        $costWarn = $llmOn ? $service->costWarningBanner() : null;

        if (Yii::$app->request->isPost) {
            return $this->handleFromBriefStart($service, $llmOn);
        }

        return $this->render('@thiscovery-forms/views/form/create-from-brief', [
            'contentContainer' => $container,
            'folderId' => (int)Yii::$app->request->get('folder', 0),
            'llmEnabled' => $llmOn,
            'costWarning' => $costWarn,
            'maxUploadMb' => (int)ceil(Module::fromBriefMaxUploadBytes() / 1048576),
        ]);
    }

    public function actionFromBriefWorkspace($session)
    {
        if (!Module::isFromBriefEnabled()) {
            throw new NotFoundHttpException();
        }
        $probe = $this->prepareNewForm();
        if (!$probe->canCreate()) {
            throw new ForbiddenHttpException();
        }

        $service = new SurveyFromBriefService();
        $state = $service->getState((string)$session);
        if (!$state) {
            $this->view->error(Yii::t('ThiscoveryFormsModule.base', 'That brief session expired. Start again.'));
            return $this->redirect(Url::toCreateFromBrief($this->studioContainer()));
        }

        $container = $this->studioContainer();
        if ($container === null && !Module::opensWithoutAdminMenu()) {
            $this->subLayout = '@humhub/modules/admin/views/layouts/main';
        }

        $llmOn = Module::isFromBriefLlmEnabled();
        $sessionCost = $llmOn
            ? (new LlmUsageLogger())->sessionSummary((string)$session)
            : ['tokens' => 0, 'cost' => 0];

        return $this->render('@thiscovery-forms/views/form/from-brief-workspace', [
            'contentContainer' => $container,
            'sessionId' => (string)$session,
            'state' => $state,
            'llmEnabled' => $llmOn,
            'costWarning' => $llmOn ? $service->costWarningBanner() : null,
            'sessionCost' => $sessionCost,
        ]);
    }

    public function actionFromBriefChat($session)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        @set_time_limit(180);
        if (!Module::isFromBriefLlmEnabled()) {
            return ['success' => false, 'error' => Yii::t('ThiscoveryFormsModule.base', 'LLM assist is not enabled.')];
        }
        if (!$this->prepareNewForm()->canCreate()) {
            throw new ForbiddenHttpException();
        }
        $service = new SurveyFromBriefService();
        $state = $service->getState((string)$session);
        if (!$state) {
            return ['success' => false, 'error' => Yii::t('ThiscoveryFormsModule.base', 'Session expired.')];
        }
        $message = trim((string)Yii::$app->request->post('message', ''));
        try {
            $result = (new BriefChatService())->turn(
                (string)($state['brief'] ?? ''),
                is_array($state['chat'] ?? null) ? $state['chat'] : [],
                $message,
                (string)$session
            );
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
        $service->saveState((string)$session, [
            'brief' => $result['brief'],
            'chat' => $result['history'],
            'title' => $state['title'] ?? '',
            'source_name' => $state['source_name'] ?? '',
            'fields' => $state['fields'] ?? null,
            'map_mode' => $state['map_mode'] ?? null,
            'map_warning' => $state['map_warning'] ?? null,
            'folder_id' => $state['folder_id'] ?? 0,
            'pending_llm' => $state['pending_llm'] ?? false,
        ]);
        $cost = (new LlmUsageLogger())->sessionSummary((string)$session);
        return [
            'success' => true,
            'reply' => $result['reply'],
            'brief' => $result['brief'],
            'briefChanged' => !empty($result['briefChanged']),
            'sessionCost' => $cost,
        ];
    }

    public function actionFromBriefGenerate($session)
    {
        if (!Module::isFromBriefEnabled()) {
            throw new NotFoundHttpException();
        }
        if (!$this->prepareNewForm()->canCreate()) {
            throw new ForbiddenHttpException();
        }

        $wantsJson = Yii::$app->request->getIsAjax()
            || (string)Yii::$app->request->post('ajax', '') === '1'
            || str_contains((string)Yii::$app->request->headers->get('Accept', ''), 'application/json');

        $service = new SurveyFromBriefService();
        $state = $service->getState((string)$session);
        if (!$state) {
            if ($wantsJson) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return ['success' => false, 'error' => Yii::t('ThiscoveryFormsModule.base', 'Session expired.')];
            }
            $this->view->error(Yii::t('ThiscoveryFormsModule.base', 'Session expired.'));
            return $this->redirect(Url::toCreateFromBrief($this->studioContainer()));
        }

        $brief = trim((string)Yii::$app->request->post('brief', $state['brief'] ?? ''));
        if ($brief === '') {
            if ($wantsJson) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return ['success' => false, 'error' => Yii::t('ThiscoveryFormsModule.base', 'Brief text is empty.')];
            }
            $this->view->error(Yii::t('ThiscoveryFormsModule.base', 'Brief text is empty.'));
            return $this->redirect(Url::toFromBriefWorkspace($this->studioContainer(), (string)$session));
        }

        // Mark pending and respond immediately so AWS ELB (~60s) does not 504 while Claude runs.
        $service->saveState((string)$session, [
            'brief' => $brief,
            'title' => $state['title'] ?? '',
            'fields' => $state['fields'] ?? [],
            'map_mode' => $state['map_mode'] ?? 'rules',
            'map_warning' => $state['map_warning'] ?? '',
            'chat' => $state['chat'] ?? [],
            'source_name' => $state['source_name'] ?? '',
            'folder_id' => $state['folder_id'] ?? 0,
            'pending_llm' => true,
            'llm_started_at' => time(),
        ]);

        // Release session lock before long background work / so status polls are not blocked.
        try {
            Yii::$app->session->close();
        } catch (\Throwable $e) {
            // ignore
        }

        $workspaceUrl = Url::toFromBriefWorkspace($this->studioContainer(), (string)$session);
        if ($wantsJson) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            $payload = [
                'success' => true,
                'started' => true,
                'pending' => true,
                'redirect' => $workspaceUrl,
            ];
            Yii::$app->response->data = $payload;
            Yii::$app->response->send();
        } else {
            $this->view->info(Yii::t('ThiscoveryFormsModule.base', 'AI improvement started. This page will update when it finishes.'));
            Yii::$app->response->redirect($workspaceUrl);
            Yii::$app->response->send();
        }

        $this->runFromBriefGenerateJob((string)$session, $brief, $state);
        Yii::$app->end();
    }

    public function actionFromBriefStatus($session)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Module::isFromBriefEnabled()) {
            throw new NotFoundHttpException();
        }
        if (!$this->prepareNewForm()->canCreate()) {
            throw new ForbiddenHttpException();
        }

        $service = new SurveyFromBriefService();
        $state = $service->getState((string)$session);
        if (!$state) {
            return ['success' => false, 'error' => Yii::t('ThiscoveryFormsModule.base', 'Session expired.')];
        }

        $pending = !empty($state['pending_llm']);
        $startedAt = (int)($state['llm_started_at'] ?? 0);
        if ($pending && $startedAt > 0 && (time() - $startedAt) > 240) {
            $service->saveState((string)$session, [
                'pending_llm' => false,
                'llm_started_at' => null,
                'map_warning' => Yii::t(
                    'ThiscoveryFormsModule.base',
                    'AI improvement timed out. You can keep the current draft or click Regenerate again.'
                ),
            ]);
            $pending = false;
            $state = $service->getState((string)$session) ?? $state;
        }

        $fields = is_array($state['fields'] ?? null) ? $state['fields'] : [];
        return [
            'success' => true,
            'pending' => $pending,
            'mode' => (string)($state['map_mode'] ?? 'rules'),
            'warning' => (string)($state['map_warning'] ?? ''),
            'fieldCount' => count($fields),
            'title' => (string)($state['title'] ?? ''),
        ];
    }

    /**
     * Continue LLM mapping after the HTTP response has been sent (avoids ELB 504).
     *
     * @param array<string, mixed> $state
     */
    protected function runFromBriefGenerateJob(string $sessionId, string $brief, array $state): void
    {
        // Release PHP session lock so status polling can read cache while Claude runs.
        try {
            Yii::$app->session->close();
        } catch (\Throwable $e) {
            // ignore
        }

        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        ignore_user_abort(true);
        @set_time_limit(300);

        $service = new SurveyFromBriefService();
        try {
            $instructions = BriefChatService::recentInstructions(is_array($state['chat'] ?? null) ? $state['chat'] : []);
            $mapped = (new SurveyDesignMapper())->map($brief, true, $sessionId, $instructions);
            $service->saveState($sessionId, [
                'brief' => $brief,
                'title' => $mapped['title'] ?: ($state['title'] ?? ''),
                'fields' => $mapped['fields'],
                'map_mode' => $mapped['mode'],
                'map_warning' => $mapped['warning'],
                'chat' => $state['chat'] ?? [],
                'source_name' => $state['source_name'] ?? '',
                'folder_id' => $state['folder_id'] ?? 0,
                'pending_llm' => false,
                'llm_started_at' => null,
            ]);
        } catch (\Throwable $e) {
            Yii::error('from-brief generate job failed: ' . $e->getMessage(), 'thiscovery-forms');
            $service->saveState($sessionId, [
                'brief' => $brief,
                'title' => $state['title'] ?? '',
                'fields' => $state['fields'] ?? [],
                'map_mode' => $state['map_mode'] ?? 'rules',
                'map_warning' => $e->getMessage(),
                'chat' => $state['chat'] ?? [],
                'source_name' => $state['source_name'] ?? '',
                'folder_id' => $state['folder_id'] ?? 0,
                'pending_llm' => false,
                'llm_started_at' => null,
            ]);
        }
    }

    public function actionFromBriefApply($session)
    {
        if (!Module::isFromBriefEnabled()) {
            throw new NotFoundHttpException();
        }
        if (!$this->prepareNewForm()->canCreate()) {
            throw new ForbiddenHttpException();
        }
        $service = new SurveyFromBriefService();
        $state = $service->getState((string)$session);
        if (!$state || empty($state['fields']) || !is_array($state['fields'])) {
            $this->view->error(Yii::t('ThiscoveryFormsModule.base', 'Generate questions first, then create the draft.'));
            return $this->redirect(Url::toFromBriefWorkspace($this->studioContainer(), (string)$session));
        }

        $title = trim((string)Yii::$app->request->post('title', $state['title'] ?? ''));
        try {
            $form = $service->createDraft(
                $this->studioContainer(),
                $title,
                $state['fields'],
                (int)($state['folder_id'] ?? 0) ?: null
            );
        } catch (\Throwable $e) {
            $this->view->error($e->getMessage());
            return $this->redirect(Url::toFromBriefWorkspace($this->studioContainer(), (string)$session));
        }

        $service->clearState((string)$session);
        $this->view->success(Yii::t('ThiscoveryFormsModule.base', 'Draft survey created. Review questions in the builder.'));
        return $this->redirect(Url::toEdit($form, ['tab' => 'builder']));
    }

    protected function handleFromBriefStart(SurveyFromBriefService $service, bool $llmOn)
    {
        $brief = trim((string)Yii::$app->request->post('brief', ''));
        $title = trim((string)Yii::$app->request->post('title', ''));
        $folderId = (int)Yii::$app->request->post('folder', Yii::$app->request->get('folder', 0));
        $sourceName = '';

        $upload = UploadedFile::getInstanceByName('document');
        if ($upload && !$upload->hasError) {
            if ($upload->size > Module::fromBriefMaxUploadBytes()) {
                $this->view->error(Yii::t('ThiscoveryFormsModule.base', 'That file is larger than the allowed upload size.'));
                return $this->redirect(Url::toCreateFromBrief($this->studioContainer(), $folderId ? ['folder' => $folderId] : []));
            }
            try {
                $extracted = (new DocumentTextExtractor())->extractFromUpload(
                    (string)$upload->tempName,
                    (string)$upload->name,
                    (string)$upload->extension
                );
                $brief = trim($brief . "\n\n" . $extracted['text']);
                $sourceName = (string)$upload->name;
            } catch (\Throwable $e) {
                $this->view->error($e->getMessage());
                return $this->redirect(Url::toCreateFromBrief($this->studioContainer(), $folderId ? ['folder' => $folderId] : []));
            }
        }

        if ($brief === '') {
            $this->view->error(Yii::t('ThiscoveryFormsModule.base', 'Paste a brief or upload a Word/PDF file.'));
            return $this->redirect(Url::toCreateFromBrief($this->studioContainer(), $folderId ? ['folder' => $folderId] : []));
        }

        $sessionId = $service->newSessionId();
        // Map with rules first so upload never waits on the LLM (avoids gateway 504).
        $mapped = (new SurveyDesignMapper())->map($brief, false, $sessionId);
        $service->saveState($sessionId, [
            'brief' => $brief,
            'title' => $title !== '' ? $title : $mapped['title'],
            'fields' => $mapped['fields'],
            'map_mode' => $mapped['mode'],
            'map_warning' => $mapped['warning'],
            'chat' => [],
            'source_name' => $sourceName,
            'folder_id' => $folderId,
            'pending_llm' => $llmOn,
        ]);

        if ($llmOn) {
            $this->view->info(Yii::t(
                'ThiscoveryFormsModule.base',
                'Brief extracted. A quick rules-based draft is ready — AI improvement is running in the background.'
            ));
        } elseif ($mapped['warning'] !== '') {
            $this->view->warn($mapped['warning']);
        }

        $workspaceUrl = Url::toFromBriefWorkspace($this->studioContainer(), $sessionId);
        Yii::$app->response->redirect($workspaceUrl);
        Yii::$app->response->send();

        if ($llmOn) {
            $this->runFromBriefGenerateJob($sessionId, $brief, [
                'title' => $title !== '' ? $title : $mapped['title'],
                'fields' => $mapped['fields'],
                'map_mode' => $mapped['mode'],
                'map_warning' => $mapped['warning'],
                'chat' => [],
                'source_name' => $sourceName,
                'folder_id' => $folderId,
            ]);
        }
        Yii::$app->end();
    }

    abstract protected function prepareNewForm();

    abstract protected function studioContainer();
}
