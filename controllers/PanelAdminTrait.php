<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\admin\permissions\ManageModules;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormPanel;
use humhub\modules\thiscoveryForms\models\FormPanelActivity;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use humhub\modules\thiscoveryForms\permissions\CreateGlobalForm;
use humhub\modules\thiscoveryForms\permissions\ManageGlobalForm;
use humhub\modules\thiscoveryForms\models\FormWave;
use humhub\modules\thiscoveryForms\services\PanelService;
use humhub\modules\thiscoveryForms\services\WaveService;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Standalone panel list, members, CSV import, and manual add (space + network).
 */
trait PanelAdminTrait
{
    protected function panelContainer()
    {
        return property_exists($this, 'contentContainer') ? $this->contentContainer : null;
    }

    protected function panelContainerId(): ?int
    {
        $container = $this->panelContainer();
        return $container ? (int)$container->contentcontainer_id : null;
    }

    protected function canManagePanels(): bool
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }
        $container = $this->panelContainer();
        if ($container) {
            $probe = new CustomForm($container);
            return $probe->canCreate() || $probe->canManage();
        }
        return Yii::$app->user->isAdmin()
            || Yii::$app->user->can(ManageModules::class)
            || Yii::$app->user->can(ManageGlobalForm::class)
            || Yii::$app->user->can(CreateGlobalForm::class);
    }

    protected function requireManagePanels(): void
    {
        if (!$this->canManagePanels()) {
            throw new ForbiddenHttpException();
        }
    }

    protected function findManagedPanel($id): FormPanel
    {
        $this->requireManagePanels();
        $query = FormPanel::find()->andWhere(['id' => (int)$id]);
        $containerId = $this->panelContainerId();
        if ($containerId) {
            $query->andWhere(['contentcontainer_id' => $containerId]);
        } else {
            $query->andWhere(['contentcontainer_id' => null]);
        }
        $panel = $query->one();
        if (!$panel) {
            throw new NotFoundHttpException();
        }
        return $panel;
    }

    protected function renderModuleView(string $view, array $params = [])
    {
        $params['contentContainer'] = $this->panelContainer();
        $params['canManagePanels'] = true;
        return $this->render('@thiscovery-forms/views/' . $view, $params);
    }

    protected function renderPanel(string $view, array $params = [])
    {
        return $this->renderModuleView('panel/' . $view, $params);
    }

    public function actionPanels()
    {
        $this->requireManagePanels();
        $q = trim((string)Yii::$app->request->get('q', ''));
        $query = FormPanel::find()->orderBy(['title' => SORT_ASC, 'id' => SORT_ASC]);
        $containerId = $this->panelContainerId();
        if ($containerId) {
            $query->andWhere(['contentcontainer_id' => $containerId]);
        } else {
            $query->andWhere(['contentcontainer_id' => null]);
        }
        if ($q !== '') {
            $query->andWhere(['like', 'title', $q]);
        }
        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 25],
            'sort' => [
                'defaultOrder' => ['title' => SORT_ASC],
                'attributes' => ['title', 'created_at', 'id'],
            ],
        ]);
        return $this->renderPanel('index', [
            'dataProvider' => $provider,
            'filters' => ['q' => $q],
        ]);
    }

    public function actionPanelEdit($id = null)
    {
        $this->requireManagePanels();
        if ($id) {
            $panel = $this->findManagedPanel($id);
            $isNew = false;
        } else {
            $panel = new FormPanel([
                'contentcontainer_id' => $this->panelContainerId(),
            ]);
            $isNew = true;
        }

        if (Yii::$app->request->isPost) {
            $panel->title = trim((string)Yii::$app->request->post('title', $panel->title));
            $panel->description = (string)Yii::$app->request->post('description', $panel->description);
            $panel->contentcontainer_id = $this->panelContainerId();
            $panel->setMemberFields(Yii::$app->request->post('fields', []));
            if ($panel->save()) {
                Yii::$app->session->setFlash('success', $isNew
                    ? Yii::t('ThiscoveryFormsModule.base', 'Panel created.')
                    : Yii::t('ThiscoveryFormsModule.base', 'Panel saved.'));
                return $this->redirect(Url::toPanelView($panel, $this->panelContainer()));
            }
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Could not save the panel.'));
        }

        return $this->renderPanel('edit', [
            'panel' => $panel,
            'isNew' => $isNew,
        ]);
    }

    public function actionPanelView($id)
    {
        $panel = $this->findManagedPanel($id);
        $q = trim((string)Yii::$app->request->get('q', ''));
        $query = $panel->getActiveMembers();
        if ($q !== '') {
            $query->andWhere([
                'or',
                ['like', 'display_name', $q],
                ['like', 'email', $q],
                ['like', 'first_name', $q],
                ['like', 'last_name', $q],
                ['like', 'demographics_json', $q],
            ]);
        }
        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 25],
            'sort' => [
                'defaultOrder' => ['display_name' => SORT_ASC, 'id' => SORT_ASC],
                'attributes' => ['display_name', 'email', 'created_at', 'id'],
            ],
        ]);

        $memberIds = [];
        foreach ($provider->getModels() as $member) {
            $memberIds[] = (int)$member->id;
        }
        $activityCounts = [];
        $latestActivity = [];
        if ($memberIds) {
            $rows = FormPanelActivity::find()
                ->select(['member_id', 'cnt' => 'COUNT(*)'])
                ->where(['panel_id' => $panel->id, 'member_id' => $memberIds])
                ->groupBy('member_id')
                ->asArray()
                ->all();
            foreach ($rows as $row) {
                $activityCounts[(int)$row['member_id']] = (int)$row['cnt'];
            }
            $latest = FormPanelActivity::find()
                ->where(['panel_id' => $panel->id, 'member_id' => $memberIds])
                ->with('form')
                ->orderBy(['created_at' => SORT_DESC, 'id' => SORT_DESC])
                ->all();
            foreach ($latest as $row) {
                $mid = (int)$row->member_id;
                if (!isset($latestActivity[$mid])) {
                    $latestActivity[$mid] = $row;
                }
            }
        }

        return $this->renderPanel('view', [
            'panel' => $panel,
            'dataProvider' => $provider,
            'activityCounts' => $activityCounts,
            'latestActivity' => $latestActivity,
            'filters' => ['q' => $q],
            'waves' => (new WaveService())->listWavesForPanel($panel),
            'wavesOnPanel' => \humhub\modules\thiscoveryForms\Module::wavesLiveOnPanelStatic(),
        ]);
    }

    public function actionPanelMember($id)
    {
        $this->requireManagePanels();
        $member = FormPanelMember::findOne((int)$id);
        if (!$member) {
            throw new NotFoundHttpException();
        }
        $panel = $this->findManagedPanel($member->panel_id);
        if ((int)$member->panel_id !== (int)$panel->id) {
            throw new NotFoundHttpException();
        }

        if (Yii::$app->request->isPost) {
            $email = strtolower(trim((string)Yii::$app->request->post('email', $member->email)));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Enter a valid email address.'));
                return $this->redirect(Url::toPanelMember($member, $this->panelContainer()));
            }
            $member->first_name = trim((string)Yii::$app->request->post('first_name', $member->first_name));
            $member->last_name = trim((string)Yii::$app->request->post('last_name', $member->last_name));
            $member->email = $email !== '' ? $email : $member->email;
            $attrs = Yii::$app->request->post('attrs', []);
            if (is_array($attrs)) {
                \humhub\modules\thiscoveryForms\services\PanelFieldService::applyAttrs($member, $attrs, $panel);
            }
            $member->display_name = $member->buildDisplayName();
            $member->save(false);
            Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Member record saved.'));
            return $this->redirect(Url::toPanelMember($member, $this->panelContainer()));
        }

        $activities = FormPanelActivity::find()
            ->where(['member_id' => $member->id, 'panel_id' => $panel->id])
            ->with(['form', 'answer'])
            ->orderBy(['created_at' => SORT_DESC, 'id' => SORT_DESC])
            ->all();

        return $this->renderPanel('member', [
            'panel' => $panel,
            'member' => $member,
            'activities' => $activities,
        ]);
    }

    public function actionPanelDelete($id)
    {
        $panel = $this->findManagedPanel($id);
        if (!Yii::$app->request->isPost) {
            return $this->redirect(Url::toPanelView($panel, $this->panelContainer()));
        }
        $panel->delete();
        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Panel deleted.'));
        return $this->redirect(Url::toPanelIndex($this->panelContainer()));
    }

    public function actionPanelMemberAdd($id)
    {
        $panel = $this->findManagedPanel($id);
        if (!Yii::$app->request->isPost) {
            return $this->redirect(Url::toPanelView($panel, $this->panelContainer()));
        }

        $service = new PanelService();
        $added = $service->addMembersFromGuids($panel, Yii::$app->request->post('userGuids', []));

        $email = strtolower(trim((string)Yii::$app->request->post('email', '')));
        $first = trim((string)Yii::$app->request->post('first_name', ''));
        $last = trim((string)Yii::$app->request->post('last_name', ''));
        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Enter a valid email address.'));
                return $this->redirect(Url::toPanelView($panel, $this->panelContainer()));
            }
            $service->upsertMember($panel, [
                'email' => $email,
                'first' => $first,
                'last' => $last,
                'attrs' => is_array(Yii::$app->request->post('attrs', [])) ? Yii::$app->request->post('attrs', []) : [],
            ]);
            $added++;
        }

        if (!$added) {
            Yii::$app->session->setFlash('error', Yii::t(
                'ThiscoveryFormsModule.base',
                'Select people already on this site, or enter an email address (with optional first and last name).'
            ));
            return $this->redirect(Url::toPanelView($panel, $this->panelContainer()));
        }

        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', '{n,plural,=1{1 member added.} other{# members added.}}', [
            'n' => $added,
        ]));
        return $this->redirect(Url::toPanelView($panel, $this->panelContainer()));
    }

    public function actionPanelMemberRemove($id)
    {
        $panel = $this->findManagedPanel($id);
        if (!Yii::$app->request->isPost) {
            return $this->redirect(Url::toPanelView($panel, $this->panelContainer()));
        }
        $member = FormPanelMember::findOne((int)Yii::$app->request->post('member_id', 0));
        if (!$member || (int)$member->panel_id !== (int)$panel->id) {
            throw new NotFoundHttpException();
        }
        $member->status = FormPanelMember::STATUS_INACTIVE;
        $member->save(false, ['status', 'updated_at']);
        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Member removed.'));
        return $this->redirect(Url::toPanelView($panel, $this->panelContainer()));
    }

    public function actionPanelImport($id)
    {
        $panel = $this->findManagedPanel($id);
        if (!Yii::$app->request->isPost) {
            return $this->redirect(Url::toPanelView($panel, $this->panelContainer()));
        }

        $upload = UploadedFile::getInstanceByName('csv_file');
        $raw = '';
        if ($upload && !$upload->hasError) {
            $raw = (string)@file_get_contents($upload->tempName);
        }
        if ($raw === '') {
            $raw = (string)Yii::$app->request->post('csv_text', '');
        }
        if (trim($raw) === '') {
            Yii::$app->session->setFlash('error', Yii::t('ThiscoveryFormsModule.base', 'Choose a CSV file or paste rows to import.'));
            return $this->redirect(Url::toPanelView($panel, $this->panelContainer()));
        }

        $result = (new PanelService())->importCsv($panel, $raw);
        $parts = [];
        if ($result['added']) {
            $parts[] = Yii::t('ThiscoveryFormsModule.base', '{n,plural,=1{1 added} other{# added}}', ['n' => $result['added']]);
        }
        if ($result['updated']) {
            $parts[] = Yii::t('ThiscoveryFormsModule.base', '{n,plural,=1{1 updated} other{# updated}}', ['n' => $result['updated']]);
        }
        if ($result['skipped']) {
            $parts[] = Yii::t('ThiscoveryFormsModule.base', '{n,plural,=1{1 skipped} other{# skipped}}', ['n' => $result['skipped']]);
        }
        if ($parts) {
            Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'CSV import: {summary}.', [
                'summary' => implode(', ', $parts),
            ]));
        }
        if ($result['errors']) {
            Yii::$app->session->setFlash('error', implode(' ', array_slice($result['errors'], 0, 8)));
        }
        return $this->redirect(Url::toPanelView($panel, $this->panelContainer()));
    }

    public function actionPanelSample($id = null): Response
    {
        $this->requireManagePanels();
        $headers = ['email', 'first_name', 'last_name'];
        $rows = [
            ['alex.example@nhs.net', 'Alex', 'Example'],
            ['sam.example@nhs.net', 'Sam', 'Example'],
        ];
        if ($id) {
            $panel = $this->findManagedPanel($id);
            foreach ($panel->getMemberFields() as $field) {
                $headers[] = $field['key'];
                $rows[0][] = '';
                $rows[1][] = '';
            }
        }
        $csv = implode(',', $headers) . "\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(static function ($col) {
                return strpos((string)$col, ',') !== false ? '"' . $col . '"' : $col;
            }, $row)) . "\n";
        }
        return Yii::$app->response->sendContentAsFile($csv, 'panel-members-sample.csv', [
            'mimeType' => 'text/csv',
        ]);
    }

    public function actionPanelWaveSave($id)
    {
        $panel = $this->findManagedPanel($id);
        if (!Yii::$app->request->isPost || !\humhub\modules\thiscoveryForms\Module::wavesLiveOnPanelStatic()) {
            return $this->redirect(Url::toPanelView($panel, $this->panelContainer()));
        }
        $waves = new WaveService();
        $title = trim((string)Yii::$app->request->post('title', ''));
        $opens = (string)Yii::$app->request->post('opens_at', '');
        $closes = (string)Yii::$app->request->post('closes_at', '');
        $waveId = (int)Yii::$app->request->post('wave_id', 0);
        if ($waveId) {
            $wave = FormWave::findOne(['id' => $waveId, 'panel_id' => $panel->id]);
            if (!$wave) {
                throw new NotFoundHttpException();
            }
            $wave->title = $title ?: $wave->title;
            $wave->opens_at = $waves->normalizeDateTime($opens);
            $wave->closes_at = $waves->normalizeDateTime($closes);
            $wave->save(false);
        } else {
            $waves->createWaveForPanel($panel, $title ?: null, $opens, $closes);
        }
        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Wave saved.'));
        return $this->redirect(Url::toPanelView($panel, $this->panelContainer()));
    }

    public function actionPanelWaveStatus($id)
    {
        $panel = $this->findManagedPanel($id);
        if (!Yii::$app->request->isPost || !\humhub\modules\thiscoveryForms\Module::wavesLiveOnPanelStatic()) {
            return $this->redirect(Url::toPanelView($panel, $this->panelContainer()));
        }
        $wave = FormWave::findOne(['id' => (int)Yii::$app->request->post('wave_id', 0), 'panel_id' => $panel->id]);
        if (!$wave) {
            throw new NotFoundHttpException();
        }
        $previous = $wave->status;
        $waves = new WaveService();
        $waves->setStatus($wave, (string)Yii::$app->request->post('status', ''));
        $wave->refresh();
        if ($previous !== FormWave::STATUS_OPEN && $wave->status === FormWave::STATUS_OPEN) {
            $result = $waves->inviteIfDue($wave);
            if ($result && $result['skipped'] === null && !empty($result['sent'])) {
                Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Wave opened.') . ' ' . Yii::t('ThiscoveryFormsModule.base', 'Sent {n} invitation email(s).', ['n' => $result['sent']]));
                return $this->redirect(Url::toPanelView($panel, $this->panelContainer()));
            }
        }
        Yii::$app->session->setFlash('success', Yii::t('ThiscoveryFormsModule.base', 'Wave updated.'));
        return $this->redirect(Url::toPanelView($panel, $this->panelContainer()));
    }
}
