<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormPanel;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use humhub\modules\thiscoveryForms\models\FormRound;
use humhub\modules\thiscoveryForms\models\FormWave;
use humhub\modules\thiscoveryForms\services\PanelService;
use humhub\modules\thiscoveryForms\services\RoundService;
use humhub\modules\thiscoveryForms\services\TranslationImportExportService;
use humhub\modules\thiscoveryForms\services\TranslationService;
use humhub\modules\thiscoveryForms\services\WaveService;
use humhub\modules\user\models\User;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Panel, waves, rounds, and translation studio actions.
 */
trait ProgrammeTrait
{
    abstract protected function findForm($id): CustomForm;

    protected function redirectStudio(CustomForm $form, string $tab)
    {
        return $this->redirect(Url::toEdit($form, ['tab' => 'settings', 'section' => $tab]));
    }

    protected function requireManageForm($id): CustomForm
    {
        $form = $this->findForm($id);
        if (!$form->canManage()) {
            throw new ForbiddenHttpException();
        }
        return $form;
    }

    public function actionPanelSave($id)
    {
        $form = $this->requireManageForm($id);
        if (!Yii::$app->request->isPost) {
            return $this->redirectStudio($form, 'panel');
        }

        $panelService = new PanelService();
        $panelId = (int)Yii::$app->request->post('panel_id', 0);
        if ($panelId) {
            $panel = FormPanel::findOne($panelId);
            if (!$panel) {
                throw new NotFoundHttpException();
            }
        } else {
            $panel = $panelService->ensurePanel($form);
            if (!$panel) {
                Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Could not create a panel.'));
                return $this->redirectStudio($form, 'panel');
            }
        }

        $panel->title = trim((string)Yii::$app->request->post('panel_title', $panel->title));
        $panel->description = (string)Yii::$app->request->post('panel_description', $panel->description);
        $panel->save(false);

        $form->setSetting('panel_id', (int)$panel->id);
        $form->setSetting('log_panel_activity', true);
        $form->setSetting('email_on_wave_open', Yii::$app->request->post('email_on_wave_open', '0') === '1');
        $form->save(false, ['settings_json']);

        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Panel saved.'));
        return $this->redirectStudio($form, 'panel');
    }

    public function actionPanelAddMember($id)
    {
        $form = $this->requireManageForm($id);
        if (!Yii::$app->request->isPost) {
            return $this->redirectStudio($form, 'panel');
        }

        $panel = (new PanelService())->ensurePanel($form);
        if (!$panel) {
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Save the form first, then add panel members.'));
            return $this->redirectStudio($form, 'panel');
        }

        $raw = trim((string)Yii::$app->request->post('member', ''));
        $weight = (float)Yii::$app->request->post('weight', 1) ?: 1;
        if ($raw === '') {
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Enter a username or email address.'));
            return $this->redirectStudio($form, 'panel');
        }

        $user = User::find()
            ->where(['email' => $raw])
            ->orWhere(['username' => $raw])
            ->one();
        if ($user) {
            (new PanelService())->addUserMember($panel, $user, $weight);
        } elseif (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            (new PanelService())->addEmailMember($panel, $raw, null, $weight);
        } else {
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Could not find that user. Use a username or a valid email.'));
            return $this->redirectStudio($form, 'panel');
        }

        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Member added.'));
        return $this->redirectStudio($form, 'panel');
    }

    public function actionPanelRemoveMember($id)
    {
        $form = $this->requireManageForm($id);
        if (!Yii::$app->request->isPost) {
            return $this->redirectStudio($form, 'panel');
        }
        $member = FormPanelMember::findOne((int)Yii::$app->request->post('member_id', 0));
        $panel = (new PanelService())->getPanel($form);
        if (!$member || !$panel || (int)$member->panel_id !== (int)$panel->id) {
            throw new NotFoundHttpException();
        }
        $member->status = FormPanelMember::STATUS_INACTIVE;
        $member->save(false, ['status', 'updated_at']);
        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Member removed.'));
        return $this->redirectStudio($form, 'panel');
    }

    public function actionPanelInvite($id)
    {
        $form = $this->requireManageForm($id);
        if (!Yii::$app->request->isPost) {
            return $this->redirectStudio($form, 'panel');
        }
        $memberId = (int)Yii::$app->request->post('member_id', 0);
        $panel = (new PanelService())->getPanel($form);
        $service = new PanelService();
        $sent = 0;
        $failed = 0;

        $members = $memberId
            ? FormPanelMember::find()->where(['id' => $memberId, 'panel_id' => $panel->id ?? 0])->all()
            : ($panel ? $panel->getActiveMembers()->all() : []);

        foreach ($members as $member) {
            if ($service->sendInvite($form, $member)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        if ($sent) {
            Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Sent {n} invitation email(s).', ['n' => $sent]));
        }
        if ($failed) {
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', '{n} invitation(s) could not be sent.', ['n' => $failed]));
        }
        return $this->redirectStudio($form, 'panel');
    }

    public function actionWaveSave($id)
    {
        $form = $this->requireManageForm($id);
        if (!Yii::$app->request->isPost) {
            return $this->redirectStudio($form, 'panel');
        }
        $waves = new WaveService();
        $waves->ensureSetup($form);
        $waveId = (int)Yii::$app->request->post('wave_id', 0);
        $title = trim((string)Yii::$app->request->post('title', ''));
        $opens = (string)Yii::$app->request->post('opens_at', '');
        $closes = (string)Yii::$app->request->post('closes_at', '');

        if ($waveId) {
            $wave = FormWave::findOne((int)$waveId);
            if (!$wave || !$waves->waveBelongsToForm($wave, $form)) {
                throw new NotFoundHttpException();
            }
            $wave->title = $title ?: $wave->title;
            $wave->opens_at = $waves->normalizeDateTime($opens);
            $wave->closes_at = $waves->normalizeDateTime($closes);
            $wave->save(false);
        } else {
            $waves->createWave($form, $title ?: null, $opens, $closes);
        }

        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Wave saved.'));
        return $this->redirectStudio($form, 'panel');
    }

    public function actionWaveStatus($id)
    {
        $form = $this->requireManageForm($id);
        if (!Yii::$app->request->isPost) {
            return $this->redirectStudio($form, 'panel');
        }
        $wave = FormWave::findOne((int)Yii::$app->request->post('wave_id', 0));
        if (!$wave || !(new WaveService())->waveBelongsToForm($wave, $form)) {
            throw new NotFoundHttpException();
        }
        $previous = $wave->status;
        (new WaveService())->setStatus($wave, (string)Yii::$app->request->post('status', ''));
        $wave->refresh();
        if ($previous !== FormWave::STATUS_OPEN && $wave->status === FormWave::STATUS_OPEN) {
            $result = (new WaveService())->inviteIfDue($wave);
            if ($result && $result['skipped'] === 'scheduled') {
                Yii::$app->session->setFlash('success', Yii::t(
                    'ThiscoveryFormsModule.base',
                    'Wave opened. Members will be emailed when it starts.'
                ));
                return $this->redirectStudio($form, 'panel');
            }
            if ($result && $result['skipped'] === 'form') {
                Yii::$app->session->setFlash('success', Yii::t(
                    'ThiscoveryFormsModule.base',
                    'Wave opened. Set the form status to Open, then members can be emailed.'
                ));
                return $this->redirectStudio($form, 'panel');
            }
            if ($result && $result['skipped'] === null) {
                $msg = Yii::t('ThiscoveryFormsModule.base', 'Wave opened.');
                if ($result['sent']) {
                    $msg .= ' ' . Yii::t('ThiscoveryFormsModule.base', 'Sent {n} invitation email(s).', ['n' => $result['sent']]);
                }
                if ($result['failed']) {
                    Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', '{n} invitation(s) could not be sent.', ['n' => $result['failed']]));
                }
                Yii::$app->session->setFlash('success', $msg);
                return $this->redirectStudio($form, 'panel');
            }
        }
        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Wave updated.'));
        return $this->redirectStudio($form, 'panel');
    }

    public function actionRoundSave($id)
    {
        $form = $this->requireManageForm($id);
        if (!Yii::$app->request->isPost) {
            return $this->redirectStudio($form, 'rounds');
        }
        $rounds = new RoundService();
        $rounds->ensureSetup($form);
        $roundId = (int)Yii::$app->request->post('round_id', 0);
        $title = trim((string)Yii::$app->request->post('title', ''));
        if ($roundId) {
            $round = FormRound::findOne(['id' => $roundId, 'form_id' => $form->id]);
            if (!$round) {
                throw new NotFoundHttpException();
            }
            $round->title = $title ?: $round->title;
            $round->opens_at = (new WaveService())->normalizeDateTime((string)Yii::$app->request->post('opens_at', ''));
            $round->closes_at = (new WaveService())->normalizeDateTime((string)Yii::$app->request->post('closes_at', ''));
            $round->save(false);
        } else {
            $rounds->createRound($form, $title ?: null);
        }
        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Round saved.'));
        return $this->redirectStudio($form, 'rounds');
    }

    public function actionRoundStatus($id)
    {
        $form = $this->requireManageForm($id);
        if (!Yii::$app->request->isPost) {
            return $this->redirectStudio($form, 'rounds');
        }
        $round = FormRound::findOne(['id' => (int)Yii::$app->request->post('round_id', 0), 'form_id' => $form->id]);
        if (!$round) {
            throw new NotFoundHttpException();
        }
        (new RoundService())->setStatus($round, (string)Yii::$app->request->post('status', ''));
        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Round updated.'));
        return $this->redirectStudio($form, 'rounds');
    }

    public function actionRoundPublish($id)
    {
        $form = $this->requireManageForm($id);
        if (!Yii::$app->request->isPost) {
            return $this->redirectStudio($form, 'rounds');
        }
        $round = FormRound::findOne(['id' => (int)Yii::$app->request->post('round_id', 0), 'form_id' => $form->id]);
        if (!$round) {
            throw new NotFoundHttpException();
        }
        $html = (string)Yii::$app->request->post('summary_html', '');
        (new RoundService())->publishSummary($form, $round, $html !== '' ? $html : null);
        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Summary published for the next round.'));
        return $this->redirectStudio($form, 'rounds');
    }

    public function actionRoundDelphi($id)
    {
        $form = $this->requireManageForm($id);
        if (!Yii::$app->request->isPost) {
            return $this->redirectStudio($form, 'rounds');
        }
        $n = (int)Yii::$app->request->post('round_count', 3);
        (new RoundService())->applyDelphiPreset($form, $n);
        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Delphi preset applied.'));
        return $this->redirectStudio($form, 'rounds');
    }

    public function actionTranslationsSave($id)
    {
        $form = $this->requireManageForm($id);
        if (!Yii::$app->request->isPost) {
            return $this->redirectStudio($form, 'translations');
        }
        $lang = (string)Yii::$app->request->post('language', '');
        $enabled = $form->getEnabledLanguages();
        $source = $form->getSourceLanguage();
        if ($lang === '' || $lang === $source || !in_array($lang, $enabled, true)) {
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Choose a language other than the source language.'));
            return $this->redirectStudio($form, 'translations');
        }

        $service = new TranslationService();
        $formData = Yii::$app->request->post('form_i18n', []);
        $service->saveFormStrings($form, $lang, is_array($formData) ? $formData : []);
        $fieldsData = Yii::$app->request->post('field_i18n', []);
        if (is_array($fieldsData)) {
            foreach ($form->fields as $field) {
                $row = $fieldsData[(string)$field->id] ?? [];
                $service->saveFieldStrings($field, $lang, is_array($row) ? $row : []);
            }
        }

        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Translations saved.'));
        return $this->redirect(Url::toEdit($form) . '?tab=translations&lang=' . urlencode($lang));
    }

    public function actionGenerateTranslations($id)
    {
        $form = $this->requireManageForm($id);
        if (!Yii::$app->request->isPost) {
            return $this->redirectStudio($form, 'translations');
        }
        $lang = (string)Yii::$app->request->post('language', '');
        $only = $lang !== '' ? $lang : null;
        if (class_exists(\humhub\modules\thiscoveryTranslate\services\FormsHook::class)
            && \humhub\modules\thiscoveryTranslate\services\FormsHook::queueFormTranslation((int)$form->id, $only)) {
            Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Machine translation queued. Refresh this tab shortly to review overlays.'));
        } else {
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Thiscovery Translate is not available or not enabled.'));
        }
        $redirLang = $only ?: (string)Yii::$app->request->get('lang', '');
        return $this->redirect(Url::toEdit($form) . '?tab=translations' . ($redirLang !== '' ? '&lang=' . urlencode($redirLang) : ''));
    }

    public function actionExportTranslations($id)
    {
        $form = $this->requireManageForm($id);
        $format = strtolower((string)Yii::$app->request->get('format', 'csv'));
        $service = new TranslationImportExportService();

        Yii::$app->response->format = Response::FORMAT_RAW;
        if ($format === 'json') {
            $filename = 'translations-' . $form->id . '.json';
            Yii::$app->response->headers->set('Content-Type', 'application/json; charset=UTF-8');
            Yii::$app->response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            return $service->exportJsonString($form);
        }

        $filename = 'translations-' . $form->id . '.csv';
        Yii::$app->response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        Yii::$app->response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        return "\xEF\xBB\xBF" . $service->exportCsv($form);
    }

    public function actionImportTranslations($id)
    {
        $form = $this->requireManageForm($id);
        if (!Yii::$app->request->isPost) {
            return $this->redirectStudio($form, 'translations');
        }

        $upload = UploadedFile::getInstanceByName('translation_file');
        if (!$upload || $upload->hasError) {
            $msg = Yii::t('ThiscoveryFormsModule.base', 'Please choose a JSON or CSV translation file to import.');
            $this->view->error($msg);
            Yii::$app->session->setFlash('cf_i18n_import_notice', ['type' => 'error', 'message' => $msg]);
            return $this->redirectStudio($form, 'translations');
        }

        $raw = @file_get_contents($upload->tempName);
        if ($raw === false || $raw === '') {
            $msg = Yii::t('ThiscoveryFormsModule.base', 'Could not read the uploaded file.');
            $this->view->error($msg);
            Yii::$app->session->setFlash('cf_i18n_import_notice', ['type' => 'error', 'message' => $msg]);
            return $this->redirectStudio($form, 'translations');
        }

        $service = new TranslationImportExportService();
        $ext = strtolower((string)$upload->extension);
        $error = ($ext === 'csv')
            ? $service->importCsv($form, $raw)
            : $service->importJson($form, $raw);

        if ($error) {
            $this->view->error($error);
            Yii::$app->session->setFlash('cf_i18n_import_notice', ['type' => 'error', 'message' => $error]);
        } else {
            $msg = Yii::t('ThiscoveryFormsModule.base', 'Translations imported.');
            $this->view->success($msg);
            Yii::$app->session->setFlash('cf_i18n_import_notice', ['type' => 'success', 'message' => $msg]);
        }

        return $this->redirectStudio($form, 'translations');
    }
}
