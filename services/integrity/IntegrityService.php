<?php

namespace humhub\modules\thiscoveryForms\services\integrity;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAccessToken;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormIntegrityAudit;
use humhub\modules\thiscoveryForms\models\FormIntegrityMeta;
use humhub\modules\thiscoveryForms\services\FillContext;
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

    public function settings(?CustomForm $form): array
    {
        return IntegritySettings::forForm($form);
    }

    public function isEnabled(?CustomForm $form): bool
    {
        return IntegritySettings::isOn($this->settings($form), 'enabled');
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

    public function onProgress(CustomForm $form, FormAnswer $answer, array $post): void
    {
        if (!$this->isEnabled($form)) {
            return;
        }
        $meta = $this->ensureMeta($form, $answer);
        $this->applyClientTimings($meta, $post);
        if (!$meta->started_at) {
            $meta->started_at = Yii::$app->session->get(self::START_PREFIX . (int)$form->id) ?: $answer->created_at;
        }
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
    public function gateSubmit(CustomForm $form, array $post, FillContext $ctx): ?string
    {
        $cfg = $this->settings($form);
        if (!IntegritySettings::isOn($cfg, 'enabled')) {
            return $this->gateAccess($form, $cfg, $ctx);
        }
        $accessError = $this->gateAccess($form, $cfg, $ctx);
        if ($accessError) {
            return $accessError;
        }
        if (IntegritySettings::isOn($cfg, 'rate_limiting') && $this->isRateLimited($form, $cfg, true)) {
            return Yii::t('ThiscoveryFormsModule.base', 'Too many submissions from this connection. Please wait a few minutes and try again.');
        }
        if (!IntegritySettings::isOn($cfg, 'captcha') || trim((string)($cfg['turnstile_site_key'] ?? '')) === '') {
            $this->clearCaptchaRequired($form);
        }
        $suspicious = $this->looksSuspiciousBeforeSave($form, $post, $cfg);
        // Sticky session bit: honeypot / missing session are only known at submit,
        // so the next render must still show Turnstile and require a pass.
        $needCaptcha = $this->shouldShowCaptcha($cfg, $suspicious || $this->isCaptchaRequired($form));
        if ($needCaptcha) {
            if (!$this->verifyCaptcha($cfg, $post)) {
                $this->markCaptchaRequired($form);
                return Yii::t('ThiscoveryFormsModule.base', 'Please complete the verification check and try again.');
            }
            $this->clearCaptchaRequired($form);
        }
        return null;
    }

    public function onComplete(CustomForm $form, FormAnswer $answer, array $post, FillContext $ctx): ?FormIntegrityMeta
    {
        $cfg = $this->settings($form);
        $rawToken = trim((string)Yii::$app->request->post('access_token', Yii::$app->request->get('access', Yii::$app->request->post('panel_token', Yii::$app->request->get('token', '')))));
        if (!IntegritySettings::isOn($cfg, 'enabled')) {
            $this->consumeAccessToken($form, $rawToken, $cfg);
            Yii::$app->session->remove(self::START_PREFIX . (int)$form->id);
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

        $suspicious = $meta->honeypot_triggered || !$sessionOk || $meta->rate_limited;
        $needCaptcha = $this->shouldShowCaptcha($cfg, $suspicious);
        $meta->captcha_shown = $needCaptcha ? 1 : 0;
        if ($needCaptcha) {
            $meta->captcha_passed = $this->verifyCaptcha($cfg, $post) ? 1 : 0;
        } else {
            $meta->captcha_passed = null;
        }

        $flags = [];
        $scores = [
            'bot' => 0.0, 'duplicate' => 0.0, 'speed' => 0.0, 'straightline' => 0.0,
            'attention' => 0.0, 'consistency' => 0.0, 'freetext' => 0.0, 'similarity' => 0.0,
        ];

        if (IntegritySettings::isOn($cfg, 'bot_protection')) {
            [$scores['bot'], $botFlags] = $this->analyseBot($meta, $cfg);
            $flags = array_merge($flags, $botFlags);
        }
        if (IntegritySettings::isOn($cfg, 'duplicate_detection')) {
            [$scores['duplicate'], $dupFlags] = $this->analyseDuplicate($form, $answer, $meta, $cfg);
            $flags = array_merge($flags, $dupFlags);
        }
        if (IntegritySettings::isOn($cfg, 'speed_detection')) {
            $meta->median_seconds = $this->medianDuration($form, (int)$answer->id);
            [$scores['speed'], $speedFlags] = $this->analyseSpeed($meta, $cfg);
            $flags = array_merge($flags, $speedFlags);
        }
        $values = $answer->getValuesMap();
        if (IntegritySettings::isOn($cfg, 'straightline_detection')) {
            [$scores['straightline'], $slFlags] = $this->analyseStraightline($form, $values, $cfg);
            $flags = array_merge($flags, $slFlags);
        }
        if (IntegritySettings::isOn($cfg, 'attention_checks')) {
            [$scores['attention'], $attFlags] = $this->analyseAttention($form, $values);
            $flags = array_merge($flags, $attFlags);
        }
        if (IntegritySettings::isOn($cfg, 'consistency_checks')) {
            [$scores['consistency'], $conFlags] = $this->analyseConsistency($form, $values, $cfg);
            $flags = array_merge($flags, $conFlags);
        }
        if (IntegritySettings::isOn($cfg, 'freetext_checks')) {
            [$scores['freetext'], $ftFlags] = $this->analyseFreetext($form, $values, $cfg);
            $flags = array_merge($flags, $ftFlags);
        }
        if (IntegritySettings::isOn($cfg, 'similarity_detection')) {
            [$scores['similarity'], $simFlags, $similarIds] = $this->analyseSimilarity($form, $answer, $values, $cfg);
            $flags = array_merge($flags, $simFlags);
            $meta->similar_answer_ids_json = $similarIds ? json_encode($similarIds) : null;
        }

        $meta->bot_score = $scores['bot'];
        $meta->duplicate_score = $scores['duplicate'];
        $meta->speed_score = $scores['speed'];
        $meta->straightline_score = $scores['straightline'];
        $meta->attention_score = $scores['attention'];
        $meta->consistency_score = $scores['consistency'];
        $meta->freetext_score = $scores['freetext'];
        $meta->similarity_score = $scores['similarity'];
        $meta->setFlags($flags);

        if (IntegritySettings::isOn($cfg, 'integrity_scoring')) {
            $meta->overall_score = $this->overallScore($scores, $cfg);
            $autoStatus = $this->statusFromScore((float)$meta->overall_score, $scores, $cfg);
            if (!$meta->status_override) {
                $fromStatus = $meta->integrity_status;
                $fromAnalysis = $meta->analysis_status;
                $meta->integrity_status = $autoStatus;
                $meta->analysis_status = $this->analysisFromStatus($autoStatus);
                if ($autoStatus === FormIntegrityMeta::STATUS_EXCLUDED) {
                    $meta->exclusion_reason = Yii::t(
                        'ThiscoveryFormsModule.base',
                        'Automatically excluded: quality score {score} with multiple integrity signals.',
                        ['score' => number_format((float)$meta->overall_score, 0)]
                    );
                    FormIntegrityAudit::record(
                        (int)$form->id,
                        (int)$answer->id,
                        'auto_exclude',
                        $fromStatus,
                        $autoStatus,
                        $meta->exclusion_reason
                    );
                    if ($fromAnalysis !== $meta->analysis_status) {
                        FormIntegrityAudit::record(
                            (int)$form->id,
                            (int)$answer->id,
                            'analysis_status',
                            $fromAnalysis,
                            $meta->analysis_status,
                            $meta->exclusion_reason
                        );
                    }
                }
            }
        } else {
            $meta->overall_score = 100;
            if (!$meta->status_override) {
                $meta->integrity_status = FormIntegrityMeta::STATUS_TRUSTED;
                $meta->analysis_status = FormIntegrityMeta::ANALYSIS_INCLUDED;
            }
        }

        $this->consumeAccessToken($form, $rawToken, $cfg);
        $meta->save(false);
        $this->clearCaptchaRequired($form);
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
        if (!IntegritySettings::isOn($cfg, 'captcha') || ($cfg['turnstile_site_key'] ?? '') === '') {
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

    private function gateAccess(CustomForm $form, array $cfg, FillContext $ctx): ?string
    {
        $mode = (string)($cfg['access_mode'] ?? IntegritySettings::ACCESS_PUBLIC);
        $user = Yii::$app->user;
        if ($mode === IntegritySettings::ACCESS_PUBLIC) {
            return null;
        }
        $raw = trim((string)Yii::$app->request->get('access', Yii::$app->request->post('access_token', Yii::$app->request->get('token', Yii::$app->request->post('panel_token', '')))));
        if ($mode === IntegritySettings::ACCESS_UNIQUE) {
            if ($ctx->tokenAccess && $ctx->member) {
                return null;
            }
            $token = $this->findAccessToken($form, $raw);
            if ($token && $token->isUsable()) {
                return null;
            }
            return Yii::t('ThiscoveryFormsModule.base', 'This survey needs a unique invitation link.');
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
        if (!IntegritySettings::isOn($cfg, 'captcha') || ($cfg['turnstile_site_key'] ?? '') === '') {
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

    private function verifyCaptcha(array $cfg, array $post): bool
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

    private function isRateLimited(CustomForm $form, array $cfg, bool $increment): bool
    {
        $ip = (string)Yii::$app->request->userIP;
        $session = Yii::$app->session->id ?: 'none';
        $key = 'cf-int-rate-' . $form->id . '-' . IntegritySettings::hashValue($ip . '|' . $session);
        $limit = max(1, (int)($cfg['rate_limit_count'] ?? 8));
        $window = max(1, (int)($cfg['rate_limit_window'] ?? 10)) * 60;
        $cache = Yii::$app->cache;
        $count = (int)$cache->get($key);
        if ($increment) {
            $count++;
            $cache->set($key, $count, $window);
        }
        return $count > $limit;
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
        if (!empty($data['startedAt']) && !$meta->started_at) {
            $ts = (int)$data['startedAt'];
            if ($ts > 1000000000000) {
                $ts = (int)floor($ts / 1000);
            }
            if ($ts > 0) {
                $meta->started_at = date('Y-m-d H:i:s', $ts);
            }
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
        if ($meta->ip_hash || $meta->session_hash || $meta->ip_network_hash) {
            $orExact = ['or'];
            if ($meta->ip_hash) {
                $orExact[] = ['m.ip_hash' => $meta->ip_hash];
            }
            if ($meta->session_hash) {
                $orExact[] = ['m.session_hash' => $meta->session_hash];
            }
            $srcDup = 0;
            if (count($orExact) > 1) {
                $srcDup = (int)FormIntegrityMeta::find()->alias('m')
                    ->innerJoin('custom_form_answer a', 'a.id = m.answer_id')
                    ->andWhere(['m.form_id' => $form->id, 'a.is_test' => 0, 'a.status' => FormAnswer::STATUS_COMPLETE])
                    ->andWhere(['<>', 'm.answer_id', $answer->id])
                    ->andWhere($orExact)
                    ->count();
            }
            if ($srcDup) {
                $hits++;
                $flags[] = $this->flag('duplicate', 'same_source', Yii::t('ThiscoveryFormsModule.base', 'Another response from the same IP or browser session'));
            } elseif ($meta->ip_network_hash) {
                // Soft signal: same /24 (or IPv6 prefix) without an exact IP match.
                $netDup = (int)FormIntegrityMeta::find()->alias('m')
                    ->innerJoin('custom_form_answer a', 'a.id = m.answer_id')
                    ->andWhere(['m.form_id' => $form->id, 'm.ip_network_hash' => $meta->ip_network_hash, 'a.is_test' => 0, 'a.status' => FormAnswer::STATUS_COMPLETE])
                    ->andWhere(['<>', 'm.answer_id', $answer->id])
                    ->count();
                if ($netDup) {
                    $hits++;
                    $flags[] = $this->flag('duplicate', 'same_network', Yii::t('ThiscoveryFormsModule.base', 'Another response from a nearby network address (shared network pattern)'));
                }
            }
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
        $min = max(5, (int)($cfg['speed_min_seconds'] ?? 15));
        $percent = max(5, min(90, (int)($cfg['speed_percent'] ?? 40)));
        $median = (int)$meta->median_seconds;
        $flags = [];
        $score = 0.0;
        if ($duration > 0 && $duration < $min) {
            $score = $weight * 0.7;
            $flags[] = $this->flag('speed', 'absolute', Yii::t('ThiscoveryFormsModule.base', 'Completed in {n} seconds (threshold {min}s)', [
                'n' => $duration,
                'min' => $min,
            ]));
        } elseif ($median >= $min && $duration > 0 && $duration < ($median * $percent / 100)) {
            $score = $weight * 0.55;
            $flags[] = $this->flag('speed', 'relative', Yii::t('ThiscoveryFormsModule.base', 'Completed in {n}s versus typical {median}s', [
                'n' => $duration,
                'median' => $median,
            ]));
        }
        return [$score, $flags];
    }

    private function analyseStraightline(CustomForm $form, array $values, array $cfg): array
    {
        $weight = (float)($cfg['weight_straightline'] ?? 10);
        $minItems = max(3, (int)($cfg['straightline_min_items'] ?? 5));
        $flags = [];
        $score = 0.0;
        foreach ($form->fields as $field) {
            if (!in_array($field->type, [FormField::TYPE_GRID_SINGLE, FormField::TYPE_GRID_MULTI], true)) {
                continue;
            }
            $val = $values[$field->id] ?? null;
            if (!is_array($val) || count($val) < $minItems) {
                continue;
            }
            $flat = [];
            foreach ($val as $cell) {
                $flat[] = is_array($cell) ? implode('|', $cell) : (string)$cell;
            }
            $unique = array_unique($flat);
            if (count($unique) === 1 && $flat[0] !== '') {
                $score = max($score, $weight * 0.8);
                $flags[] = $this->flag('straightline', 'identical_grid', Yii::t('ThiscoveryFormsModule.base', 'Identical answers across “{label}”', [
                    'label' => $field->label,
                ]), ['field_id' => (int)$field->id]);
            }
        }
        $ratings = [];
        foreach ($form->fields as $field) {
            if ($field->type !== FormField::TYPE_RATING) {
                continue;
            }
            $v = $values[$field->id] ?? '';
            if ($v === '' || $v === null) {
                continue;
            }
            $ratings[] = (int)$v;
        }
        if (count($ratings) >= $minItems) {
            $seq = implode(',', $ratings);
            $asc = implode(',', range(min($ratings), max($ratings)));
            $desc = implode(',', range(max($ratings), min($ratings), -1));
            $unique = array_unique($ratings);
            if (count($unique) === 1) {
                $score = max($score, $weight * 0.75);
                $flags[] = $this->flag('straightline', 'flat_ratings', Yii::t('ThiscoveryFormsModule.base', 'No variation across rating questions'));
            } elseif ($seq === $asc || $seq === $desc) {
                $score = max($score, $weight * 0.7);
                $flags[] = $this->flag('straightline', 'sequential', Yii::t('ThiscoveryFormsModule.base', 'Sequential pattern in rating answers ({pattern})', [
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
            if (count($unique) === 1 && $answers[0] !== '') {
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
        foreach ($form->fields as $field) {
            if (!$field->collectsAnswer() || $field->isHiddenFromRespondent() || $field->isAttentionCheck()) {
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
            ];
        }
        $flush();

        return [$score, $flags];
    }

    private function analyseAttention(CustomForm $form, array $values): array
    {
        $flags = [];
        $failed = 0;
        $total = 0;
        foreach ($form->fields as $field) {
            if (!$field->isAttentionCheck()) {
                continue;
            }
            $total++;
            $expected = $field->getAttentionExpected();
            $actual = $values[$field->id] ?? '';
            $pass = $this->attentionPasses($field, $actual, $expected);
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

    private function analyseConsistency(CustomForm $form, array $values, array $cfg): array
    {
        $weight = (float)($cfg['weight_consistency'] ?? 10);
        $flags = [];
        $hits = 0;
        foreach (($cfg['consistency_rules'] ?? []) as $rule) {
            if (!is_array($rule) || empty($rule['conditions'])) {
                continue;
            }
            $matched = true;
            foreach ($rule['conditions'] as $cond) {
                $fieldId = (int)($cond['field_id'] ?? 0);
                $actual = $values[$fieldId] ?? '';
                $actualStr = is_array($actual) ? implode(',', $actual) : (string)$actual;
                $expected = (string)($cond['value'] ?? '');
                $op = $cond['operator'] ?? 'equals';
                $ok = match ($op) {
                    'not_equals' => !$this->valuesMatch($actualStr, $expected),
                    'contains' => $expected !== '' && mb_stripos($actualStr, $expected) !== false,
                    default => $this->valuesMatch($actualStr, $expected),
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

    private function analyseFreetext(CustomForm $form, array $values, array $cfg): array
    {
        $weight = (float)($cfg['weight_freetext'] ?? 10);
        $minChars = max(3, (int)($cfg['freetext_min_chars'] ?? 8));
        $flags = [];
        $hits = 0;
        $textTypes = [FormField::TYPE_TEXT, FormField::TYPE_TEXTAREA, FormField::TYPE_HTML];
        $seenNorm = [];
        foreach ($form->fields as $field) {
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

    private function analyseSimilarity(CustomForm $form, FormAnswer $answer, array $values, array $cfg): array
    {
        $weight = (float)($cfg['weight_similarity'] ?? 5);
        $threshold = max(50, min(99, (int)($cfg['similarity_threshold'] ?? 90)));
        $sig = $this->answerSignature($form, $values);
        $texts = $this->answerTexts($form, $values);
        $flags = [];
        $similarIds = [];
        $best = 0;
        $others = FormAnswer::find()->alias('a')
            ->andWhere(['a.form_id' => $form->id, 'a.is_test' => 0, 'a.status' => FormAnswer::STATUS_COMPLETE])
            ->andWhere(['<>', 'a.id', $answer->id])
            ->with('answerFields')
            ->orderBy(['a.id' => SORT_DESC])
            ->limit(250)
            ->all();
        /** @var FormAnswer $other */
        foreach ($others as $other) {
            $omap = $other->getValuesMap();
            $osig = $this->answerSignature($form, $omap);
            $choiceSim = 0;
            if ($sig !== '' && $osig !== '') {
                similar_text($sig, $osig, $choiceSim);
            }
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
        $score = $similarIds ? min($weight, $weight * (0.4 + min(0.6, count($similarIds) * 0.15))) : 0;
        return [$score, $flags, $similarIds];
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

    private function medianDuration(CustomForm $form, int $excludeId): int
    {
        $rows = FormIntegrityMeta::find()->alias('m')
            ->innerJoin('custom_form_answer a', 'a.id = m.answer_id')
            ->andWhere(['m.form_id' => $form->id, 'a.is_test' => 0, 'a.status' => FormAnswer::STATUS_COMPLETE])
            ->andWhere(['>', 'm.duration_seconds', 0])
            ->andWhere(['<>', 'm.answer_id', $excludeId])
            ->select('m.duration_seconds')
            ->orderBy(['m.duration_seconds' => SORT_ASC])
            ->column();
        $n = count($rows);
        if ($n < 3) {
            return 0;
        }
        $mid = (int)floor($n / 2);
        if ($n % 2) {
            return (int)$rows[$mid];
        }
        return (int)round(((int)$rows[$mid - 1] + (int)$rows[$mid]) / 2);
    }

    private function consumeAccessToken(CustomForm $form, string $raw, array $cfg): void
    {
        if ($raw === '') {
            return;
        }
        $token = $this->findAccessToken($form, $raw);
        if (!$token) {
            return;
        }
        $token->use_count = (int)$token->use_count + 1;
        $token->last_used_at = date('Y-m-d H:i:s');
        $token->save(false);
    }

    private function answerSignature(CustomForm $form, array $values): string
    {
        $parts = [];
        foreach ($form->fields as $field) {
            if (!$field->collectsAnswer() || in_array($field->type, [FormField::TYPE_TEXT, FormField::TYPE_TEXTAREA, FormField::TYPE_HTML, FormField::TYPE_FILE, FormField::TYPE_MAP], true)) {
                continue;
            }
            $v = $values[$field->id] ?? '';
            $parts[] = $field->id . ':' . (is_array($v) ? json_encode($v) : (string)$v);
        }
        return implode('|', $parts);
    }

    private function answerTexts(CustomForm $form, array $values): array
    {
        $out = [];
        foreach ($form->fields as $field) {
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
