<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use Yii;

/**
 * Save-and-continue-later: draft answers keyed by a human-friendly resume code.
 */
class ResumeService
{
    public const CODE_LENGTH = 12;
    /** A code stops working this many days after the draft was last saved (DAT-14). */
    public const SETTING_CODE_DAYS = 'resume_code_days';
    public const DEFAULT_CODE_DAYS = 60;
    /** Failed code lookups allowed per session and network in ten minutes. */
    public const LOOKUP_LIMIT = 10;

    /**
     * A new plain code. Only its keyed hash is stored (DAT-14); the plain code lives in the
     * respondent's session for display and email, and is never written to the database.
     */
    public function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max = strlen($alphabet) - 1;
        do {
            $raw = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $raw .= $alphabet[random_int(0, $max)];
            }
            $code = substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-' . substr($raw, 8, 4);
        } while (FormAnswer::find()->where(['resume_code' => self::hash($code)])->exists());

        return $code;
    }

    /** Keyed hash of a (normalised) code, 32 hex characters, as stored in resume_code. */
    public static function hash(string $code): string
    {
        return substr(hash_hmac('sha256', 'resume|' . $code, self::secret()), 0, 32);
    }

    private static function secret(): string
    {
        $module = Yii::$app->getModule('thiscovery-forms');
        $secret = $module ? (string)$module->settings->get('resume_secret', '') : '';
        if ($secret === '' && $module) {
            $secret = bin2hex(random_bytes(32));
            $module->settings->set('resume_secret', $secret);
        }
        return $secret;
    }

    /** Keep the plain code for this form in the respondent's session. */
    public static function rememberPlain(int $formId, string $plain): void
    {
        if (Yii::$app->has('session')) {
            Yii::$app->session->set('cf-resume-plain-' . $formId, $plain);
        }
    }

    /** The plain code for this draft, if this session created or entered it. */
    public static function plainFor(?FormAnswer $answer): string
    {
        if (!$answer || !$answer->resume_code || !Yii::$app->has('session')) {
            return '';
        }
        $plain = (string)Yii::$app->session->get('cf-resume-plain-' . (int)$answer->form_id, '');
        return $plain !== '' && hash_equals((string)$answer->resume_code, self::hash($plain)) ? $plain : '';
    }

    /** True when a posted code belongs to this draft. */
    public function matches(FormAnswer $answer, string $code): bool
    {
        $code = $this->normalizeCode($code);
        return $code !== '' && $answer->resume_code !== null && hash_equals((string)$answer->resume_code, self::hash($code));
    }

    public static function codeDays(): int
    {
        $module = Yii::$app->getModule('thiscovery-forms');
        $raw = $module ? $module->settings->get(self::SETTING_CODE_DAYS) : null;
        return max(0, (int)($raw === null || $raw === '' ? self::DEFAULT_CODE_DAYS : $raw));
    }

    private static function lookupKey(): string
    {
        $session = Yii::$app->has('session') ? (string)Yii::$app->session->id : '';
        $ip = (string)(Yii::$app->request->userIP ?? '');
        return 'cf-resume-fail-' . hash('sha256', $session . '|' . FormActionService::networkOf($ip));
    }

    public static function lookupsBlocked(): bool
    {
        return (int)Yii::$app->cache->get(self::lookupKey()) >= self::LOOKUP_LIMIT;
    }

    public function normalizeCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = preg_replace('/[^A-Z0-9]/', '', $code) ?? '';
        if (strlen($code) === self::CODE_LENGTH) {
            return substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 4);
        }
        return $code;
    }

    public function findDraftByCode(CustomForm $form, string $code): ?FormAnswer
    {
        $code = $this->normalizeCode($code);
        if ($code === '' || self::lookupsBlocked()) {
            return null;
        }
        $query = FormAnswer::find()
            ->where([
                'form_id' => $form->id,
                'resume_code' => self::hash($code),
                'status' => FormAnswer::STATUS_IN_PROGRESS,
            ]);
        $days = self::codeDays();
        if ($days > 0) {
            $query->andWhere(['>=', 'updated_at', date('Y-m-d H:i:s', time() - $days * 86400)]);
        }
        $draft = $query->one();
        if (!$draft) {
            $key = self::lookupKey();
            Yii::$app->cache->set($key, (int)Yii::$app->cache->get($key) + 1, 600);
            return null;
        }
        self::rememberPlain((int)$form->id, $code);
        return $draft;
    }

    /**
     * Persist partial answers without requiring required fields.
     */
    public function saveDraft(
        CustomForm $form,
        SubmitForm $submit,
        ?FormAnswer $existing = null,
        bool $anonymous = false,
        ?string $email = null,
        ?int $currentPage = null,
        bool $isTest = false
    ): ?FormAnswer {
        if ($existing && !$existing->isInProgress()) {
            $submit->addError('values', Yii::t('ThiscoveryFormsModule.base', 'This response has already been submitted.'));
            return null;
        }

        $answer = $submit->save($existing, $anonymous || $form->allowsAnonymous() || $isTest, true, $isTest);
        if (!$answer) {
            return null;
        }

        $dirty = [];
        if ($email !== null && $email !== '') {
            $answer->resume_email = $email;
            $dirty[] = 'resume_email';
        }
        if ($currentPage !== null) {
            $answer->current_page = max(0, $currentPage);
            $dirty[] = 'current_page';
            if ($answer->hasAttribute('current_page_key')) {
                $key = trim((string)Yii::$app->request->post('current_page_key', ''));
                $answer->current_page_key = $key !== '' ? substr($key, 0, 64) : null;
                $dirty[] = 'current_page_key';
            }
        }
        $instance = Yii::$app->request->post('current_instance_key', null);
        if (!empty($submit->rosterChanged)) {
            $answer->current_instance_key = substr((string)$submit->rosterKey, 0, 191);
            $dirty[] = 'current_instance_key';
        } elseif ($instance !== null && \humhub\modules\thiscoveryForms\services\LoopService::columnReady()) {
            $answer->current_instance_key = substr((string)$instance, 0, 191);
            $dirty[] = 'current_instance_key';
        }
        if ($dirty) {
            $dirty[] = 'updated_at';
            $answer->save(false, $dirty);
        }

        return $answer;
    }

    /**
     * Remove autosave snapshot rows: keep one in-progress draft per person per fill session,
     * and drop leftovers from sessions that already completed.
     *
     * @return int Number of in-progress answers deleted
     */
    public function collapseSnapshotDrafts(): int
    {
        $drafts = FormAnswer::find()
            ->where([
                'status' => FormAnswer::STATUS_IN_PROGRESS,
                'is_test' => 0,
            ])
            ->andWhere(['IS NOT', 'created_by', null])
            ->orderBy(['form_id' => SORT_ASC, 'created_by' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $groups = [];
        foreach ($drafts as $draft) {
            $key = (int)$draft->form_id . ':' . (int)$draft->created_by;
            $groups[$key][] = $draft;
        }

        $deleted = 0;
        foreach ($groups as $rows) {
            $chains = $this->splitDraftChains($rows);
            foreach ($chains as $chain) {
                $deleted += $this->collapseChain($chain);
            }
        }

        return $deleted;
    }

    /**
     * @param FormAnswer[] $rows
     * @return FormAnswer[][]
     */
    protected function splitDraftChains(array $rows): array
    {
        $chains = [];
        $current = [];
        $prevTs = null;
        foreach ($rows as $row) {
            $ts = strtotime((string)$row->created_at) ?: 0;
            if ($current && $prevTs !== null && ($ts - $prevTs) > 120) {
                $chains[] = $current;
                $current = [];
            }
            $current[] = $row;
            $prevTs = $ts;
        }
        if ($current) {
            $chains[] = $current;
        }
        return $chains;
    }

    /**
     * @param FormAnswer[] $chain
     */
    protected function collapseChain(array $chain): int
    {
        if (!$chain) {
            return 0;
        }
        $first = $chain[0];
        $last = $chain[count($chain) - 1];
        $start = date('Y-m-d H:i:s', (strtotime((string)$first->created_at) ?: time()) - 5);
        $end = date('Y-m-d H:i:s', (strtotime((string)$last->updated_at ?: $last->created_at) ?: time()) + 15);

        $completedNearby = FormAnswer::find()
            ->where([
                'form_id' => $first->form_id,
                'status' => FormAnswer::STATUS_COMPLETE,
                'is_test' => 0,
            ])
            ->andWhere(['>=', 'created_at', $start])
            ->andWhere(['<=', 'created_at', $end])
            ->andWhere([
                'or',
                ['created_by' => $first->created_by],
                ['created_by' => null],
            ])
            ->exists();

        $keepId = $completedNearby ? 0 : (int)$last->id;
        $deleted = 0;
        foreach ($chain as $row) {
            if ((int)$row->id === $keepId) {
                continue;
            }
            if ($row->delete()) {
                $deleted++;
            }
        }
        return $deleted;
    }

    public function sendResumeEmail(CustomForm $form, FormAnswer $answer, string $email, string $plain = ''): bool
    {
        $email = trim($email);
        $plain = $plain !== '' ? $this->normalizeCode($plain) : self::plainFor($answer);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$answer->resume_code || !$this->matches($answer, $plain)) {
            return false;
        }
        // Not a way to send mail to arbitrary addresses: the same caps as action emails (DAT-14).
        if (!FormActionService::emailAllowed((int)$form->id, $email, (string)(Yii::$app->request->userIP ?? ''))) {
            return false;
        }

        $answer->resume_email = $email;
        $answer->save(false, ['resume_email', 'updated_at']);

        $resumeUrl = Url::toResume($form, $plain, true);
        $subject = Yii::t('ThiscoveryFormsModule.base', 'Your saved response code for "{title}"', [
            'title' => $form->title,
        ]);
        $body = Yii::t(
            'ThiscoveryFormsModule.base',
            "You saved your progress on the form \"{title}\".\n\nYour resume code is:\n{code}\n\nOpen this link to continue:\n{url}\n\nKeep this code private. Anyone with it can continue your response.",
            [
                'title' => $form->title,
                'code' => $plain,
                'url' => $resumeUrl,
            ]
        );

        try {
            return (bool)Yii::$app->mailer->compose()
                ->setTo($email)
                ->setSubject($subject)
                ->setTextBody($body)
                ->send();
        } catch (\Throwable $e) {
            Yii::error('Thiscovery Forms resume email failed: ' . $e->getMessage(), 'thiscovery-forms');
            return false;
        }
    }
}
