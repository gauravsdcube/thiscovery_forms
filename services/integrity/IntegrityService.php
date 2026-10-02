<?php

namespace humhub\modules\thiscoveryForms\services\integrity;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAccessToken;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormIntegrityAudit;
use humhub\modules\thiscoveryForms\models\FormIntegrityMeta;
use humhub\modules\thiscoveryForms\services\FillContext;
use humhub\modules\thiscoveryForms\services\FormPager;
use humhub\modules\thiscoveryForms\services\LogicEngine;
use humhub\modules\thiscoveryForms\services\RandomisationService;
use Yii;

/**
 * Collects multi-signal quality metadata. Never auto-rejects from a single indicator.
 */
class IntegrityService
{
    public const HONEYPOT_NAME = 'cf_hp_company_url';
    public const TIMING_NAME = 'cf_integrity_timing';
    public const SESSION_PREFIX = 'cf_integrity_session_';
    public const START_PREFIX = 'cf_integrity_start_';
    public const CAPTCHA_REQUIRED_PREFIX = 'cf_integrity_captcha_';
    public const OPEN_OK_PREFIX = 'cf_integrity_open_ok_';
    public const OPEN_CAPTCHA_REQUIRED_PREFIX = 'cf_integrity_open_captcha_';

    public function settings(?CustomForm $form): array
    {
        return IntegritySettings::forForm($form);
    }

    public function isEnabled(?CustomForm $form): bool
    {
        return IntegritySettings::isOn($this->settings($form), 'enabled');
    }

    public function captchaAvailable(array $cfg): bool
    {
        return IntegritySettings::captchaAvailable($cfg);
    }

    public function sessionKey(CustomForm $form): string
    {
        return self::SESSION_PREFIX . (int)$form->id;
    }

    public function onFillOpen(CustomForm $form, ?FormAnswer $answer = null): void
    {
        if (!$this->isEnabled($form)) {
            return;
        }
        Yii::$app->session->set($this->sessionKey($form), Yii::$app->security->generateRandomString(16));
        $startKey = self::START_PREFIX . (int)$form->id;
        if (!Yii::$app->session->get($startKey)) {
            Yii::$app->session->set($startKey, date('Y-m-d H:i:s'));
        }
        if ($answer) {
            $meta = $this->ensureMeta($form, $answer);
            if (!$meta->started_at) {
                $meta->started_at = Yii::$app->session->get($startKey) ?: date('Y-m-d H:i:s');
                $meta->session_established = 1;
                $meta->save(false);
            }
        }
    }

    /**
     * Starts the fill session when allowed, or signals that an open-rate CAPTCHA is required.
     *
     * @return string 'ok'|'challenge'
     */
    public function prepareFillOpen(CustomForm $form, ?FormAnswer $existing = null): string
    {
        if ($this->isOpenCaptchaCleared($form)) {
            $this->onFillOpen($form, $existing);
            return 'ok';
        }
        $cfg = $this->settings($form);
        if (IntegritySettings::isOn($cfg, 'open_captcha') && $this->captchaAvailable($cfg)) {
            // Sticky challenge: do not re-increment the open-rate counter on refresh.
            if ($this->isOpenCaptchaRequired($form)) {
                return 'challenge';
            }
            if ($this->isOpenRateLimited($form, $cfg, true)) {
                $this->markOpenCaptchaRequired($form);
                return 'challenge';
            }
        }
        $this->onFillOpen($form, $existing);
        return 'ok';
    }

    public function needsOpenCaptchaChallenge(CustomForm $form): bool
    {
        if ($this->isOpenCaptchaCleared($form)) {
            return false;
        }
        $cfg = $this->settings($form);
        if (!IntegritySettings::isOn($cfg, 'open_captcha') || !$this->captchaAvailable($cfg)) {
            return false;
        }
        return $this->isOpenCaptchaRequired($form)
            || $this->isOpenRateLimited($form, $cfg, false);
    }

    public function isOpenCaptchaCleared(CustomForm $form): bool
    {
        return (bool)Yii::$app->session->get(self::OPEN_OK_PREFIX . (int)$form->id);
    }

    public function markOpenCaptchaCleared(CustomForm $form): void
    {
        Yii::$app->session->set(self::OPEN_OK_PREFIX . (int)$form->id, 1);
        $this->clearOpenCaptchaRequired($form);
    }

    public function clearOpenCaptchaCleared(CustomForm $form): void
    {
        Yii::$app->session->remove(self::OPEN_OK_PREFIX . (int)$form->id);
    }

    public function isOpenCaptchaRequired(CustomForm $form): bool
    {
        return (bool)Yii::$app->session->get(self::OPEN_CAPTCHA_REQUIRED_PREFIX . (int)$form->id);
    }

    public function markOpenCaptchaRequired(CustomForm $form): void
    {
        Yii::$app->session->set(self::OPEN_CAPTCHA_REQUIRED_PREFIX . (int)$form->id, 1);
    }

    public function clearOpenCaptchaRequired(CustomForm $form): void
    {
        Yii::$app->session->remove(self::OPEN_CAPTCHA_REQUIRED_PREFIX . (int)$form->id);
    }

    public function isOpenRateLimited(CustomForm $form, array $cfg, bool $increment): bool
    {
        $ip = (string)Yii::$app->request->userIP;
        $session = Yii::$app->session->id ?: 'none';
        $key = 'cf-int-open-rate-' . $form->id . '-' . IntegritySettings::hashValue($ip . '|' . $session);
        $limit = max(1, (int)($cfg['open_rate_count'] ?? 30));
        $window = max(1, (int)($cfg['open_rate_window'] ?? 10)) * 60;
        $cache = Yii::$app->cache;
        $count = (int)$cache->get($key);
        if ($increment) {
            $count++;
            $cache->set($key, $count, $window);
        }
        return $count > $limit;
    }

    public function verifyOpenCaptcha(CustomForm $form, array $post): bool
    {
        $cfg = $this->settings($form);
        if (!$this->verifyCaptcha($cfg, $post)) {
            $this->markOpenCaptchaRequired($form);
            return false;
        }
        $this->markOpenCaptchaCleared($form);
        return true;
    }

    public function onProgress(CustomForm $form, FormAnswer $answer, array $post): void
    {
        if (!$this->isEnabled($form)) {
            return;
        }
        $meta = $this->ensureMeta($form, $answer);
        if (!$meta->started_at) {
            $meta->started_at = Yii::$app->session->get(self::START_PREFIX . (int)$form->id)
                ?: ($answer->created_at ?: date('Y-m-d H:i:s'));
        }
        $this->applyClientTimings($meta, $post);
        $meta->session_established = Yii::$app->session->get($this->sessionKey($form)) ? 1 : (int)$meta->session_established;
        $meta->save(false);
    }

    /**
     * @return string|null error when the respondent may not open/submit this form
     */
    public function checkAccess(CustomForm $form, FillContext $ctx): ?string
    {
        return $this->gateAccess($form, $this->settings($form), $ctx);
    }
    /**
     * @param bool $poll true for the poll embed. It cannot show a CAPTCHA, so polls are gated by
     *                   access and rate limit only; a CAPTCHA would reject every vote (V3-34).
     */
    public function gateSubmit(CustomForm $form, array $post, FillContext $ctx, bool $poll = false): ?string
    {
        $cfg = $this->settings($form);
        $accessError = $this->gateAccess($form, $cfg, $ctx, true);
        if ($accessError) {
            $this->discardCaptchaResult($form);
            return $accessError;
        }
        $integrityOn = IntegritySettings::isOn($cfg, 'enabled');
        if ($integrityOn && IntegritySettings::isOn($cfg, 'rate_limiting') && $this->isRateLimited($form, $cfg, true, $ctx)) {
            $this->refundAccessToken($form, $ctx);
            return Yii::t('ThiscoveryFormsModule.base', 'Too many submissions from this connection. Please wait a few minutes and try again.');
        }
        if ($poll) {
            $this->discardCaptchaResult($form);
            return null;
        }
        if (!IntegritySettings::isOn($cfg, 'captcha') || !$this->captchaAvailable($cfg)) {
            $this->clearCaptchaRequired($form);
            $this->discardCaptchaResult($form);
        }
        // Suspicious signals (honeypot / session) only exist when integrity scoring is on.
        $suspicious = $integrityOn && $this->looksSuspiciousBeforeSave($form, $post, $cfg);
        // Sticky session bit: honeypot / missing session are only known at submit,
        // so the next render must still show CAPTCHA and require a pass.
        $needCaptcha = $this->shouldShowCaptcha($cfg, $suspicious || $this->isCaptchaRequired($form));
        if ($needCaptcha) {
            $passed = $this->verifyCaptcha($cfg, $post);
            if (!$passed) {
                $this->markCaptchaRequired($form);
                $this->discardCaptchaResult($form);
                $this->refundAccessToken($form, $ctx);
                return Yii::t('ThiscoveryFormsModule.base', 'Please complete the verification check and try again.');
            }
            $this->clearCaptchaRequired($form);
            $this->rememberCaptchaResult($form, true, true);
        } else {
            $this->discardCaptchaResult($form);
        }
        return null;
    }

    public function onComplete(CustomForm $form, FormAnswer $answer, array $post, FillContext $ctx): ?FormIntegrityMeta
    {
        $cfg = $this->settings($form);
        $rawToken = $ctx->accessToken;
        if (!IntegritySettings::isOn($cfg, 'enabled')) {
            if (!$ctx->accessTokenConsumed) {
                $ctx->accessTokenConsumed = $this->consumeAccessToken($form, $rawToken);
            }
            Yii::$app->session->remove(self::START_PREFIX . (int)$form->id);
            Yii::$app->session->remove($this->captchaResultKey($form));
            return null;
        }

        $meta = $this->ensureMeta($form, $answer);
        $now = date('Y-m-d H:i:s');

        if (!$meta->started_at) {
            $meta->started_at = Yii::$app->session->get(self::START_PREFIX . (int)$form->id) ?: $answer->created_at;
        }
        $meta->completed_at = $answer->submitted_at ?: $now;
        $startTs = strtotime((string)$meta->started_at) ?: strtotime((string)$answer->created_at);
        $endTs = strtotime((string)$meta->completed_at) ?: time();
        $meta->duration_seconds = max(0, $endTs - $startTs);
        $this->applyClientTimings($meta, $post);

        $sessionOk = (bool)Yii::$app->session->get($this->sessionKey($form));
        $meta->session_established = $sessionOk ? 1 : 0;
        $meta->access_mode = (string)($cfg['access_mode'] ?? IntegritySettings::ACCESS_PUBLIC);
        $meta->honeypot_triggered = $this->honeypotTriggered($post) ? 1 : 0;
        $meta->rate_limited = $this->isRateLimited($form, $cfg, false) ? 1 : 0;

        $userAgent = (string)Yii::$app->request->userAgent;
        $sessionId = Yii::$app->session->id ?: Yii::$app->security->generateRandomString(16);
        $ip = (string)Yii::$app->request->userIP;
        if (IntegritySettings::isOn($cfg, 'hash_ip')) {
            $hashes = IntegritySettings::hashIp($ip);
            $meta->ip_hash = $hashes['ip_hash'];
            $meta->ip_network_hash = $hashes['ip_network_hash'];
        } else {
            $meta->ip_hash = null;
            $meta->ip_network_hash = null;
        }
        $meta->session_hash = IntegritySettings::hashValue('sess:' . $sessionId);
        $meta->user_agent_hash = $userAgent !== '' ? IntegritySettings::hashValue('ua:' . $userAgent) : null;

        if ($rawToken !== '') {
            $meta->access_token_hash = IntegritySettings::hashValue('tok:' . $rawToken);
        }

        $recorded = $this->takeCaptchaResult($form);
        if ($recorded !== null && $recorded['shown']) {
            $meta->captcha_shown = 1;
            $meta->captcha_passed = $recorded['passed'] ? 1 : 0;
        } else {
            $meta->captcha_shown = 0;
            $meta->captcha_passed = null;
        }

        $flags = [];
        $skipScores = in_array((string)$answer->outcome, [FormAnswer::OUTCOME_NOT_CONSENTED, FormAnswer::OUTCOME_SCREENED_OUT, FormAnswer::OUTCOME_OVER_QUOTA], true);
        $scores = [
            'bot' => 0.0, 'duplicate' => 0.0, 'speed' => 0.0, 'straightline' => 0.0,
            'attention' => 0.0, 'consistency' => 0.0, 'freetext' => 0.0, 'similarity' => 0.0,
        ];

        if (!$skipScores && IntegritySettings::isOn($cfg, 'bot_protection')) {
            [$scores['bot'], $botFlags] = $this->analyseBot($meta, $cfg);
            $flags = array_merge($flags, $botFlags);
        }
        if (!$skipScores && IntegritySettings::isOn($cfg, 'duplicate_detection')) {
            [$scores['duplicate'], $dupFlags] = $this->analyseDuplicate($form, $answer, $meta, $cfg);
            $flags = array_merge($flags, $dupFlags);
        }
        $values = $answer->getValuesMap();
        $shown = $this->shownFieldIds($form, $values);
        $meta->shown_question_count = (new \humhub\modules\thiscoveryForms\services\LoopService())->shownQuestionCount($form, $values, $shown);
        if (!$skipScores && IntegritySettings::isOn($cfg, 'speed_detection')) {
            $meta->median_seconds = $this->medianSecondsPerQuestion($form, (int)$answer->id);
            [$scores['speed'], $speedFlags] = $this->analyseSpeed($meta, $cfg);
            $flags = array_merge($flags, $speedFlags);
        }
        if (!$skipScores && IntegritySettings::isOn($cfg, 'straightline_detection')) {
            [$scores['straightline'], $slFlags] = $this->analyseStraightline($form, $values, $cfg, $shown);
            $flags = array_merge($flags, $slFlags);
        }
        if (!$skipScores && IntegritySettings::isOn($cfg, 'attention_checks')) {
            [$scores['attention'], $attFlags] = $this->analyseAttention($form, $values, $shown);
            $flags = array_merge($flags, $attFlags);
        }
        if (!$skipScores && IntegritySettings::isOn($cfg, 'consistency_checks')) {
            [$scores['consistency'], $conFlags] = $this->analyseConsistency($form, $values, $cfg, $shown);
            $flags = array_merge($flags, $conFlags);
        }
        if (!$skipScores && IntegritySettings::isOn($cfg, 'freetext_checks')) {
            [$scores['freetext'], $ftFlags] = $this->analyseFreetext($form, $values, $cfg, $shown);
            $flags = array_merge($flags, $ftFlags);
        }
        if (!$skipScores && IntegritySettings::isOn($cfg, 'similarity_detection')) {
            [$scores['similarity'], $simFlags, $similarIds] = $this->analyseSimilarity($form, $answer, $values, $cfg);
            $flags = array_merge($flags, $simFlags);
            $meta->similar_answer_ids_json = $similarIds ? json_encode($similarIds) : null;
        }
        $similarPending = $similarIds ?? [];

        $meta->bot_score = $scores['bot'];
        $meta->duplicate_score = $scores['duplicate'];
        $meta->speed_score = $scores['speed'];
        $meta->straightline_score = $scores['straightline'];
        $meta->attention_score = $scores['attention'];
        $meta->consistency_score = $scores['consistency'];
        $meta->freetext_score = $scores['freetext'];
        $meta->similarity_score = $scores['similarity'];
        $meta->setFlags($flags);

        if ($skipScores) {
            $meta->analysis_status = FormIntegrityMeta::ANALYSIS_EXCLUDED;
            $meta->exclusion_reason = (string)$answer->outcome;
        } elseif (IntegritySettings::isOn($cfg, 'integrity_scoring')) {
            $this->applyScoreStatus($form, (int)$answer->id, $meta, $scores, $cfg);
        } else {
            $meta->overall_score = 100;
            if (!$meta->status_override) {
                $meta->integrity_status = FormIntegrityMeta::STATUS_TRUSTED;
                $meta->analysis_status = FormIntegrityMeta::ANALYSIS_INCLUDED;
            }
        }

        if (!$ctx->accessTokenConsumed) {
            $ctx->accessTokenConsumed = $this->consumeAccessToken($form, $rawToken);
        }
        $meta->save(false);
        if ((string)$meta->analysis_status === FormIntegrityMeta::ANALYSIS_EXCLUDED) {
            (new \humhub\modules\thiscoveryForms\services\QuotaService())->releaseExcluded($form, $answer);
        }
        if (!$skipScores && IntegritySettings::isOn($cfg, 'similarity_detection')) {
            // The earlier response of a similar pair is flagged too (INT-3), and the comparison
            // against the whole form runs in the background when the queue is available.
            $this->linkSimilar($form, (int)$answer->id, $similarPending, $cfg);
            $this->queueSimilarityScan($form, $answer);
        }
        $this->clearCaptchaRequired($form);
        $this->clearOpenCaptchaRequired($form);
        Yii::$app->session->remove(self::START_PREFIX . (int)$form->id);
        return $meta;
    }

    public function overrideStatus(
        CustomForm $form,
        FormIntegrityMeta $meta,
        string $status,
        string $analysis,
        ?string $reason
    ): void {
        $reason = trim((string)$reason);
        $fromStatus = $meta->getEffectiveStatus();
        $fromAnalysis = $meta->analysis_status;
        $meta->status_override = $status;
        $meta->integrity_status = $status;
        $meta->analysis_status = $analysis;
        if ($analysis === FormIntegrityMeta::ANALYSIS_EXCLUDED) {
            $meta->exclusion_reason = $reason;
        } elseif ($fromAnalysis === FormIntegrityMeta::ANALYSIS_EXCLUDED && $analysis !== FormIntegrityMeta::ANALYSIS_EXCLUDED) {
            $meta->exclusion_reason = $reason !== '' ? $reason : null;
        }
        $meta->save(false);
        if ($analysis === FormIntegrityMeta::ANALYSIS_EXCLUDED) {
            $answer = FormAnswer::findOne((int)$meta->answer_id);
            if ($answer) {
                (new \humhub\modules\thiscoveryForms\services\QuotaService())->releaseExcluded($form, $answer);
            }
        } elseif ($fromAnalysis === FormIntegrityMeta::ANALYSIS_EXCLUDED) {
            // Reinstated: the quota place comes back (V3-47).
            $answer = FormAnswer::findOne((int)$meta->answer_id);
            if ($answer) {
                (new \humhub\modules\thiscoveryForms\services\QuotaService())->restoreReinstated($form, $answer);
            }
        }
        FormIntegrityAudit::record($form->id, $meta->answer_id, 'status_override', $fromStatus, $status, $reason);
        if ($fromAnalysis !== $analysis) {
            FormIntegrityAudit::record($form->id, $meta->answer_id, 'analysis_status', $fromAnalysis, $analysis, $reason);
        }
    }

    public function addNote(FormIntegrityMeta $meta, string $note): void
    {
        $note = trim($note);
        if ($note === '') {
            return;
        }
        $stamp = date('Y-m-d H:i') . ' — ' . (Yii::$app->user->identity->displayName ?? 'Admin');
        $meta->notes = trim((string)$meta->notes . "\n[" . $stamp . "]\n" . $note);
        $meta->save(false);
        FormIntegrityAudit::record((int)$meta->form_id, (int)$meta->answer_id, 'note', null, null, mb_strimwidth($note, 0, 240, '…'));
    }

    public function ensureMeta(CustomForm $form, FormAnswer $answer): FormIntegrityMeta
    {
        $meta = FormIntegrityMeta::findOne(['answer_id' => $answer->id]);
        if ($meta) {
            return $meta;
        }
        $meta = new FormIntegrityMeta();
        $meta->answer_id = (int)$answer->id;
        $meta->form_id = (int)$form->id;
        $meta->overall_score = 100;
        $meta->integrity_status = FormIntegrityMeta::STATUS_TRUSTED;
        $meta->analysis_status = FormIntegrityMeta::ANALYSIS_INCLUDED;
        $meta->save(false);
        return $meta;
    }

    public function dashboard(CustomForm $form): array
    {
        $q = FormIntegrityMeta::find()->alias('m')
            ->innerJoin('custom_form_answer a', 'a.id = m.answer_id')
            ->andWhere(['m.form_id' => $form->id, 'a.is_test' => 0, 'a.status' => FormAnswer::STATUS_COMPLETE]);
        $total = (int)(clone $q)->count();
        $avg = $total ? (float)(clone $q)->average('m.overall_score') : 0;
        $byStatus = [];
        foreach (array_keys(FormIntegrityMeta::statusLabels()) as $status) {
            $byStatus[$status] = (int)(clone $q)->andWhere(['m.integrity_status' => $status])->count();
            $byStatus[$status] += (int)FormIntegrityMeta::find()->alias('m')
                ->innerJoin('custom_form_answer a', 'a.id = m.answer_id')
                ->andWhere(['m.form_id' => $form->id, 'a.is_test' => 0, 'a.status' => FormAnswer::STATUS_COMPLETE, 'm.status_override' => $status])
                ->andWhere(['<>', 'm.integrity_status', $status])
                ->count();
        }
        $flagCounts = [
            'speed' => 0, 'duplicate' => 0, 'straightline' => 0, 'attention' => 0,
            'consistency' => 0, 'freetext' => 0, 'bot' => 0, 'similarity' => 0,
        ];
        /** @var FormIntegrityMeta $row */
        foreach ((clone $q)->each(100) as $row) {
            $seen = [];
            foreach ($row->getFlags() as $flag) {
                $cat = (string)($flag['category'] ?? '');
                if ($cat === 'attention' && ($flag['code'] ?? '') !== 'failed') {
                    continue;
                }
                // Information-only flags (same address on a shared network) are not counted (INT-7).
                if (array_key_exists('severity', $flag) && (int)$flag['severity'] === 0) {
                    continue;
                }
                if (isset($flagCounts[$cat]) && empty($seen[$cat])) {
                    $flagCounts[$cat]++;
                    $seen[$cat] = true;
                }
            }
        }
        return [
            'total' => $total,
            'averageScore' => round($avg, 1),
            'byStatus' => $byStatus,
            'flags' => $flagCounts,
            'clusters' => $this->similarityClusters($form, clone $q),
        ];
    }

    /**
     * Groups of responses linked by similar_answer_ids (connected components).
     *
     * @param \yii\db\ActiveQuery $baseQuery already scoped to complete non-test answers for the form
     * @return array<int, array{ids: int[], size: int}>
     */
    private function similarityClusters(CustomForm $form, $baseQuery): array
    {
        $parent = [];
        $find = static function (int $x) use (&$parent, &$find): int {
            if (!isset($parent[$x])) {
                $parent[$x] = $x;
            }
            if ($parent[$x] !== $x) {
                $parent[$x] = $find($parent[$x]);
            }
            return $parent[$x];
        };
        $union = static function (int $a, int $b) use (&$find, &$parent): void {
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[$rb] = $ra;
            }
        };

        /** @var FormIntegrityMeta $row */
        foreach ($baseQuery->andWhere(['IS NOT', 'm.similar_answer_ids_json', null])->each(100) as $row) {
            $aid = (int)$row->answer_id;
            $ids = $row->getSimilarAnswerIds();
            if ($ids === []) {
                continue;
            }
            $find($aid);
            foreach ($ids as $oid) {
                $oid = (int)$oid;
                if ($oid < 1 || $oid === $aid) {
                    continue;
                }
                $union($aid, $oid);
            }
        }

        $groups = [];
        foreach ($parent as $id => $_) {
            $root = $find((int)$id);
            $groups[$root][] = (int)$id;
        }
        $clusters = [];
        foreach ($groups as $ids) {
            $ids = array_values(array_unique($ids));
            sort($ids);
            if (count($ids) < 2) {
                continue;
            }
            $clusters[] = ['ids' => $ids, 'size' => count($ids)];
        }
        usort($clusters, static fn($a, $b) => $b['size'] <=> $a['size']);
        return array_slice($clusters, 0, 25);
    }

    public function shouldShowCaptchaWidget(CustomForm $form): bool
    {
        $cfg = $this->settings($form);
        if (!IntegritySettings::isOn($cfg, 'captcha') || !$this->captchaAvailable($cfg)) {
            return false;
        }
        $mode = $cfg['captcha_mode'] ?? IntegritySettings::CAPTCHA_SUSPICIOUS;
        if ($mode === IntegritySettings::CAPTCHA_OFF) {
            return false;
        }
        if ($mode === IntegritySettings::CAPTCHA_ALWAYS) {
            return true;
        }
        // Sticky after a blocked submit, or already suspicious on this request.
        return $this->isCaptchaRequired($form)
            || $this->looksSuspiciousBeforeSave($form, Yii::$app->request->post(), $cfg);
    }

    public function isCaptchaRequired(CustomForm $form): bool
    {
        return (bool)Yii::$app->session->get($this->captchaRequiredKey($form));
    }

    public function markCaptchaRequired(CustomForm $form): void
    {
        Yii::$app->session->set($this->captchaRequiredKey($form), 1);
    }

    public function captchaResultKey(CustomForm $form): string
    {
        return 'cf-int-captcha-pass-' . (int)$form->id;
    }

    private function rememberCaptchaResult(CustomForm $form, bool $shown, bool $passed): void
    {
        Yii::$app->session->set($this->captchaResultKey($form), [
            'shown' => $shown ? 1 : 0,
            'passed' => $passed ? 1 : 0,
        ]);
    }

    /**
     * @return array{shown:bool,passed:bool}|null
     */
    private function takeCaptchaResult(CustomForm $form): ?array
    {
        $key = $this->captchaResultKey($form);
        if (!Yii::$app->session->has($key)) {
            return null;
        }
        $raw = Yii::$app->session->get($key);
        Yii::$app->session->remove($key);
        if (is_array($raw)) {
            return [
                'shown' => !empty($raw['shown']),
                'passed' => !empty($raw['passed']),
            ];
        }
        return [
            'shown' => true,
            'passed' => (int)$raw === 1,
        ];
    }

    public function discardCaptchaResult(CustomForm $form): void
    {
        Yii::$app->session->remove($this->captchaResultKey($form));
    }

    public function clearCaptchaRequired(CustomForm $form): void
    {
        Yii::$app->session->remove($this->captchaRequiredKey($form));
    }

    private function captchaRequiredKey(CustomForm $form): string
    {
        return self::CAPTCHA_REQUIRED_PREFIX . (int)$form->id;
    }

    public function findAccessToken(CustomForm $form, string $raw): ?FormAccessToken
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        return FormAccessToken::findOne([
            'form_id' => $form->id,
            'token_hash' => IntegritySettings::hashValue('tok:' . $raw),
        ]);
    }

    /**
     * @return array{tokens: FormAccessToken[], plaintext: string[]}
     */
    public function generateAccessTokens(
        CustomForm $form,
        int $count,
        bool $oneTime,
        ?string $label = null,
        ?int $expiresInDays = null
    ): array {
        $count = max(1, min(200, $count));
        $created = [];
        $plain = [];
        $expiresAt = null;
        if ($expiresInDays !== null && $expiresInDays > 0) {
            $expiresAt = date('Y-m-d H:i:s', time() + ($expiresInDays * 86400));
        }
        for ($i = 0; $i < $count; $i++) {
            $raw = bin2hex(random_bytes(16));
            $row = new FormAccessToken();
            $row->form_id = (int)$form->id;
            $row->token_hash = IntegritySettings::hashValue('tok:' . $raw);
            $row->token_hint = substr($raw, 0, 6);
            $row->label = $label;
            $row->one_time = $oneTime ? 1 : 0;
            $row->max_uses = $oneTime ? 1 : 99;
            $row->use_count = 0;
            $row->expires_at = $expiresAt;
            $row->created_by = Yii::$app->user->id;
            $row->created_at = date('Y-m-d H:i:s');
            $row->save(false);
            $created[] = $row;
            $plain[] = $raw;
        }
        return ['tokens' => $created, 'plaintext' => $plain];
    }

    private function gateAccess(CustomForm $form, array $cfg, FillContext $ctx, bool $consume = false): ?string
    {
        $mode = (string)($cfg['access_mode'] ?? IntegritySettings::ACCESS_PUBLIC);
        $user = Yii::$app->user;
        if ($mode === IntegritySettings::ACCESS_PUBLIC) {
            return null;
        }
        if ($mode === IntegritySettings::ACCESS_UNIQUE) {
            if ($ctx->tokenAccess && $ctx->member) {
                return null;
            }
            $token = $this->findAccessToken($form, $ctx->accessToken);
            if (!$token || !$token->isUsable()) {
                return Yii::t('ThiscoveryFormsModule.base', 'This survey needs a unique invitation link.');
            }
            if ($consume) {
                if (!$this->consumeAccessToken($form, $ctx->accessToken)) {
                    return Yii::t('ThiscoveryFormsModule.base', 'This survey needs a unique invitation link.');
                }
                $ctx->accessTokenConsumed = true;
            }
            return null;
        }
        if ($mode === IntegritySettings::ACCESS_LOGGED_IN || $mode === IntegritySettings::ACCESS_RESTRICTED || $mode === IntegritySettings::ACCESS_EMAIL) {
            if ($user->isGuest) {
                return Yii::t('ThiscoveryFormsModule.base', 'You need to be signed in to take this survey.');
            }
        }
        if ($mode === IntegritySettings::ACCESS_EMAIL) {
            $identity = $user->identity;
            $email = $identity->email ?? '';
            if ($email === '') {
                return Yii::t('ThiscoveryFormsModule.base', 'This survey is limited to signed-in accounts that have an email address.');
            }
        }
        if ($mode === IntegritySettings::ACCESS_RESTRICTED && !$form->isGlobal()) {
            $container = $form->content->container ?? null;
            if ($container && method_exists($container, 'isMember') && !$container->isMember($user->id)) {
                return Yii::t('ThiscoveryFormsModule.base', 'This survey is limited to members of this space.');
            }
        }
        return null;
    }

    private function honeypotTriggered(array $post): bool
    {
        $val = trim((string)($post[self::HONEYPOT_NAME] ?? ''));
        return $val !== '';
    }

    private function looksSuspiciousBeforeSave(CustomForm $form, array $post, array $cfg): bool
    {
        if ($this->honeypotTriggered($post)) {
            return true;
        }
        if (!Yii::$app->session->get($this->sessionKey($form))) {
            return true;
        }
        if (IntegritySettings::isOn($cfg, 'rate_limiting') && $this->isRateLimited($form, $cfg, false)) {
            return true;
        }
        return false;
    }

    private function shouldShowCaptcha(array $cfg, bool $suspicious): bool
    {
        if (!IntegritySettings::isOn($cfg, 'captcha') || !$this->captchaAvailable($cfg)) {
            return false;
        }
        $mode = $cfg['captcha_mode'] ?? IntegritySettings::CAPTCHA_SUSPICIOUS;
        if ($mode === IntegritySettings::CAPTCHA_OFF) {
            return false;
        }
        if ($mode === IntegritySettings::CAPTCHA_ALWAYS) {
            return true;
        }
        return $suspicious;
    }

    public function verifyCaptcha(array $cfg, array $post): bool
    {
        if (!$this->captchaAvailable($cfg)) {
            return false;
        }
        $provider = (string)($cfg['captcha_provider'] ?? IntegritySettings::CAPTCHA_PROVIDER_ALTCHA);
        if ($provider === IntegritySettings::CAPTCHA_PROVIDER_TURNSTILE) {
            return $this->verifyTurnstile($cfg, $post);
        }
        return $this->verifyAltcha($post);
    }

    private function verifyTurnstile(array $cfg, array $post): bool
    {
        $secret = trim((string)($cfg['turnstile_secret'] ?? ''));
        $token = trim((string)($post['cf-turnstile-response'] ?? ''));
        if ($secret === '' || $token === '') {
            return false;
        }
        try {
            $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => Yii::$app->request->userIP,
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
            ]);
            $body = curl_exec($ch);
            curl_close($ch);
            $data = json_decode((string)$body, true);
            return !empty($data['success']);
        } catch (\Throwable $e) {
            Yii::warning('Turnstile verify failed: ' . $e->getMessage(), 'thiscovery-forms');
            return false;
        }
    }

    private function verifyAltcha(array $post): bool
    {
        $token = '';
        if (isset($post['DynamicModel']) && is_array($post['DynamicModel'])) {
            $token = trim((string)($post['DynamicModel']['captcha'] ?? ''));
        }
        if ($token === '' && isset($post['IntegrityCaptcha']) && is_array($post['IntegrityCaptcha'])) {
            $token = trim((string)($post['IntegrityCaptcha']['captcha'] ?? ''));
        }
        if ($token === '') {
            $token = trim((string)($post['captcha'] ?? ''));
        }
        if ($token === '') {
            return false;
        }
        try {
            $model = new \yii\base\DynamicModel(['captcha' => $token]);
            $model->addRule(['captcha'], Yii::$app->captcha->getValidatorClass());
            return $model->validate();
        } catch (\Throwable $e) {
            Yii::warning('Altcha verify failed: ' . $e->getMessage(), 'thiscovery-forms');
            return false;
        }
    }

    /** One address's shared allowance is this many times the per-person limit (V3-51). */
    public const RATE_LIMIT_SHARED_FACTOR = 5;

    /**
     * Keyed by the exact IPv4 address, never by /24 network: a hospital or university NAT puts
     * many real people behind one network, and the 9th of them was being blocked (V3-51, SEC-8).
     * IPv6 is keyed by /64, since one client can rotate through addresses inside its own /64.
     */
    public static function rateLimitKey(int $formId, string $ip): string
    {
        $hashes = IntegritySettings::hashIp(\humhub\modules\thiscoveryForms\services\FormActionService::networkOf($ip));
        $identity = $hashes['ip_hash'] ?: hash('sha256', 'none');
        return 'cf-int-rate-' . $formId . '-' . $identity;
    }

    private function isRateLimited(CustomForm $form, array $cfg, bool $increment, ?FillContext $ctx = null): bool
    {
        // Two counters: the configured limit per browser session, and a larger allowance per
        // address, so people sharing one address are not blocked, but a script that drops its
        // cookies still is. An attempt that is already over the limit is not counted, and one
        // whose save is rejected is refunded (refundRateLimit), so a person who keeps getting
        // validation errors is never locked out (INT-8).
        $limit = max(1, (int)($cfg['rate_limit_count'] ?? 8));
        $window = max(1, (int)($cfg['rate_limit_window'] ?? 10)) * 60;
        $keys = $this->rateLimitKeys($form);
        $sharedLimit = $limit * self::RATE_LIMIT_SHARED_FACTOR;
        return $this->withRateLock($keys, function () use ($keys, $limit, $sharedLimit, $window, $increment, $ctx): bool {
            $cache = Yii::$app->cache;
            $ipCount = (int)$cache->get($keys['ip']);
            $sessionCount = $keys['session'] ? (int)$cache->get($keys['session']) : 0;
            if (!$increment) {
                // Over-limit attempts are never counted, so "at the limit" is the flag: this
                // connection has used its whole allowance.
                return $sessionCount >= $limit || $ipCount >= $sharedLimit;
            }
            if ($sessionCount >= $limit || $ipCount >= $sharedLimit) {
                return true;
            }
            $cache->set($keys['ip'], $ipCount + 1, $window);
            if ($keys['session']) {
                $cache->set($keys['session'], $sessionCount + 1, $window);
            }
            if ($ctx) {
                $ctx->rateCounted = true;
            }
            return false;
        });
    }

    /** Give back the rate-limit count of a submit that was rejected after the gate (INT-8). */
    public function refundRateLimit(CustomForm $form, FillContext $ctx): void
    {
        if (!$ctx->rateCounted) {
            return;
        }
        $ctx->rateCounted = false;
        $cfg = $this->settings($form);
        $window = max(1, (int)($cfg['rate_limit_window'] ?? 10)) * 60;
        $keys = $this->rateLimitKeys($form);
        $this->withRateLock($keys, function () use ($keys, $window): void {
            foreach (array_filter($keys) as $key) {
                $count = (int)Yii::$app->cache->get($key);
                if ($count > 0) {
                    Yii::$app->cache->set($key, $count - 1, $window);
                }
            }
        });
    }

    /** @return array{ip:string, session:?string} */
    private function rateLimitKeys(CustomForm $form): array
    {
        $sessionId = Yii::$app->has('session') ? (string)Yii::$app->session->id : '';
        return [
            'ip' => self::rateLimitKey((int)$form->id, (string)Yii::$app->request->userIP),
            'session' => $sessionId !== '' ? 'cf-int-rate-s-' . (int)$form->id . '-' . hash('sha256', $sessionId) : null,
        ];
    }

    /**
     * Read-and-write of the counters happens under a mutex, so two concurrent submits cannot
     * both read the same count and both slip under the limit.
     */
    private function withRateLock(array $keys, callable $fn)
    {
        $mutex = Yii::$app->has('mutex') ? Yii::$app->mutex : null;
        $lock = 'cf-int-rate-' . md5($keys['ip']);
        $locked = $mutex ? $mutex->acquire($lock, 2) : false;
        try {
            return $fn();
        } finally {
            if ($locked) {
                $mutex->release($lock);
            }
        }
    }

    private function applyClientTimings(FormIntegrityMeta $meta, array $post): void
    {
        $raw = $post[self::TIMING_NAME] ?? '';
        if (is_array($raw)) {
            $data = $raw;
        } else {
            $data = json_decode((string)$raw, true);
        }
        if (!is_array($data)) {
            return;
        }
        if (!empty($data['pages']) && is_array($data['pages'])) {
            $meta->page_timings_json = json_encode($data['pages'], JSON_UNESCAPED_UNICODE);
        }
        if (!empty($data['questions']) && is_array($data['questions'])) {
            $meta->question_timings_json = json_encode($data['questions'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function analyseBot(FormIntegrityMeta $meta, array $cfg): array
    {
        $weight = (float)($cfg['weight_bot'] ?? 20);
        $hits = 0;
        $flags = [];
        if ($meta->honeypot_triggered) {
            $hits++;
            $flags[] = $this->flag('bot', 'honeypot', Yii::t('ThiscoveryFormsModule.base', 'Hidden honeypot field was filled'));
        }
        if (!$meta->session_established) {
            $hits++;
            $flags[] = $this->flag('bot', 'no_session', Yii::t('ThiscoveryFormsModule.base', 'No browser session was established before submit'));
        }
        if ($meta->rate_limited) {
            $hits++;
            $flags[] = $this->flag('bot', 'rate_limit', Yii::t('ThiscoveryFormsModule.base', 'Repeated rapid submissions from this source'));
        }
        if ($meta->captcha_shown && $meta->captcha_passed === 0) {
            $hits++;
            $flags[] = $this->flag('bot', 'captcha_fail', Yii::t('ThiscoveryFormsModule.base', 'Verification check was not completed'));
        }
        $score = $hits ? min($weight, $weight * min(1, 0.4 + ($hits - 1) * 0.3)) : 0;
        return [$score, $flags];
    }

    private function analyseDuplicate(CustomForm $form, FormAnswer $answer, FormIntegrityMeta $meta, array $cfg): array
    {
        $weight = (float)($cfg['weight_duplicate'] ?? 15);
        $flags = [];
        $hits = 0;
        $base = FormAnswer::find()->alias('a')
            ->andWhere(['a.form_id' => $form->id, 'a.is_test' => 0, 'a.status' => FormAnswer::STATUS_COMPLETE])
            ->andWhere(['<>', 'a.id', $answer->id]);
        if ($answer->wave_id) {
            $base->andWhere(['a.wave_id' => $answer->wave_id]);
        }
        if ($answer->round_id) {
            $base->andWhere(['a.round_id' => $answer->round_id]);
        }
        if ($answer->created_by) {
            $userDup = (clone $base)->andWhere(['a.created_by' => $answer->created_by])->count();
            if ($userDup) {
                $hits++;
                $flags[] = $this->flag('duplicate', 'same_user', Yii::t('ThiscoveryFormsModule.base', 'Another complete response from the same signed-in user'));
            }
        }
        if ($meta->access_token_hash) {
            $tokDup = FormIntegrityMeta::find()->alias('m')
                ->innerJoin('custom_form_answer a', 'a.id = m.answer_id')
                ->andWhere(['m.form_id' => $form->id, 'm.access_token_hash' => $meta->access_token_hash, 'a.is_test' => 0, 'a.status' => FormAnswer::STATUS_COMPLETE])
                ->andWhere(['<>', 'm.answer_id', $answer->id])
                ->count();
            if ($tokDup) {
                $hits++;
                $flags[] = $this->flag('duplicate', 'same_token', Yii::t('ThiscoveryFormsModule.base', 'Invitation token reused on another response'));
            }
        }
        // INT-7: hospitals, universities and mobile carriers put many people behind one
        // address. A shared session, or the same address with the same browser, is a duplicate
        // signal; the same address alone is shown for information only, and the nearby-network
        // signal is off unless the form turns it on.
        $others = static fn() => FormIntegrityMeta::find()->alias('m')
            ->innerJoin('custom_form_answer a', 'a.id = m.answer_id')
            ->andWhere(['m.form_id' => $form->id, 'a.is_test' => 0, 'a.status' => FormAnswer::STATUS_COMPLETE])
            ->andWhere(['<>', 'm.answer_id', $answer->id]);
        $strong = false;
        if ($meta->session_hash && $others()->andWhere(['m.session_hash' => $meta->session_hash])->exists()) {
            $strong = true;
            $hits++;
            $flags[] = $this->flag('duplicate', 'same_session', Yii::t('ThiscoveryFormsModule.base', 'Another response from the same browser session'));
        }
        if (!$strong && $meta->ip_hash && $meta->user_agent_hash
            && $others()->andWhere(['m.ip_hash' => $meta->ip_hash, 'm.user_agent_hash' => $meta->user_agent_hash])->exists()) {
            $strong = true;
            $hits++;
            $flags[] = $this->flag('duplicate', 'same_source', Yii::t('ThiscoveryFormsModule.base', 'Another response from the same address and browser'));
        }
        if (!$strong && $meta->ip_hash && $others()->andWhere(['m.ip_hash' => $meta->ip_hash])->exists()) {
            $flags[] = $this->flag('duplicate', 'same_address', Yii::t('ThiscoveryFormsModule.base', 'Another response from the same network address (common on shared networks; not counted)'), ['severity' => 0]);
        }
        if (!$strong && !empty($cfg['duplicate_network_signal']) && $meta->ip_network_hash
            && $others()->andWhere(['m.ip_network_hash' => $meta->ip_network_hash])->exists()) {
            $flags[] = $this->flag('duplicate', 'same_network', Yii::t('ThiscoveryFormsModule.base', 'Another response from a nearby network address (not counted)'), ['severity' => 0]);
        }
        $allowMultiple = $form->allow_multiple || !empty($cfg['allow_multiple']);
        $score = $hits ? min($weight, $weight * (0.45 + ($hits - 1) * 0.28)) : 0;
        if ($allowMultiple) {
            $score *= 0.45;
        }
        return [$score, $flags];
    }

    private function analyseSpeed(FormIntegrityMeta $meta, array $cfg): array
    {
        $weight = (float)($cfg['weight_speed'] ?? 15);
        $duration = (int)$meta->duration_seconds;
        $shown = max(1, (int)$meta->shown_question_count);
        $perQuestion = $duration / $shown;
        $minPer = $this->secondsPerQuestionFloor($cfg, $shown);
        $percent = max(5, min(90, (int)($cfg['speed_percent'] ?? 40)));
        $median = (int)$meta->median_seconds;
        $flags = [];
        $score = 0.0;
        if ($duration > 0 && $perQuestion < $minPer) {
            $score = $weight * 0.7;
            $flags[] = $this->flag('speed', 'absolute', Yii::t('ThiscoveryFormsModule.base', 'Completed in {n} seconds per question (threshold {min}s)', [
                'n' => round($perQuestion, 1),
                'min' => $minPer,
            ]));
        } elseif ($median >= $minPer && $duration > 0 && $perQuestion < ($median * $percent / 100)) {
            $score = $weight * 0.55;
            $flags[] = $this->flag('speed', 'relative', Yii::t('ThiscoveryFormsModule.base', 'Completed in {n}s per question versus typical {median}s', [
                'n' => round($perQuestion, 1),
                'median' => $median,
            ]));
        }
        return [$score, $flags];
    }

    /**
     * Seconds per question shown. The old setting was a total-seconds floor: its default of
     * 15 reads as 2 seconds per question, and any value over 20 (no one needs 20 seconds per
     * question to be genuine) is a total, spread over the questions shown (V3-51).
     */
    private function secondsPerQuestionFloor(array $cfg, int $shown = 1): int
    {
        $configured = (int)($cfg['speed_min_seconds'] ?? 2);
        if ($configured === 15 || $configured < 1) {
            return 2;
        }
        if ($configured > 20) {
            return max(1, min(20, (int)ceil($configured / max(1, $shown))));
        }
        return $configured;
    }

    /**
     * Field ids on the respondent's route that were visible.
     *
     * @return array<int, true>
     */
    public function shownFieldIds(CustomForm $form, array $values): array
    {
        // Live questions, plus a removed question only when this response answered it (it was
        // filling an edition that still had it). A removed question the respondent never saw is
        // not "shown", so it cannot fail an attention check or inflate the speed baseline (V3-33).
        $fields = [];
        foreach ($form->getAllFields()->all() as $field) {
            $raw = $values[(int)$field->id] ?? ($values[(string)$field->id] ?? null);
            $answered = $raw !== null && $raw !== '' && $raw !== [];
            if (!$field->isRemoved() || $answered) {
                $fields[] = $field;
            }
        }
        $pageOrders = [];
        $current = RandomisationService::$current;
        if ($current && (int)$current->form_id === (int)$form->id && RandomisationService::active($form)) {
            $pageOrders = (new RandomisationService())->orders($current)['pages'];
        }
        $onRoute = (new FormPager())->visitedFieldIds($fields, $values, $pageOrders);
        $engine = new LogicEngine();
        $ids = [];
        foreach ($fields as $field) {
            $id = (int)$field->id;
            if ($id < 1 || !isset($onRoute[$id]) || !$field->collectsAnswer() || $field->isHiddenFromRespondent()) {
                continue;
            }
            if (!$engine->isFieldVisible($field, $fields, $values)) {
                continue;
            }
            $ids[$id] = true;
        }
        return $ids;
    }

    private function analyseStraightline(CustomForm $form, array $values, array $cfg, array $shown): array
    {
        $weight = (float)($cfg['weight_straightline'] ?? 10);
        $minItems = max(3, (int)($cfg['straightline_min_items'] ?? 5));
        $flags = [];
        $score = 0.0;
        // INT-6: identical answers are often the true answer on health instruments ("Not at
        // all" to every PHQ-9 item), so they are flagged only where the set includes
        // reverse-keyed items, which make the same answer inconsistent. Questions tagged
        // "leave out" are skipped, and sequences are judged on consecutive questions only.
        foreach ($form->getAllFields()->all() as $field) {
            if (!isset($shown[(int)$field->id]) || $field->isStraightlineExempt()) {
                continue;
            }
            if (!in_array($field->type, [FormField::TYPE_GRID_SINGLE, FormField::TYPE_GRID_MULTI], true)) {
                continue;
            }
            $val = $values[$field->id] ?? null;
            if (!is_array($val) || count($val) < $minItems) {
                continue;
            }
            $reverse = array_fill_keys($field->getReverseRows(), true);
            $hasReverse = false;
            foreach (array_keys($val) as $rowKey) {
                if (isset($reverse[(string)$rowKey])) {
                    $hasReverse = true;
                }
            }
            if (!$hasReverse || count($reverse) >= count($val)) {
                continue;
            }
            $flat = [];
            foreach ($val as $cell) {
                $flat[] = is_array($cell) ? implode('|', $cell) : (string)$cell;
            }
            $unique = array_unique($flat);
            if (count($unique) === 1 && $flat[0] !== '') {
                $score = max($score, $weight * 0.8);
                $flags[] = $this->flag('straightline', 'identical_grid', Yii::t('ThiscoveryFormsModule.base', 'Identical answers across “{label}”, which has reverse-keyed rows', [
                    'label' => $field->label,
                ]), ['field_id' => (int)$field->id]);
            }
        }
        // Consecutive rating questions only: a run breaks at any other answered question.
        $ratingRuns = [[]];
        foreach ($form->getAllFields()->all() as $field) {
            if (!isset($shown[(int)$field->id]) || !$field->collectsAnswer()) {
                continue;
            }
            $v = $values[$field->id] ?? '';
            if ($field->type !== FormField::TYPE_RATING || $field->isStraightlineExempt() || $v === '' || $v === null || is_array($v)) {
                if ($ratingRuns[count($ratingRuns) - 1] !== []) {
                    $ratingRuns[] = [];
                }
                continue;
            }
            $ratingRuns[count($ratingRuns) - 1][] = ['v' => (int)$v, 'reverse' => $field->isReverseKeyed()];
        }
        foreach ($ratingRuns as $run) {
            if (count($run) < $minItems) {
                continue;
            }
            $ratings = array_column($run, 'v');
            $seq = implode(',', $ratings);
            $asc = implode(',', range(min($ratings), max($ratings)));
            $desc = implode(',', range(max($ratings), min($ratings), -1));
            if (count(array_unique($ratings)) === 1) {
                if (in_array(true, array_column($run, 'reverse'), true) && in_array(false, array_column($run, 'reverse'), true)) {
                    $score = max($score, $weight * 0.75);
                    $flags[] = $this->flag('straightline', 'flat_ratings', Yii::t('ThiscoveryFormsModule.base', 'No variation across rating questions that include reverse-keyed items'));
                }
            } elseif ($seq === $asc || $seq === $desc) {
                $score = max($score, $weight * 0.7);
                $flags[] = $this->flag('straightline', 'sequential', Yii::t('ThiscoveryFormsModule.base', 'Sequential pattern in consecutive rating answers ({pattern})', [
                    'pattern' => $seq,
                ]));
            }
        }

        // Consecutive radio/dropdown questions that share the same option list (Likert sets).
        $run = [];
        $flush = function () use (&$run, &$score, &$flags, $weight, $minItems) {
            if (count($run) < $minItems) {
                $run = [];
                return;
            }
            $answers = array_column($run, 'value');
            $unique = array_unique($answers);
            $mixed = in_array(true, array_column($run, 'reverse'), true) && in_array(false, array_column($run, 'reverse'), true);
            if (count($unique) === 1 && $answers[0] !== '' && !$mixed) {
                // Same answer to every item and none reverse-keyed: often the honest answer (INT-6).
            } elseif (count($unique) === 1 && $answers[0] !== '') {
                $score = max($score, $weight * 0.75);
                $flags[] = $this->flag('straightline', 'flat_choices', Yii::t('ThiscoveryFormsModule.base', 'No variation across {n} consecutive choice questions', [
                    'n' => count($run),
                ]), ['field_ids' => array_column($run, 'id')]);
            } else {
                $opts = $run[0]['options'];
                $indices = [];
                $ok = true;
                foreach ($answers as $ans) {
                    $idx = array_search($ans, $opts, true);
                    if ($idx === false) {
                        $ok = false;
                        break;
                    }
                    $indices[] = (int)$idx;
                }
                if ($ok && count($indices) >= $minItems) {
                    $seq = implode(',', $indices);
                    $asc = implode(',', range(min($indices), max($indices)));
                    $desc = implode(',', range(max($indices), min($indices), -1));
                    if (($seq === $asc || $seq === $desc) && min($indices) !== max($indices)) {
                        $score = max($score, $weight * 0.7);
                        $flags[] = $this->flag('straightline', 'sequential_choices', Yii::t('ThiscoveryFormsModule.base', 'Sequential pattern across consecutive choice questions'), [
                            'field_ids' => array_column($run, 'id'),
                        ]);
                    }
                }
            }
            $run = [];
        };
        foreach ($form->getAllFields()->all() as $field) {
            if (!isset($shown[(int)$field->id]) || !$field->collectsAnswer() || $field->isHiddenFromRespondent() || $field->isAttentionCheck() || $field->isStraightlineExempt()) {
                $flush();
                continue;
            }
            if (!in_array($field->type, [FormField::TYPE_RADIO, FormField::TYPE_DROPDOWN], true)) {
                $flush();
                continue;
            }
            $raw = $values[$field->id] ?? '';
            if ($raw === '' || $raw === null || is_array($raw)) {
                $flush();
                continue;
            }
            $options = array_map('strval', $field->getOptions());
            if (count($options) < 2) {
                $flush();
                continue;
            }
            $sig = implode("\0", $options);
            if ($run !== [] && ($run[0]['sig'] ?? '') !== $sig) {
                $flush();
            }
            $run[] = [
                'id' => (int)$field->id,
                'value' => (string)$raw,
                'options' => $options,
                'sig' => $sig,
                'reverse' => $field->isReverseKeyed(),
            ];
        }
        $flush();

        return [$score, $flags];
    }

    private function analyseAttention(CustomForm $form, array $values, array $shown): array
    {
        $loopIds = (new \humhub\modules\thiscoveryForms\services\LoopService())->loopFieldIds($form);
        $flags = [];
        $failed = 0;
        $total = 0;
        foreach ($form->getAllFields()->all() as $field) {
            if (!isset($shown[(int)$field->id]) || !$field->isAttentionCheck()) {
                continue;
            }
            $total++;
            $expected = $field->getAttentionExpected();
            $actual = $values[$field->id] ?? '';
            if (isset($loopIds[(int)$field->id]) && is_array($actual)) {
                // In a loop, every repeat must pass, not any one of them (V3-51).
                $pass = $actual !== [];
                foreach ($actual as $cell) {
                    if (!$this->attentionPasses($field, $cell, $expected)) {
                        $pass = false;
                        break;
                    }
                }
            } else {
                $pass = $this->attentionPasses($field, $actual, $expected);
            }
            if (!$pass) {
                $failed++;
                $flags[] = $this->flag('attention', 'failed', Yii::t('ThiscoveryFormsModule.base', 'Attention check failed on “{label}”', [
                    'label' => $field->label,
                ]), ['field_id' => (int)$field->id, 'passed' => false]);
            } else {
                $flags[] = $this->flag('attention', 'passed', Yii::t('ThiscoveryFormsModule.base', 'Attention check passed on “{label}”', [
                    'label' => $field->label,
                ]), ['field_id' => (int)$field->id, 'passed' => true, 'severity' => 0]);
            }
        }
        $weight = (float)(IntegritySettings::forForm($form)['weight_attention'] ?? 15);
        $score = $total ? min($weight, $weight * ($failed / max(1, $total))) : 0;
        if ($failed === 1 && $total === 1) {
            $score = min($score, $weight * 0.55);
        }
        return [$score, $flags];
    }

    /** @param array<int,true>|null $shown questions on this respondent's route and visible */
    private function analyseConsistency(CustomForm $form, array $values, array $cfg, ?array $shown = null): array
    {
        $weight = (float)($cfg['weight_consistency'] ?? 10);
        $flags = [];
        $hits = 0;
        $fieldsById = [];
        foreach ($form->getAllFields()->all() as $f) {
            $fieldsById[(int)$f->id] = $f;
        }
        foreach (($cfg['consistency_rules'] ?? []) as $rule) {
            if (!is_array($rule) || empty($rule['conditions'])) {
                continue;
            }
            // A rule only applies when the respondent was shown every question it reads: an empty
            // answer to a skipped question is not an inconsistency (INT-2).
            if ($shown !== null) {
                foreach ($rule['conditions'] as $cond) {
                    if (!isset($shown[(int)($cond['field_id'] ?? 0)])) {
                        continue 2;
                    }
                }
            }
            $matched = true;
            foreach ($rule['conditions'] as $cond) {
                $fieldId = (int)($cond['field_id'] ?? 0);
                $actual = $values[$fieldId] ?? '';
                $actualStr = is_array($actual) ? implode(',', $actual) : (string)$actual;
                $expected = (string)($cond['value'] ?? '');
                $op = $cond['operator'] ?? 'equals';
                // Code or label, like attention checks: "Agree" matches the code it stands for (INT-9).
                $field = $fieldsById[$fieldId] ?? null;
                $same = $field ? $this->attentionPasses($field, $actual, $expected) : $this->valuesMatch($actualStr, $expected);
                $ok = match ($op) {
                    'not_equals' => !$same,
                    'contains' => $expected !== '' && mb_stripos($actualStr, $expected) !== false,
                    default => $same,
                };
                if (!$ok) {
                    $matched = false;
                    break;
                }
            }
            if ($matched) {
                $hits++;
                $flags[] = $this->flag('consistency', (string)($rule['id'] ?? 'rule'), Yii::t('ThiscoveryFormsModule.base', 'Inconsistent answers: {label}', [
                    'label' => $rule['label'] ?? $rule['id'] ?? 'rule',
                ]), ['rule_id' => $rule['id'] ?? null]);
            }
        }
        $score = $hits ? min($weight, $weight * min(1, 0.5 + ($hits - 1) * 0.25)) : 0;
        return [$score, $flags];
    }

    private function analyseFreetext(CustomForm $form, array $values, array $cfg, array $shown): array
    {
        $weight = (float)($cfg['weight_freetext'] ?? 10);
        $minChars = max(3, (int)($cfg['freetext_min_chars'] ?? 8));
        $flags = [];
        $hits = 0;
        $textTypes = [FormField::TYPE_TEXT, FormField::TYPE_TEXTAREA, FormField::TYPE_HTML];
        $seenNorm = [];
        foreach ($form->getAllFields()->all() as $field) {
            if (!isset($shown[(int)$field->id])) {
                continue;
            }
            if (!in_array($field->type, $textTypes, true) || !$field->collectsAnswer() || $field->isHiddenFromRespondent()) {
                continue;
            }
            $raw = $values[$field->id] ?? '';
            $text = trim(is_array($raw) ? implode(' ', $raw) : (string)$raw);
            if ($text === '') {
                if ($field->required) {
                    $hits++;
                    $flags[] = $this->flag('freetext', 'empty', Yii::t('ThiscoveryFormsModule.base', 'Empty answer where text was expected on “{label}”', [
                        'label' => $field->label,
                    ]), ['field_id' => (int)$field->id]);
                }
                continue;
            }
            if (mb_strlen($text) < $minChars && $field->required) {
                $hits++;
                $flags[] = $this->flag('freetext', 'short', Yii::t('ThiscoveryFormsModule.base', 'Very short answer on “{label}”', [
                    'label' => $field->label,
                ]), ['field_id' => (int)$field->id]);
            }
            if (preg_match('/(.)\1{7,}/u', $text) || preg_match('/^(.{1,12})\1{3,}$/u', $text)) {
                $hits++;
                $flags[] = $this->flag('freetext', 'repeated', Yii::t('ThiscoveryFormsModule.base', 'Repeated characters or copied fragment on “{label}”', [
                    'label' => $field->label,
                ]), ['field_id' => (int)$field->id]);
            }
            $words = preg_split('/\s+/', mb_strtolower($text)) ?: [];
            if (count($words) >= 4) {
                $uniq = array_unique($words);
                if (count($uniq) <= 2) {
                    $hits++;
                    $flags[] = $this->flag('freetext', 'repeated_words', Yii::t('ThiscoveryFormsModule.base', 'Repeated words on “{label}”', [
                        'label' => $field->label,
                    ]), ['field_id' => (int)$field->id]);
                }
            }
            $labelNorm = $this->normalizeText($field->label);
            $ansNorm = $this->normalizeText($text);
            if ($labelNorm !== '' && $ansNorm !== '' && (str_contains($ansNorm, $labelNorm) || similar_text($labelNorm, $ansNorm) / max(1, mb_strlen($labelNorm)) > 0.85)) {
                $hits++;
                $flags[] = $this->flag('freetext', 'echo_question', Yii::t('ThiscoveryFormsModule.base', 'Answer repeats the question on “{label}”', [
                    'label' => $field->label,
                ]), ['field_id' => (int)$field->id]);
            }
            $letters = preg_replace('/[^a-z]/i', '', $text) ?? '';
            if (mb_strlen($letters) >= 20) {
                $vowels = preg_match_all('/[aeiou]/i', $letters);
                $ratio = $vowels / max(1, mb_strlen($letters));
                $uniqueRatio = count(array_unique(str_split(mb_strtolower($letters)))) / max(1, mb_strlen($letters));
                if ($ratio < 0.12 || $uniqueRatio < 0.12) {
                    $hits++;
                    $flags[] = $this->flag('freetext', 'gibberish', Yii::t('ThiscoveryFormsModule.base', 'Low-variety text on “{label}”', [
                        'label' => $field->label,
                    ]), ['field_id' => (int)$field->id]);
                }
            }
            $seenNorm[] = ['id' => (int)$field->id, 'norm' => $ansNorm, 'label' => $field->label];
        }
        // Same response: near-identical text pasted into two long free-text boxes.
        $copyMin = max($minChars, 20);
        for ($i = 0; $i < count($seenNorm); $i++) {
            if (mb_strlen($seenNorm[$i]['norm']) < $copyMin) {
                continue;
            }
            for ($j = $i + 1; $j < count($seenNorm); $j++) {
                if (mb_strlen($seenNorm[$j]['norm']) < $copyMin) {
                    continue;
                }
                if ($seenNorm[$i]['norm'] === $seenNorm[$j]['norm']) {
                    $pct = 100.0;
                } else {
                    similar_text($seenNorm[$i]['norm'], $seenNorm[$j]['norm'], $pct);
                }
                if ($pct >= 92) {
                    $hits++;
                    $flags[] = $this->flag('freetext', 'copied_text', Yii::t('ThiscoveryFormsModule.base', 'Nearly identical text on “{a}” and “{b}”', [
                        'a' => $seenNorm[$i]['label'],
                        'b' => $seenNorm[$j]['label'],
                    ]), ['field_ids' => [$seenNorm[$i]['id'], $seenNorm[$j]['id']], 'score' => round($pct, 1)]);
                    break 2;
                }
            }
        }
        $score = $hits ? min($weight, $weight * min(1, 0.35 + ($hits - 1) * 0.2)) : 0;
        return [$score, $flags];
    }

    /** Responses the submit request compares against; the background scan reads up to FULL. */
    public const SIMILARITY_POOL = 250;
    public const SIMILARITY_POOL_FULL = 5000;

    /**
     * Score and status from the signal scores, unless a person has overridden the status. An
     * automatic exclusion is audited. Used at completion and when similarity is re-scored.
     *
     * @param array<string,float> $scores
     */
    private function applyScoreStatus(CustomForm $form, int $answerId, FormIntegrityMeta $meta, array $scores, array $cfg): void
    {
        $meta->overall_score = $this->overallScore($scores, $cfg);
        $autoStatus = $this->statusFromScore((float)$meta->overall_score, $scores, $cfg);
        if ($meta->status_override) {
            return;
        }
        $fromStatus = $meta->integrity_status;
        $fromAnalysis = $meta->analysis_status;
        $meta->integrity_status = $autoStatus;
        $meta->analysis_status = $this->analysisFromStatus($autoStatus);
        if ($autoStatus === FormIntegrityMeta::STATUS_EXCLUDED && $fromStatus !== FormIntegrityMeta::STATUS_EXCLUDED) {
            $meta->exclusion_reason = Yii::t(
                'ThiscoveryFormsModule.base',
                'Automatically excluded: quality score {score} with multiple integrity signals.',
                ['score' => number_format((float)$meta->overall_score, 0)]
            );
            FormIntegrityAudit::record($form->id, $answerId, 'auto_exclude', $fromStatus, $autoStatus, $meta->exclusion_reason);
            if ($fromAnalysis !== $meta->analysis_status) {
                FormIntegrityAudit::record($form->id, $answerId, 'analysis_status', $fromAnalysis, $meta->analysis_status, $meta->exclusion_reason);
            }
        }
    }

    private function similarityScoreFor(int $count, array $cfg): float
    {
        $weight = (float)($cfg['weight_similarity'] ?? 5);
        return $count ? min($weight, $weight * (0.4 + min(0.6, $count * 0.15))) : 0.0;
    }

    /**
     * Record a similar pair on the earlier response too, and re-score it: the first response
     * of a copied pair used to be scored before its twin existed, so it was never flagged.
     *
     * @param int[] $similarIds
     */
    private function linkSimilar(CustomForm $form, int $answerId, array $similarIds, array $cfg): void
    {
        foreach (array_unique(array_map('intval', $similarIds)) as $otherId) {
            $other = FormIntegrityMeta::findOne(['answer_id' => $otherId]);
            if (!$other) {
                continue;
            }
            $ids = $other->getSimilarAnswerIds();
            if (in_array($answerId, $ids, true)) {
                continue;
            }
            $ids[] = $answerId;
            $this->storeSimilarity($form, $other, $ids, $cfg);
        }
    }

    /** @param int[] $ids */
    private function storeSimilarity(CustomForm $form, FormIntegrityMeta $meta, array $ids, array $cfg): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $meta->similar_answer_ids_json = $ids ? json_encode($ids) : null;
        $meta->similarity_score = $this->similarityScoreFor(count($ids), $cfg);
        $flags = array_values(array_filter($meta->getFlags(), static fn($f) => !is_array($f) || ($f['category'] ?? '') !== 'similarity'));
        if ($ids) {
            $flags[] = $this->flag('similarity', 'cluster', Yii::t('ThiscoveryFormsModule.base', 'Unusually similar to {n,plural,=1{1 other response} other{# other responses}}', [
                'n' => count($ids),
            ]), ['ids' => $ids]);
        }
        $meta->setFlags($flags);
        $answer = FormAnswer::findOne((int)$meta->answer_id);
        $wasExcluded = (string)$meta->analysis_status === FormIntegrityMeta::ANALYSIS_EXCLUDED;
        // Responses left out for their outcome (screened out, over quota, not consented) are not
        // scored, so they are not re-scored either.
        $byOutcome = $answer && in_array((string)$answer->outcome, [FormAnswer::OUTCOME_NOT_CONSENTED, FormAnswer::OUTCOME_SCREENED_OUT, FormAnswer::OUTCOME_OVER_QUOTA], true);
        if (IntegritySettings::isOn($cfg, 'integrity_scoring') && !$byOutcome) {
            $this->applyScoreStatus($form, (int)$meta->answer_id, $meta, $meta->getComponentScores(), $cfg);
        }
        $meta->save(false);
        if ($answer && !$wasExcluded && (string)$meta->analysis_status === FormIntegrityMeta::ANALYSIS_EXCLUDED) {
            (new \humhub\modules\thiscoveryForms\services\QuotaService())->releaseExcluded($form, $answer);
        }
    }

    private function queueSimilarityScan(CustomForm $form, FormAnswer $answer): void
    {
        if (!class_exists(\humhub\modules\queue\ActiveJob::class) || !Yii::$app->has('queue')) {
            return;
        }
        $pool = (int)FormAnswer::find()
            ->where(['form_id' => (int)$form->id, 'is_test' => 0, 'status' => FormAnswer::STATUS_COMPLETE])
            ->count();
        if ($pool <= self::SIMILARITY_POOL + 1) {
            // The submit request already compared against every response.
            return;
        }
        Yii::$app->queue->push(new \humhub\modules\thiscoveryForms\jobs\SimilarityScanJob([
            'formId' => (int)$form->id,
            'answerId' => (int)$answer->id,
        ]));
    }

    /**
     * Background comparison of one response against the whole form (up to
     * SIMILARITY_POOL_FULL), with both sides of each similar pair recorded (INT-3).
     */
    public function rescanSimilarity(int $formId, int $answerId): void
    {
        $form = CustomForm::findOne($formId);
        $answer = FormAnswer::findOne(['id' => $answerId, 'form_id' => $formId]);
        $meta = FormIntegrityMeta::findOne(['answer_id' => $answerId]);
        if (!$form || !$answer || !$meta) {
            return;
        }
        $cfg = $this->settings($form);
        if (!IntegritySettings::isOn($cfg, 'enabled') || !IntegritySettings::isOn($cfg, 'similarity_detection')) {
            return;
        }
        $answer->populateRelation('form', $form);
        [, , $similarIds] = $this->analyseSimilarity($form, $answer, $answer->getValuesMap(), $cfg, self::SIMILARITY_POOL_FULL);
        $this->storeSimilarity($form, $meta, array_merge($meta->getSimilarAnswerIds(), $similarIds), $cfg);
        $this->linkSimilar($form, $answerId, $similarIds, $cfg);
    }

    private function analyseSimilarity(CustomForm $form, FormAnswer $answer, array $values, array $cfg, int $pool = self::SIMILARITY_POOL): array
    {
        $weight = (float)($cfg['weight_similarity'] ?? 5);
        $threshold = max(50, min(99, (int)($cfg['similarity_threshold'] ?? 90)));
        $texts = $this->answerTexts($form, $values);
        $flags = [];
        $similarIds = [];
        $best = 0;
        $comparable = 0;
        foreach ($form->getAllFields()->all() as $field) {
            $comparable += $this->isChoiceComparable($field) ? 1 : 0;
        }
        if ($comparable < 5 && array_filter($texts, static fn($t) => mb_strlen((string)$t) >= 12) === []) {
            // Nothing that could show copying: no query on the submit path (DAT-15).
            return [0, [], []];
        }
        $others = FormAnswer::find()->alias('a')
            ->andWhere(['a.form_id' => $form->id, 'a.is_test' => 0, 'a.status' => FormAnswer::STATUS_COMPLETE])
            ->andWhere(['<>', 'a.id', $answer->id])
            ->with('answerFields')
            ->orderBy(['a.id' => SORT_DESC])
            ->limit($pool)
            ->all();
        // How often each answer occurs in the pool, so agreement on common answers (everyone
        // picking "Neutral") is discounted as chance (V3-51).
        $maps = [];
        foreach ($others as $other) {
            $other->populateRelation('form', $form);
            $maps[(int)$other->id] = $other->getValuesMap();
        }
        $distributions = $this->answerDistributions($form, array_merge([$values], array_values($maps)));
        /** @var FormAnswer $other */
        foreach ($others as $other) {
            $omap = $maps[(int)$other->id];
            $choiceSim = $this->choiceSimilarity($form, $values, $omap, $distributions);
            $textSim = 0;
            $otexts = $this->answerTexts($form, $omap);
            foreach ($texts as $i => $t) {
                $o = $otexts[$i] ?? '';
                if ($t === '' || $o === '' || mb_strlen($t) < 12) {
                    continue;
                }
                similar_text($t, $o, $pct);
                $textSim = max($textSim, $pct);
            }
            $combined = max($choiceSim, $textSim);
            if ($combined >= $threshold) {
                $similarIds[] = (int)$other->id;
                $best = max($best, $combined);
            }
        }
        if ($similarIds) {
            $flags[] = $this->flag('similarity', 'cluster', Yii::t('ThiscoveryFormsModule.base', 'Unusually similar to {n,plural,=1{1 other response} other{# other responses}}', [
                'n' => count($similarIds),
            ]), ['ids' => $similarIds, 'score' => round($best, 1)]);
        }
        return [$this->similarityScoreFor(count($similarIds), $cfg), $flags, $similarIds];
    }

    private function overallScore(array $scores, array $cfg): float
    {
        $total = 0.0;
        foreach ($scores as $v) {
            $total += (float)$v;
        }
        return max(0, min(100, round(100 - $total, 2)));
    }

    private function statusFromScore(float $score, array $scores, array $cfg): string
    {
        $trust = (float)($cfg['trust_threshold'] ?? 80);
        $review = (float)($cfg['review_threshold'] ?? 55);
        $positive = 0;
        foreach ($scores as $v) {
            if ((float)$v >= 4) {
                $positive++;
            }
        }
        // One signal may prompt human review. It must never classify the response
        // as suspicious or excluded — that would treat a single indicator as fraud.
        if ($positive < 2) {
            if ($score >= $trust) {
                return FormIntegrityMeta::STATUS_TRUSTED;
            }
            return FormIntegrityMeta::STATUS_REVIEW;
        }
        if ($score >= $trust) {
            return FormIntegrityMeta::STATUS_TRUSTED;
        }
        if (IntegritySettings::isOn($cfg, 'auto_exclude') && $score < ($review - 15) && $positive >= 2) {
            return FormIntegrityMeta::STATUS_EXCLUDED;
        }
        if ($score >= $review) {
            return FormIntegrityMeta::STATUS_REVIEW;
        }
        return FormIntegrityMeta::STATUS_SUSPICIOUS;
    }

    private function analysisFromStatus(string $status): string
    {
        return match ($status) {
            FormIntegrityMeta::STATUS_EXCLUDED => FormIntegrityMeta::ANALYSIS_EXCLUDED,
            FormIntegrityMeta::STATUS_SUSPICIOUS => FormIntegrityMeta::ANALYSIS_QUARANTINED,
            FormIntegrityMeta::STATUS_REVIEW => FormIntegrityMeta::ANALYSIS_REVIEW,
            default => FormIntegrityMeta::ANALYSIS_INCLUDED,
        };
    }

    private function medianSecondsPerQuestion(CustomForm $form, int $excludeId): int
    {
        $rows = FormIntegrityMeta::find()->alias('m')
            ->innerJoin('custom_form_answer a', 'a.id = m.answer_id')
            ->andWhere(['m.form_id' => $form->id, 'a.is_test' => 0, 'a.status' => FormAnswer::STATUS_COMPLETE])
            ->andWhere(['>', 'm.duration_seconds', 0])
            ->andWhere(['>', 'm.shown_question_count', 0])
            ->andWhere(['<>', 'm.answer_id', $excludeId])
            ->andWhere(['not in', 'm.integrity_status', [FormIntegrityMeta::STATUS_SUSPICIOUS, FormIntegrityMeta::STATUS_EXCLUDED]])
            ->andWhere(['or', ['m.speed_score' => null], ['m.speed_score' => 0]])
            ->select(['m.duration_seconds', 'm.shown_question_count'])
            ->asArray()
            ->all();
        $rates = [];
        foreach ($rows as $row) {
            $shown = (int)$row['shown_question_count'];
            if ($shown < 1) {
                continue;
            }
            $rates[] = (int)$row['duration_seconds'] / $shown;
        }
        sort($rates);
        $n = count($rates);
        if ($n < 3) {
            return 0;
        }
        $mid = (int)floor($n / 2);
        if ($n % 2) {
            return (int)round($rates[$mid]);
        }
        return (int)round(($rates[$mid - 1] + $rates[$mid]) / 2);
    }

    /**
     * Give back a one-time invitation that the submit gate reserved, when the submission then
     * fails (CAPTCHA, rate limit, validation or save). The invitee can simply try again (V3-32).
     * The reservation itself stays atomic, so two simultaneous submits still cannot both use it.
     */
    public function refundAccessToken(CustomForm $form, FillContext $ctx): void
    {
        // Every rejected submit refunds its token use; it gives back its rate-limit count too.
        $this->refundRateLimit($form, $ctx);
        if (!$ctx->accessTokenConsumed || trim((string)$ctx->accessToken) === '') {
            return;
        }
        Yii::$app->db->createCommand()->update('{{%custom_form_access_token}}', [
            'use_count' => new \yii\db\Expression('use_count - 1'),
        ], [
            'and',
            ['form_id' => (int)$form->id],
            ['token_hash' => IntegritySettings::hashValue('tok:' . trim((string)$ctx->accessToken))],
            ['>', 'use_count', 0],
        ])->execute();
        $ctx->accessTokenConsumed = false;
    }

    public function consumeAccessToken(CustomForm $form, string $raw): bool
    {
        $raw = trim($raw);
        if ($raw === '') {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        $updated = Yii::$app->db->createCommand()->update('{{%custom_form_access_token}}', [
            'use_count' => new \yii\db\Expression('use_count + 1'),
            'last_used_at' => $now,
        ], [
            'and',
            ['form_id' => (int)$form->id],
            ['token_hash' => IntegritySettings::hashValue('tok:' . $raw)],
            ['<', 'use_count', new \yii\db\Expression('max_uses')],
            ['or', ['expires_at' => null], ['>=', 'expires_at', $now]],
        ])->execute();
        return $updated > 0;
    }

    /**
     * Share of choice questions that hold the same value. The question id is not part of the comparison.
     */
    /**
     * Percent of choice answers two responses share. With $distributions (answer frequencies
     * per question across the pool) the agreement expected by chance is taken out, as in
     * Cohen's kappa, so five shared "Neutral" answers are not 100% (V3-51); fewer than 5
     * compared questions is not evidence either way.
     *
     * @param array<int, array<string, float>>|null $distributions
     */
    public function choiceSimilarity(CustomForm $form, array $left, array $right, ?array $distributions = null): float
    {
        $compared = 0;
        $same = 0;
        $expected = 0.0;
        foreach ($form->getAllFields()->all() as $field) {
            if (!$this->isChoiceComparable($field)) {
                continue;
            }
            $a = $this->comparable($left[$field->id] ?? $left[(string)$field->id] ?? '');
            $b = $this->comparable($right[$field->id] ?? $right[(string)$field->id] ?? '');
            if ($a === '' && $b === '') {
                continue;
            }
            $compared++;
            if ($a === $b) {
                $same++;
            }
            if ($distributions !== null) {
                foreach ($distributions[(int)$field->id] ?? [] as $share) {
                    $expected += $share * $share;
                }
            }
        }
        if ($compared < 1) {
            return 0.0;
        }
        $observed = $same / $compared;
        if ($distributions === null) {
            return round(100 * $observed, 2);
        }
        if ($compared < 5) {
            return 0.0;
        }
        $chance = $expected / $compared;
        if ($chance >= 0.999) {
            // Everyone answers alike: agreeing says nothing about copying.
            return 0.0;
        }
        return round(100 * max(0.0, ($observed - $chance) / (1 - $chance)), 2);
    }

    private function isChoiceComparable(FormField $field): bool
    {
        return $field->collectsAnswer()
            && !in_array($field->type, [FormField::TYPE_TEXT, FormField::TYPE_TEXTAREA, FormField::TYPE_HTML, FormField::TYPE_FILE, FormField::TYPE_MAP], true);
    }

    /** @param mixed $value */
    private function comparable($value): string
    {
        return is_array($value) ? (string)json_encode($value) : (string)$value;
    }

    /**
     * Share of each answer per question, over the given responses.
     *
     * @param array<int, array<int|string,mixed>> $maps
     * @return array<int, array<string, float>>
     */
    private function answerDistributions(CustomForm $form, array $maps): array
    {
        $out = [];
        foreach ($form->getAllFields()->all() as $field) {
            if (!$this->isChoiceComparable($field)) {
                continue;
            }
            $counts = [];
            $n = 0;
            foreach ($maps as $map) {
                $value = $this->comparable($map[$field->id] ?? $map[(string)$field->id] ?? '');
                if ($value === '') {
                    continue;
                }
                $counts[$value] = ($counts[$value] ?? 0) + 1;
                $n++;
            }
            if ($n > 0) {
                $out[(int)$field->id] = array_map(static fn($c) => $c / $n, $counts);
            }
        }
        return $out;
    }

    private function answerSignature(CustomForm $form, array $values): string
    {
        $parts = [];
        foreach ($form->getAllFields()->all() as $field) {
            if (!$field->collectsAnswer() || in_array($field->type, [FormField::TYPE_TEXT, FormField::TYPE_TEXTAREA, FormField::TYPE_HTML, FormField::TYPE_FILE, FormField::TYPE_MAP], true)) {
                continue;
            }
            $v = $values[$field->id] ?? '';
            $parts[] = is_array($v) ? json_encode($v) : (string)$v;
        }
        return implode('|', $parts);
    }

    private function answerTexts(CustomForm $form, array $values): array
    {
        $out = [];
        foreach ($form->getAllFields()->all() as $field) {
            if (!in_array($field->type, [FormField::TYPE_TEXT, FormField::TYPE_TEXTAREA], true)) {
                continue;
            }
            $v = $values[$field->id] ?? '';
            $out[(int)$field->id] = $this->normalizeText(is_array($v) ? implode(' ', $v) : (string)$v);
        }
        return $out;
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        return $text;
    }

    private function valuesMatch(string $actual, string $expected): bool
    {
        $a = $this->normalizeText($actual);
        $e = $this->normalizeText($expected);
        if ($e === '') {
            return $a !== '';
        }
        return $a === $e;
    }

    /**
     * Exact match after normalisation. Also accepts the choice label when the
     * stored answer is an option code (or the reverse), so an instructed item
     * is not failed because the creator typed “Agree” and the value is “agree”.
     */
    private function attentionPasses(FormField $field, $actual, string $expected): bool
    {
        $parts = is_array($actual) ? array_map('strval', $actual) : [(string)$actual];
        foreach ($parts as $part) {
            if ($this->valuesMatch($part, $expected)) {
                return true;
            }
        }
        foreach ($field->getChoicePairs() as $pair) {
            $code = (string)($pair['code'] ?? '');
            $label = (string)($pair['label'] ?? '');
            $expectedHits = ($code !== '' && $this->valuesMatch($expected, $code))
                || ($label !== '' && $this->valuesMatch($expected, $label));
            if (!$expectedHits) {
                continue;
            }
            foreach ($parts as $part) {
                if (($code !== '' && $this->valuesMatch($part, $code))
                    || ($label !== '' && $this->valuesMatch($part, $label))) {
                    return true;
                }
            }
        }
        return false;
    }

    private function flag(string $category, string $code, string $message, array $extra = []): array
    {
        return array_merge([
            'category' => $category,
            'code' => $code,
            'message' => $message,
            'severity' => $extra['severity'] ?? 1,
        ], $extra);
    }
}
