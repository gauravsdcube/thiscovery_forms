<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\FormLlmUsage;
use humhub\modules\thiscoveryForms\Module;
use Yii;

class LlmUsageLogger
{
    /**
     * @param array<string, mixed> $data
     */
    public function log(array $data): void
    {
        $module = Yii::$app->getModule('thiscovery-forms');
        $inRate = $module instanceof Module ? (float)$module->settings->get(Module::SETTING_LLM_COST_INPUT, 0.15) : 0.15;
        $outRate = $module instanceof Module ? (float)$module->settings->get(Module::SETTING_LLM_COST_OUTPUT, 0.60) : 0.60;
        $prompt = (int)($data['prompt_tokens'] ?? 0);
        $completion = (int)($data['completion_tokens'] ?? 0);
        $cost = ($prompt / 1000) * $inRate + ($completion / 1000) * $outRate;

        $row = new FormLlmUsage();
        $row->created_at = date('Y-m-d H:i:s');
        $row->user_id = Yii::$app->user->isGuest ? null : (int)Yii::$app->user->id;
        $row->form_id = isset($data['form_id']) && $data['form_id'] ? (int)$data['form_id'] : null;
        $row->session_id = $data['session_id'] ?? null;
        $row->purpose = (string)($data['purpose'] ?? 'map');
        $row->provider = (string)($data['provider'] ?? 'openai');
        $row->model = (string)($data['model'] ?? '');
        $row->prompt_tokens = $prompt;
        $row->completion_tokens = $completion;
        $row->total_tokens = $prompt + $completion;
        $row->estimated_cost = round($cost, 6);
        $row->latency_ms = (int)($data['latency_ms'] ?? 0);
        $row->success = !empty($data['success']) ? 1 : 0;
        $row->error_code = $data['error_code'] ?? null;
        $row->save(false);
    }

    /**
     * @return array{calls: int, tokens: int, cost: float, warn: bool, warn_threshold: float|null}
     */
    public function monthSummary(?int $userId = null): array
    {
        $start = date('Y-m-01 00:00:00');
        $q = FormLlmUsage::find()->where(['>=', 'created_at', $start]);
        if ($userId) {
            $q->andWhere(['user_id' => $userId]);
        }
        $calls = (int)$q->count();
        $tokens = (int)$q->sum('total_tokens');
        $cost = (float)$q->sum('estimated_cost');

        $module = Yii::$app->getModule('thiscovery-forms');
        $warnRaw = $module instanceof Module ? $module->settings->get(Module::SETTING_LLM_WARN_MONTHLY_COST, '') : '';
        $threshold = ($warnRaw === '' || $warnRaw === null) ? null : (float)$warnRaw;
        $warn = $threshold !== null && $cost >= $threshold;

        return [
            'calls' => $calls,
            'tokens' => $tokens,
            'cost' => round($cost, 4),
            'warn' => $warn,
            'warn_threshold' => $threshold,
        ];
    }

    /**
     * @return array{tokens: int, cost: float}
     */
    public function sessionSummary(string $sessionId): array
    {
        $q = FormLlmUsage::find()->where(['session_id' => $sessionId]);
        return [
            'tokens' => (int)$q->sum('total_tokens'),
            'cost' => round((float)$q->sum('estimated_cost'), 4),
        ];
    }
}
