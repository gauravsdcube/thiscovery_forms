<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormWave;
use Yii;

class WaveService
{
    public function ensureSetup(CustomForm $form): void
    {
        if (!$form->id || !$form->isLongitudinal() || $form->isTemplate()) {
            return;
        }
        (new PanelService())->ensurePanel($form);
        if (!FormWave::find()->where(['form_id' => $form->id])->exists()) {
            $this->createWave($form, Yii::t('ThiscoveryFormsModule.base', 'Wave 1'));
        }
    }

    public function createWave(CustomForm $form, ?string $title = null, ?string $opensAt = null, ?string $closesAt = null): FormWave
    {
        $max = (int)FormWave::find()->where(['form_id' => $form->id])->max('wave_number');
        $wave = new FormWave();
        $wave->form_id = $form->id;
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
        $waves = FormWave::find()
            ->where(['form_id' => $form->id, 'status' => FormWave::STATUS_OPEN])
            ->orderBy(['wave_number' => SORT_DESC])
            ->all();
        foreach ($waves as $wave) {
            if ($wave->isOpenNow()) {
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
        return FormWave::find()
            ->where(['form_id' => $form->id])
            ->orderBy(['wave_number' => SORT_ASC])
            ->all();
    }

    public function setStatus(FormWave $wave, string $status): bool
    {
        if (!isset(FormWave::getStatusLabels()[$status])) {
            return false;
        }
        if ($status === FormWave::STATUS_OPEN) {
            FormWave::updateAll(
                ['status' => FormWave::STATUS_CLOSED, 'updated_at' => date('Y-m-d H:i:s')],
                [
                    'and',
                    ['form_id' => $wave->form_id],
                    ['status' => FormWave::STATUS_OPEN],
                    ['<>', 'id', $wave->id],
                ]
            );
            if (!$wave->opens_at) {
                $wave->opens_at = date('Y-m-d H:i:s');
            }
        }
        $wave->status = $status;
        $ok = $wave->save(false, ['status', 'opens_at', 'updated_at']);
        return $ok;
    }

    /**
     * Email the panel for a later wave that is now fillable. Wave 1 is left to the manual invite button.
     *
     * @return array{sent:int,failed:int,skipped:?string}|null
     */
    public function inviteIfDue(FormWave $wave, bool $force = false): ?array
    {
        $form = $wave->form;
        if (!$form || $form->isTemplate() || !$form->isLongitudinal()) {
            return null;
        }
        if (!$force && !$form->emailsOnWaveOpen()) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 'off'];
        }
        if ((int)$wave->wave_number < 2) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 'first'];
        }
        if ($wave->status !== FormWave::STATUS_OPEN || !$wave->isOpenNow()) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 'scheduled'];
        }
        if (!$form->isOpen()) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 'form'];
        }
        if ($wave->invited_at) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 'already'];
        }

        $result = (new PanelService())->inviteAll($form, $wave);
        $wave->invited_at = date('Y-m-d H:i:s');
        $wave->save(false, ['invited_at', 'updated_at']);
        $result['skipped'] = null;
        return $result;
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
