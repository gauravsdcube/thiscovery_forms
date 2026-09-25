<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\content\models\Content;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\Module;
use Yii;
use yii\base\Exception;

/**
 * Session + Draft creation for survey-from-brief.
 *
 * State is stored in cache (not PHP session) so background LLM jobs do not
 * block status polling via the session lock.
 */
class SurveyFromBriefService
{
    public const SESSION_KEY = 'cf_from_brief';
    private const CACHE_TTL = 7200;

    public function newSessionId(): string
    {
        return bin2hex(random_bytes(16));
    }

    protected function cacheKey(string $sessionId): string
    {
        $uid = (int)(Yii::$app->user->id ?? 0);
        return 'cf_from_brief_u' . $uid . '_' . $sessionId;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveState(string $sessionId, array $data): void
    {
        $prev = $this->getState($sessionId) ?? [];
        $merged = array_merge($prev, $data, ['updated_at' => time()]);
        Yii::$app->cache->set($this->cacheKey($sessionId), $merged, self::CACHE_TTL);

        // Keep a lightweight pointer in the PHP session (no large payloads / no lock during jobs).
        try {
            $ptrs = Yii::$app->session->get(self::SESSION_KEY . '_ids', []);
            if (!is_array($ptrs)) {
                $ptrs = [];
            }
            $ptrs[$sessionId] = time();
            if (count($ptrs) > 8) {
                asort($ptrs);
                $ptrs = array_slice($ptrs, -8, null, true);
            }
            Yii::$app->session->set(self::SESSION_KEY . '_ids', $ptrs);
        } catch (\Throwable $e) {
            // Session may be closed during background work; cache write is enough.
        }
    }

    public function getState(string $sessionId): ?array
    {
        $cached = Yii::$app->cache->get($this->cacheKey($sessionId));
        if (is_array($cached)) {
            return $cached;
        }

        // Migrate legacy PHP-session payloads if present.
        try {
            $all = Yii::$app->session->get(self::SESSION_KEY, []);
            if (is_array($all) && isset($all[$sessionId]) && is_array($all[$sessionId])) {
                $legacy = $all[$sessionId];
                Yii::$app->cache->set($this->cacheKey($sessionId), $legacy, self::CACHE_TTL);
                return $legacy;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    public function clearState(string $sessionId): void
    {
        Yii::$app->cache->delete($this->cacheKey($sessionId));
        try {
            $ptrs = Yii::$app->session->get(self::SESSION_KEY . '_ids', []);
            if (is_array($ptrs) && isset($ptrs[$sessionId])) {
                unset($ptrs[$sessionId]);
                Yii::$app->session->set(self::SESSION_KEY . '_ids', $ptrs);
            }
            $all = Yii::$app->session->get(self::SESSION_KEY, []);
            if (is_array($all) && isset($all[$sessionId])) {
                unset($all[$sessionId]);
                Yii::$app->session->set(self::SESSION_KEY, $all);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     */
    public function createDraft($container, string $title, array $fields, ?int $folderId = null): CustomForm
    {
        $form = $container
            ? new CustomForm($container)
            : new CustomForm();
        if (!$form->canCreate()) {
            throw new Exception(Yii::t('ThiscoveryFormsModule.base', 'You cannot create forms here.'));
        }
        $form->kind = CustomForm::KIND_SURVEY;
        $form->applyKindDefaults();
        $form->title = $title !== '' ? mb_substr($title, 0, 255) : Yii::t('ThiscoveryFormsModule.base', 'Survey from brief');
        $form->status = CustomForm::STATUS_DRAFT;
        if ($folderId) {
            $form->folder_id = $folderId;
        }
        $form->content->visibility = Content::VISIBILITY_PRIVATE;
        if (!$form->save()) {
            throw new Exception(Yii::t('ThiscoveryFormsModule.base', 'Could not create the draft form.'));
        }

        $error = (new QuestionImportExportService())->appendFieldPayloads($form, $fields, true);
        if ($error) {
            throw new Exception($error);
        }

        return $form;
    }

    public function costWarningBanner(): ?string
    {
        $summary = (new LlmUsageLogger())->monthSummary();
        if (!$summary['warn'] || $summary['warn_threshold'] === null) {
            return null;
        }
        return Yii::t(
            'ThiscoveryFormsModule.base',
            'Estimated LLM cost this month is ${cost} (warning threshold ${threshold}). This is informational only and does not block generation.',
            [
                'cost' => number_format($summary['cost'], 2),
                'threshold' => number_format((float)$summary['warn_threshold'], 2),
            ]
        );
    }
}
