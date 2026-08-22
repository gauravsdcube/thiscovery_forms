<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormPanel;
use humhub\modules\thiscoveryForms\models\FormWave;
use Yii;

class WaveService
{
    public function wavesLiveOnPanel(): bool
    {
        return Module::wavesLiveOnPanelStatic();
    }

    public function ensureSetup(CustomForm $form): void
    {
        if (!$form->id || !$form->usesWaves() || $form->isTemplate()) {
            return;
        }
        (new PanelService())->ensurePanel($form);
        if (!$this->listWaves($form)) {
            $this->createWave($form, Yii::t('ThiscoveryFormsModule.base', 'Wave 1'));
        }
    }

    public function createWave(CustomForm $form, ?string $title = null, ?string $opensAt = null, ?string $closesAt = null): FormWave
    {
        if ($this->wavesLiveOnPanel()) {
            $panel = (new PanelService())->ensurePanel($form);
            if ($panel) {
                return $this->createWaveForPanel($panel, $title, $opensAt, $closesAt);
            }
        }

        $max = (int)FormWave::find()->where(['form_id' => $form->id])->max('wave_number');
        $wave = new FormWave();
        $wave->form_id = $form->id;
        $wave->panel_id = null;
        $wave->wave_number = $max + 1;
        $wave->title = $title ?: Yii::t('ThiscoveryFormsModule.base', 'Wave {n}', ['n' => $wave->wave_number]);
        $wave->status = FormWave::STATUS_DRAFT;
        $wave->opens_at = $this->normalizeDateTime($opensAt);
        $wave->closes_at = $this->normalizeDateTime($closesAt);
        $wave->save(false);
        return $wave;
    }

    public function createWaveForPanel(FormPanel $panel, ?string $title = null, ?string $opensAt = null, ?string $closesAt = null): FormWave
    {
        $max = (int)FormWave::find()->where(['panel_id' => $panel->id])->max('wave_number');
        $wave = new FormWave();
        $wave->form_id = null;
        $wave->panel_id = $panel->id;
        $wave->wave_number = $max + 1;
        $wave->title = $title ?: Yii::t('ThiscoveryFormsModule.base', 'Wave {n}', ['n' => $wave->wave_number]);
        $wave->status = FormWave::STATUS_DRAFT;
        $wave->opens_at = $this->normalizeDateTime($opensAt);
        $wave->closes_at = $this->normalizeDateTime($closesAt);
        $wave->save(false);
        return $wave;
    }

    public function getCurrentOpen(CustomForm $form): ?FormWave
    {
        foreach ($this->listWaves($form) as $wave) {
            if ($wave->status === FormWave::STATUS_OPEN && $wave->isOpenNow()) {
                return $wave;
            }
        }
        return null;
    }

    /**
     * @return FormWave[]
     */
    public function listWaves(CustomForm $form): array
    {
        if ($this->wavesLiveOnPanel()) {
            $panel = (new PanelService())->getPanel($form);
            return $panel ? $this->listWavesForPanel($panel) : [];
        }
        return FormWave::find()
            ->where(['form_id' => $form->id])
            ->orderBy(['wave_number' => SORT_ASC])
            ->all();
    }

    /**
     * @return FormWave[]
     */
    public function listWavesForPanel(FormPanel $panel): array
    {
        return FormWave::find()
            ->where(['panel_id' => $panel->id])
            ->orderBy(['wave_number' => SORT_ASC])
            ->all();
    }

    public function waveBelongsToForm(FormWave $wave, CustomForm $form): bool
    {
        if ((int)$wave->panel_id) {
            $panel = (new PanelService())->getPanel($form);
            return $panel && (int)$panel->id === (int)$wave->panel_id;
        }
        return (int)$wave->form_id === (int)$form->id;
    }

    public function setStatus(FormWave $wave, string $status): bool
    {
        if (!isset(FormWave::getStatusLabels()[$status])) {
            return false;
        }
        if ($status === FormWave::STATUS_OPEN) {
            $owner = (int)$wave->panel_id
                ? ['panel_id' => (int)$wave->panel_id]
                : ['form_id' => (int)$wave->form_id];
            FormWave::updateAll(
                ['status' => FormWave::STATUS_CLOSED, 'updated_at' => date('Y-m-d H:i:s')],
                [
                    'and',
                    $owner,
                    ['status' => FormWave::STATUS_OPEN],
                    ['<>', 'id', $wave->id],
                ]
            );
            if (!$wave->opens_at) {
                $wave->opens_at = date('Y-m-d H:i:s');
            }
        }
        $wave->status = $status;
        return $wave->save(false, ['status', 'opens_at', 'updated_at']);
    }

    /**
     * Email the panel for a later wave that is now fillable. Wave 1 is left to the manual invite button.
     *
     * @return array{sent:int,failed:int,skipped:?string}|null
     */
    public function inviteIfDue(FormWave $wave, bool $force = false): ?array
    {
        if ((int)$wave->wave_number < 2) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 'first'];
        }
        if ($wave->status !== FormWave::STATUS_OPEN || !$wave->isOpenNow()) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 'scheduled'];
        }
        if ($wave->invited_at) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 'already'];
        }

        $forms = $this->formsForWave($wave);
        if (!$forms) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 'form'];
        }

        $sent = 0;
        $failed = 0;
        $anyReady = false;
        $anyEmailOn = false;
        foreach ($forms as $form) {
            if ($form->isTemplate() || !$form->usesWaves()) {
                continue;
            }
            if (!$form->isOpen()) {
                continue;
            }
            $anyReady = true;
            if (!$force && !$form->emailsOnWaveOpen()) {
                continue;
            }
            $anyEmailOn = true;
            $result = (new PanelService())->inviteAll($form, $wave);
            $sent += (int)($result['sent'] ?? 0);
            $failed += (int)($result['failed'] ?? 0);
        }

        if (!$anyReady) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 'form'];
        }
        if (!$force && !$anyEmailOn) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 'off'];
        }

        $wave->invited_at = date('Y-m-d H:i:s');
        $wave->save(false, ['invited_at', 'updated_at']);
        return ['sent' => $sent, 'failed' => $failed, 'skipped' => null];
    }

    /**
     * @return CustomForm[]
     */
    public function formsForWave(FormWave $wave): array
    {
        if ((int)$wave->panel_id) {
            $panel = $wave->panel ?: FormPanel::findOne((int)$wave->panel_id);
            return $panel ? (new PanelService())->waveFormsForPanel($panel) : [];
        }
        $form = $wave->form;
        return $form ? [$form] : [];
    }

    /**
     * Hourly: send invites for scheduled later waves that have now opened.
     */
    public function dispatchDueInvites(): void
    {
        $waves = FormWave::find()
            ->where(['status' => FormWave::STATUS_OPEN])
            ->andWhere(['invited_at' => null])
            ->andWhere(['>=', 'wave_number', 2])
            ->all();
        foreach ($waves as $wave) {
            try {
                $this->inviteIfDue($wave);
            } catch (\Throwable $e) {
                Yii::error('Thiscovery Forms wave invite cron failed: ' . $e->getMessage(), 'thiscovery-forms');
            }
        }
    }

    public function normalizeDateTime(?string $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}
