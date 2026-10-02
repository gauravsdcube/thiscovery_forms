<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\SecureCode;
use humhub\modules\thiscoveryForms\models\SecureEvent;
use humhub\modules\thiscoveryForms\models\SecureRelease;
use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\services\integrity\IntegritySettings;
use Yii;

/**
 * Prepared files for a named contact. The link and the code are stored only as keyed hashes.
 */
class SecureSendService
{
    public const MIN_MINUTES = 5;
    public const MAX_MINUTES = 10080;
    public const DEFAULT_MINUTES = 60;
    public const MAX_FAILURES = 5;
    public const CONTACT_COOLDOWN_SECONDS = 120;
    public const CONTACT_MAX_PER_HOUR = 5;
    public const MAX_BYTES = 20971520;
    public const GRANT_SECONDS = 120;

    private const ALLOWED_EXTENSIONS = ['csv', 'txt', 'tsv', 'xlsx', 'xls', 'pdf', 'zip', 'json'];

    public static function enabled(): bool
    {
        return Module::secureSendEnabled();
    }

    public static function minutes(CustomForm $form): int
    {
        $value = (int)$form->getSetting('secure_send_minutes', self::DEFAULT_MINUTES);
        return self::clampMinutes($value);
    }

    public static function saveMinutes(CustomForm $form, int $minutes): void
    {
        $form->setSetting('secure_send_minutes', self::clampMinutes($minutes));
        $form->save(false, ['settings_json']);
    }

    public static function clampMinutes(int $minutes): int
    {
        return max(self::MIN_MINUTES, min(self::MAX_MINUTES, $minutes));
    }

    public static function maskEmail(string $email): string
    {
        $email = strtolower(trim($email));
        $at = strrpos($email, '@');
        if ($at === false || $at === 0) {
            return '';
        }
        return substr($email, 0, 1) . '***@' . substr($email, $at + 1);
    }

    /**
     * @param array<string, mixed> $params export choices frozen into the file
     * @return array{release:SecureRelease,link:string}|null
     */
    public function createFromExport(
        CustomForm $form,
        string $label,
        string $contactName,
        string $contactEmail,
        array $params,
        ?int $actorId,
        array $requestMeta = []
    ): ?array {
        $contact = $this->contact($label, $contactName, $contactEmail);
        if ($contact === null) {
            return null;
        }
        $export = new ExportService();
        [$fh, $rows] = $export->toCsvHandle($form, $this->exportParams($params), true);
        rewind($fh);
        $stored = $this->writeStream($fh);
        if ($stored === null) {
            return null;
        }
        $release = $this->insertRelease(
            $form,
            $contact,
            SecureRelease::SOURCE_EXPORT,
            $stored,
            'answers.csv',
            $rows,
            $actorId,
            $requestMeta,
            ['export' => $this->exportParams($params)]
        );
        return $release;
    }

    /**
     * @return array{release:SecureRelease,link:string}|null
     */
    public function createFromUpload(
        CustomForm $form,
        string $label,
        string $contactName,
        string $contactEmail,
        string $tmpPath,
        string $originalName,
        ?int $actorId,
        array $requestMeta = []
    ): ?array {
        $contact = $this->contact($label, $contactName, $contactEmail);
        if ($contact === null) {
            return null;
        }
        $safeName = $this->safeFileName($originalName);
        $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return null;
        }
        if (!is_file($tmpPath) || filesize($tmpPath) === 0 || filesize($tmpPath) > self::MAX_BYTES) {
            return null;
        }
        $in = fopen($tmpPath, 'rb');
        if ($in === false) {
            return null;
        }
        $stored = $this->writeStream($in);
        if ($stored === null) {
            return null;
        }
        return $this->insertRelease(
            $form,
            $contact,
            SecureRelease::SOURCE_UPLOAD,
            $stored,
            $safeName,
            null,
            $actorId,
            $requestMeta
        );
    }

    /**
     * @return array{release:SecureRelease,link:?string}|null null when the input is rejected
     */
    public function changeContact(
        SecureRelease $release,
        string $name,
        string $email,
        ?int $actorId,
        array $requestMeta = []
    ): ?array {
        if ($release->status !== SecureRelease::STATUS_ACTIVE) {
            return null;
        }
        $name = $this->cleanText($name, 160);
        $email = strtolower(trim($email));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $emailChanged = $email !== strtolower((string)$release->contact_email);
        $previousEmail = (string)$release->contact_email;
        $previousName = (string)$release->contact_name;
        $link = null;
        $now = $this->now();
        $tx = Yii::$app->db->beginTransaction();
        try {
            $this->lockRelease((int)$release->id);
            $release->refresh();
            if ($release->status !== SecureRelease::STATUS_ACTIVE) {
                $tx->rollBack();
                return null;
            }
            $release->contact_name = $name;
            $release->contact_email = $email;
            if ($emailChanged) {
                $token = $this->newToken();
                $release->link_hash = $this->linkHash($token);
                $release->failed_attempts = 0;
                $link = $this->absoluteLink($token);
                $this->revokeOpenCodes((int)$release->id, $now);
            }
            $release->save(false);
            $this->event($release, 'contact_changed', $actorId, $requestMeta, [
                'from_name' => $previousName,
                'to_name' => $name,
                'from_email' => $previousEmail,
                'to_email' => $email,
            ]);
            if ($emailChanged) {
                $this->event($release, 'link_issued', $actorId, $requestMeta, []);
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error('Secure send contact change failed: ' . $e->getMessage(), 'thiscovery-forms');
            return null;
        }
        return ['release' => $release, 'link' => $link];
    }

    /**
     * Emails a new one-time code. A failed email leaves the previous code in place.
     */
    public function sendCode(SecureRelease $release, string $source, ?int $actorId, array $requestMeta = []): string
    {
        if (!self::enabled() || $release->status !== SecureRelease::STATUS_ACTIVE) {
            return 'unavailable';
        }
        $source = $source === SecureCode::SOURCE_CONTACT ? SecureCode::SOURCE_CONTACT : SecureCode::SOURCE_MANAGER;
        if ($source === SecureCode::SOURCE_CONTACT && $this->ipLimited((string)($requestMeta['ip'] ?? ''))) {
            return 'cooldown';
        }
        if ($source === SecureCode::SOURCE_CONTACT && !$this->contactMayRequest($release)) {
            return 'cooldown';
        }
        $plain = $this->newCode();
        $hash = $this->codeHash((int)$release->id, $plain);
        $form = $release->form;
        $minutes = $form ? self::minutes($form) : self::DEFAULT_MINUTES;
        $expires = date('Y-m-d H:i:s', time() + $minutes * 60);
        $now = $this->now();
        $tx = Yii::$app->db->beginTransaction();
        try {
            $this->lockRelease((int)$release->id);
            $release->refresh();
            if (!self::enabled() || $release->status !== SecureRelease::STATUS_ACTIVE) {
                $tx->rollBack();
                return 'unavailable';
            }
            if ($source === SecureCode::SOURCE_CONTACT && !$this->contactMayRequest($release)) {
                $tx->rollBack();
                return 'cooldown';
            }
            if (!$this->deliver((string)$release->contact_email, $plain, $expires)) {
                $tx->rollBack();
                return 'mail';
            }
            $this->revokeOpenCodes((int)$release->id, $now);
            $code = new SecureCode();
            $code->release_id = (int)$release->id;
            $code->code_hash = $hash;
            $code->expires_at = $expires;
            $code->source = $source;
            $code->created_by = $source === SecureCode::SOURCE_MANAGER ? $actorId : null;
            $code->created_at = $now;
            $code->save(false);
            $release->failed_attempts = 0;
            $release->save(false, ['failed_attempts']);
            $this->event($release, 'code_sent', $actorId, $requestMeta, [
                'source' => $source,
                'expires_at' => $expires,
            ]);
            $tx->commit();
            return 'sent';
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error('Secure send could not send a code: ' . $e->getMessage(), 'thiscovery-forms');
            return 'mail';
        }
    }

    public function revokeCodes(SecureRelease $release, ?int $actorId, array $requestMeta = []): bool
    {
        if ($release->status !== SecureRelease::STATUS_ACTIVE) {
            return false;
        }
        $this->revokeOpenCodes((int)$release->id, $this->now());
        $this->event($release, 'code_revoked', $actorId, $requestMeta, []);
        return true;
    }

    /**
     * @return string|null the new link, shown once
     */
    public function rotateLink(SecureRelease $release, ?int $actorId, array $requestMeta = []): ?string
    {
        if ($release->status !== SecureRelease::STATUS_ACTIVE) {
            return null;
        }
        $token = $this->newToken();
        $tx = Yii::$app->db->beginTransaction();
        try {
            $this->lockRelease((int)$release->id);
            $release->refresh();
            if ($release->status !== SecureRelease::STATUS_ACTIVE) {
                $tx->rollBack();
                return null;
            }
            $release->link_hash = $this->linkHash($token);
            $release->failed_attempts = 0;
            $release->save(false, ['link_hash', 'failed_attempts']);
            $this->revokeOpenCodes((int)$release->id, $this->now());
            $this->event($release, 'link_issued', $actorId, $requestMeta, []);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error('Secure send could not issue a new link: ' . $e->getMessage(), 'thiscovery-forms');
            return null;
        }
        return $this->absoluteLink($token);
    }

    public function revokeRelease(SecureRelease $release, ?int $actorId, array $requestMeta = []): bool
    {
        if ($release->status === SecureRelease::STATUS_REVOKED) {
            return true;
        }
        $now = $this->now();
        $this->revokeOpenCodes((int)$release->id, $now);
        $this->deleteStoredFile($release);
        $release->status = SecureRelease::STATUS_REVOKED;
        $release->storage_name = null;
        $release->revoked_at = $now;
        $release->revoked_by = $actorId;
        $release->save(false);
        $this->event($release, 'file_revoked', $actorId, $requestMeta, []);
        return true;
    }

    public function findByToken(string $token): ?SecureRelease
    {
        $token = trim($token);
        if (!self::enabled() || !preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
            return null;
        }
        $release = SecureRelease::find()->where([
            'link_hash' => $this->linkHash($token),
            'status' => SecureRelease::STATUS_ACTIVE,
        ])->one();
        return $release instanceof SecureRelease && $release->isActive() ? $release : null;
    }

    /**
     * Checks a code and, on success, spends it. Returns ok, invalid, locked, or unavailable.
     */
    public function redeem(SecureRelease $release, string $code, array $requestMeta = []): string
    {
        if (!self::enabled() || !$release->isActive()) {
            return 'unavailable';
        }
        if ($this->ipLimited((string)($requestMeta['ip'] ?? ''))) {
            return 'locked';
        }
        $release->refresh();
        if ((int)$release->failed_attempts >= self::MAX_FAILURES) {
            $this->event($release, 'code_failed', null, $requestMeta, ['reason' => 'locked']);
            return 'locked';
        }
        $normalized = $this->normalizeCode($code);
        $latest = SecureCode::find()
            ->where(['release_id' => (int)$release->id, 'revoked_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->one();
        $expected = $normalized === '' ? '' : $this->codeHash((int)$release->id, $normalized);
        $matches = $latest instanceof SecureCode
            && strlen((string)$latest->code_hash) === 64
            && strlen($expected) === 64
            && hash_equals((string)$latest->code_hash, $expected);
        if (!$matches || !$latest instanceof SecureCode) {
            $this->registerFailure($release, $requestMeta);
            return (int)$release->failed_attempts >= self::MAX_FAILURES ? 'locked' : 'invalid';
        }
        if ($latest->used_at || strtotime((string)$latest->expires_at) <= time()) {
            $this->event($release, 'code_failed', null, $requestMeta, ['reason' => 'expired']);
            return 'invalid';
        }
        $spent = Yii::$app->db->createCommand()->update(SecureCode::tableName(), [
            'used_at' => $this->now(),
        ], [
            'and',
            ['id' => (int)$latest->id],
            ['code_hash' => $expected],
            ['used_at' => null],
            ['revoked_at' => null],
            ['>', 'expires_at', $this->now()],
        ])->execute();
        if ($spent !== 1) {
            $this->event($release, 'code_failed', null, $requestMeta, ['reason' => 'spent']);
            return 'invalid';
        }
        return 'ok';
    }

    /**
     * @param array<string, mixed> $requestMeta
     */
    public function recordDownload(SecureRelease $release, array $requestMeta = []): void
    {
        $this->event($release, 'download', null, $requestMeta, [
            'bytes' => (int)$release->byte_size,
            'file' => (string)$release->original_name,
        ]);
    }

    public function storedPath(SecureRelease $release): ?string
    {
        $name = (string)$release->storage_name;
        if (!preg_match('/^[a-f0-9]{32}$/', $name)) {
            return null;
        }
        $path = self::storageDir() . DIRECTORY_SEPARATOR . $name;
        return is_file($path) ? $path : null;
    }

    public function purgeForm(int $formId): void
    {
        if (Yii::$app->db->schema->getTableSchema(SecureRelease::tableName(), true) === null) {
            return;
        }
        $releases = SecureRelease::find()->where(['form_id' => $formId])->all();
        $ids = [];
        foreach ($releases as $release) {
            $this->deleteStoredFile($release);
            $ids[] = (int)$release->id;
        }
        if ($ids) {
            if (Yii::$app->db->schema->getTableSchema(SecureEvent::tableName(), true)) {
                SecureEvent::deleteAll(['release_id' => $ids]);
            }
            if (Yii::$app->db->schema->getTableSchema(SecureCode::tableName(), true)) {
                SecureCode::deleteAll(['release_id' => $ids]);
            }
            SecureRelease::deleteAll(['id' => $ids]);
        }
    }

    public function rememberLink(int $releaseId, string $link): void
    {
        $pending = Yii::$app->session->get('cfSecureLinks', []);
        if (!is_array($pending)) {
            $pending = [];
        }
        $pending[$releaseId] = $link;
        Yii::$app->session->set('cfSecureLinks', $pending);
    }

    /**
     * @return array<int, string>
     */
    public function pullLinks(): array
    {
        $pending = Yii::$app->session->get('cfSecureLinks', []);
        Yii::$app->session->remove('cfSecureLinks');
        return is_array($pending) ? $pending : [];
    }

    public static function grant(int $releaseId): void
    {
        Yii::$app->session->set('cfSecureGrant', [
            'releaseId' => $releaseId,
            'until' => time() + self::GRANT_SECONDS,
        ]);
    }

    public static function takeGrant(): ?int
    {
        $grant = Yii::$app->session->get('cfSecureGrant');
        Yii::$app->session->remove('cfSecureGrant');
        if (!is_array($grant) || (int)($grant['until'] ?? 0) < time()) {
            return null;
        }
        $id = (int)($grant['releaseId'] ?? 0);
        return $id > 0 ? $id : null;
    }

    /**
     * @return array<string, string>
     */
    public static function exportParams(array $params): array
    {
        $header = (string)($params['header_mode'] ?? ExportService::HEADER_LABEL);
        if (!isset(ExportService::headerModeLabels()[$header])) {
            $header = ExportService::HEADER_LABEL;
        }
        $status = (string)($params['status'] ?? '');
        if (!in_array($status, ['', 'complete', 'progress'], true)) {
            $status = '';
        }
        $out = [
            'header_mode' => $header,
            'include_in_progress' => (string)($params['include_in_progress'] ?? '0') === '1' ? '1' : '0',
            'include_excluded' => (string)($params['include_excluded'] ?? '0') === '1' ? '1' : '0',
        ];
        if ($status !== '') {
            $out['status'] = $status;
        }
        $q = mb_substr(trim((string)($params['q'] ?? '')), 0, 200);
        if ($q !== '') {
            $out['q'] = $q;
        }
        $integrity = mb_substr(trim((string)($params['integrity'] ?? '')), 0, 40);
        if ($integrity !== '') {
            $out['integrity'] = $integrity;
        }
        if (isset($params['min_score']) && $params['min_score'] !== '' && is_numeric($params['min_score'])) {
            $out['min_score'] = (string)(int)$params['min_score'];
        }
        return $out;
    }

    public static function eventLabel(string $event): string
    {
        $labels = [
            'prepared' => Yii::t('ThiscoveryFormsModule.base', 'File prepared'),
            'contact_changed' => Yii::t('ThiscoveryFormsModule.base', 'Contact changed'),
            'link_issued' => Yii::t('ThiscoveryFormsModule.base', 'Link issued'),
            'code_sent' => Yii::t('ThiscoveryFormsModule.base', 'Code sent'),
            'code_revoked' => Yii::t('ThiscoveryFormsModule.base', 'Code revoked'),
            'code_failed' => Yii::t('ThiscoveryFormsModule.base', 'Code rejected'),
            'download' => Yii::t('ThiscoveryFormsModule.base', 'File downloaded'),
            'file_revoked' => Yii::t('ThiscoveryFormsModule.base', 'File revoked'),
        ];
        return $labels[$event] ?? $event;
    }

    /**
     * @param array{label:string,name:string,email:string}|null $contact
     * @param array{path:string,bytes:int} $stored
     * @param array<string, mixed> $requestMeta
     * @param array<string, mixed> $detail
     * @return array{release:SecureRelease,link:string}|null
     */
    private function insertRelease(
        CustomForm $form,
        ?array $contact,
        string $source,
        array $stored,
        string $originalName,
        ?int $rows,
        ?int $actorId,
        array $requestMeta,
        array $detail = []
    ): ?array {
        if ($contact === null) {
            $this->unlinkStored($stored['path']);
            return null;
        }
        $token = $this->newToken();
        $release = new SecureRelease();
        $release->form_id = (int)$form->id;
        $release->label = $contact['label'];
        $release->contact_name = $contact['name'];
        $release->contact_email = $contact['email'];
        $release->source = $source;
        $release->storage_name = basename($stored['path']);
        $release->original_name = $originalName;
        $release->byte_size = $stored['bytes'];
        $release->row_count = $rows;
        $release->link_hash = $this->linkHash($token);
        $release->status = SecureRelease::STATUS_ACTIVE;
        $release->failed_attempts = 0;
        $release->created_by = $actorId;
        $release->created_at = $this->now();
        if (!$release->save(false)) {
            $this->unlinkStored($stored['path']);
            return null;
        }
        $this->event($release, 'prepared', $actorId, $requestMeta, $detail + [
            'bytes' => $stored['bytes'],
            'source' => $source,
            'file' => $originalName,
        ]);
        $this->event($release, 'link_issued', $actorId, $requestMeta, []);
        return ['release' => $release, 'link' => $this->absoluteLink($token)];
    }

    /**
     * @return array{label:string,name:string,email:string}|null
     */
    private function contact(string $label, string $name, string $email): ?array
    {
        $label = $this->cleanText($label, 160);
        $name = $this->cleanText($name, 160);
        $email = strtolower(trim($email));
        if ($label === '' || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return ['label' => $label, 'name' => $name, 'email' => $email];
    }

    private function contactMayRequest(SecureRelease $release): bool
    {
        $hour = date('Y-m-d H:i:s', time() - 3600);
        $cool = date('Y-m-d H:i:s', time() - self::CONTACT_COOLDOWN_SECONDS);
        $base = SecureCode::find()->where([
            'release_id' => (int)$release->id,
            'source' => SecureCode::SOURCE_CONTACT,
        ]);
        $recent = (clone $base)->andWhere(['>=', 'created_at', $cool])->exists();
        if ($recent) {
            return false;
        }
        $count = (int)(clone $base)->andWhere(['>=', 'created_at', $hour])->count();
        return $count < self::CONTACT_MAX_PER_HOUR;
    }

    /**
     * @param array<string, mixed> $requestMeta
     */
    private function registerFailure(SecureRelease $release, array $requestMeta): void
    {
        Yii::$app->db->createCommand(
            'UPDATE {{%custom_form_secure_release}} SET failed_attempts = failed_attempts + 1 WHERE id=:id',
            [':id' => (int)$release->id]
        )->execute();
        $release->refresh();
        $this->event($release, 'code_failed', null, $requestMeta, [
            'reason' => (int)$release->failed_attempts >= self::MAX_FAILURES ? 'locked' : 'mismatch',
        ]);
    }

    private function revokeOpenCodes(int $releaseId, string $now): void
    {
        Yii::$app->db->createCommand()->update(SecureCode::tableName(), [
            'revoked_at' => $now,
        ], [
            'and',
            ['release_id' => $releaseId],
            ['used_at' => null],
            ['revoked_at' => null],
        ])->execute();
    }

    private function lockRelease(int $id): void
    {
        Yii::$app->db->createCommand(
            'SELECT id FROM {{%custom_form_secure_release}} WHERE id=:id FOR UPDATE',
            [':id' => $id]
        )->queryScalar();
    }

    private function deliver(string $email, string $code, string $expires): bool
    {
        $shown = implode('-', str_split($code, 4));
        $when = Yii::$app->formatter->asDatetime($expires, 'short');
        $text = Yii::t(
            'ThiscoveryFormsModule.base',
            "Your one-time download code is {code}. It expires at {time} and works once. If you did not ask for this code, ignore this email.",
            ['code' => $shown, 'time' => $when]
        );
        try {
            return (bool)Yii::$app->mailer->compose()
                ->setTo($email)
                ->setSubject(Yii::t('ThiscoveryFormsModule.base', 'Your download code'))
                ->setTextBody($text)
                ->send();
        } catch (\Throwable $e) {
            Yii::error('Secure send email failed: ' . $e->getMessage(), 'thiscovery-forms');
            return false;
        }
    }

    /**
     * @param resource $in
     * @return array{path:string,bytes:int}|null
     */
    private function writeStream($in): ?array
    {
        $dir = self::storageDir();
        $name = bin2hex(random_bytes(16));
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        $out = fopen($path, 'wb');
        if ($out === false) {
            if (is_resource($in)) {
                fclose($in);
            }
            return null;
        }
        $bytes = stream_copy_to_stream($in, $out);
        fclose($out);
        if (is_resource($in)) {
            fclose($in);
        }
        if ($bytes === false || $bytes === 0 || $bytes > self::MAX_BYTES) {
            $this->unlinkStored($path);
            return null;
        }
        @chmod($path, 0600);
        return ['path' => $path, 'bytes' => (int)$bytes];
    }

    private function deleteStoredFile(SecureRelease $release): void
    {
        $path = $this->storedPath($release);
        if ($path) {
            $this->unlinkStored($path);
        }
    }

    private function unlinkStored(string $path): void
    {
        $dir = self::storageDir();
        if (str_starts_with($path, $dir . DIRECTORY_SEPARATOR) && is_file($path)) {
            @unlink($path);
        }
    }

    public static function storageDir(): string
    {
        $dir = Yii::getAlias('@runtime/thiscovery-forms-secure');
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $ht = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($ht)) {
            file_put_contents($ht, "Require all denied\nDeny from all\n");
        }
        return $dir;
    }

    private function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function newCode(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $max = strlen($alphabet) - 1;
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }
        return $code;
    }

    private function normalizeCode(string $code): string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        return preg_match('/^[2-9A-HJ-NP-Z]{8}$/', $code) ? $code : '';
    }

    private function linkHash(string $token): string
    {
        return IntegritySettings::hashValue('secure-link:' . $token);
    }

    private function codeHash(int $releaseId, string $code): string
    {
        return IntegritySettings::hashValue('secure-code:' . $releaseId . ':' . $code);
    }

    private function absoluteLink(string $token): string
    {
        return Yii::$app->urlManager->createAbsoluteUrl(['/thiscovery-forms/secure/open', 't' => $token]);
    }

    private function safeFileName(string $name): string
    {
        $name = basename(str_replace(["\0", '\\'], ['', '/'], $name));
        $name = str_replace(["\r", "\n", '"'], '', $name);
        $name = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $name) ?? '';
        $name = trim($name, '. ');
        if ($name === '' || $name === '.' || $name === '..') {
            return 'download.csv';
        }
        return mb_substr($name, 0, 200);
    }

    private function cleanText(string $value, int $max): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        return mb_substr($value, 0, $max);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function ipLimited(string $ip): bool
    {
        $ip = trim($ip);
        if ($ip === '' || !Yii::$app->has('cache')) {
            return false;
        }
        $key = 'cfsec:' . hash('sha256', $ip);
        $count = (int)Yii::$app->cache->get($key);
        if ($count >= 30) {
            return true;
        }
        Yii::$app->cache->set($key, $count + 1, 3600);
        return false;
    }

    /**
     * @param array<string, mixed> $requestMeta
     * @param array<string, mixed> $detail
     */
    private function event(SecureRelease $release, string $name, ?int $actorId, array $requestMeta, array $detail): void
    {
        $row = new SecureEvent();
        $row->release_id = (int)$release->id;
        $row->form_id = (int)$release->form_id;
        $row->event = $name;
        $row->actor_id = $actorId;
        $ip = trim((string)($requestMeta['ip'] ?? ''));
        $row->ip = $ip !== '' ? mb_substr($ip, 0, 45) : null;
        $ua = trim((string)($requestMeta['user_agent'] ?? ''));
        $row->user_agent = $ua !== '' ? mb_substr($ua, 0, 500) : null;
        $lang = trim((string)($requestMeta['accept_language'] ?? ''));
        $row->accept_language = $lang !== '' ? mb_substr($lang, 0, 255) : null;
        $row->detail = $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null;
        $row->created_at = $this->now();
        $row->save(false);
    }
}
