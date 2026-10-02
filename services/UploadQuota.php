<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\services\integrity\IntegritySettings;
use Yii;

/**
 * Stops an open file question from being used to fill disk.
 * Count and size apply to every fill upload. Guests are also rate limited.
 */
class UploadQuota
{
    public const MAX_FILES = 10;
    public const MAX_FILE_BYTES = 10485760;
    public const MAX_QUESTION_BYTES = 52428800;
    public const GUEST_LIMIT = 20;
    public const GUEST_WINDOW = 600;
    /** Bytes one network may upload to one form per window, whatever its sessions (V3-50). */
    public const NETWORK_BYTES = 209715200;

    /**
     * File types a fill upload may be, by extension, with the content types each may really
     * be (SEC-13). No HTML, SVG, scripts or executables: an upload is served back from this site.
     */
    public const TYPES = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'gif' => ['image/gif'],
        'webp' => ['image/webp'], 'heic' => ['image/heic', 'image/heif'],
        'txt' => ['text/plain'], 'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'rtf' => ['text/rtf', 'application/rtf'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument', 'application/zip', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument', 'application/zip', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument', 'application/zip', 'application/octet-stream'],
        'odt' => ['application/vnd.oasis.opendocument', 'application/zip'],
        'ods' => ['application/vnd.oasis.opendocument', 'application/zip'],
        'mp3' => ['audio/mpeg'], 'm4a' => ['audio/mp4', 'audio/x-m4a', 'video/mp4'], 'wav' => ['audio/wav', 'audio/x-wav', 'audio/vnd.wave'],
        'mp4' => ['video/mp4'], 'mov' => ['video/quicktime'],
    ];

    /**
     * Why this file may not be uploaded to this question: a type outside the question's list
     * (or the built-in one), or content that is not what the extension says.
     */
    public static function typeError(string $fileName, string $tempPath, array $allowed = []): ?string
    {
        $ext = strtolower((string)pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = $allowed ? array_values(array_intersect($allowed, array_keys(self::TYPES))) : array_keys(self::TYPES);
        if ($ext === '' || !in_array($ext, $allowed, true)) {
            return Yii::t('ThiscoveryFormsModule.base', 'Files of this type cannot be uploaded here. Allowed: {types}.', [
                'types' => implode(', ', $allowed),
            ]);
        }
        if ($tempPath !== '' && is_file($tempPath) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? (string)finfo_file($finfo, $tempPath) : '';
            if ($finfo) {
                finfo_close($finfo);
            }
            if ($mime !== '') {
                foreach (self::TYPES[$ext] as $prefix) {
                    if (stripos($mime, $prefix) === 0) {
                        return null;
                    }
                }
                return Yii::t('ThiscoveryFormsModule.base', 'This file’s content does not match its .{ext} name.', ['ext' => $ext]);
            }
        }
        return null;
    }

    public static function allows(int $formId, int $fieldId, int $bytes, bool $guest, string $ip, ?int $maxFileBytes = null): ?string
    {
        if ($bytes > min(self::MAX_FILE_BYTES, $maxFileBytes ?? self::MAX_FILE_BYTES)) {
            return Yii::t('ThiscoveryFormsModule.base', 'That file is too large.');
        }
        if ($guest && self::guestLimited($formId, $ip)) {
            return Yii::t('ThiscoveryFormsModule.base', 'Too many uploads. Please wait and try again.');
        }
        // The per-question quota lives in the session, which a script can drop; this cap does not.
        if (self::networkBytesExceeded($formId, $ip, $bytes)) {
            return Yii::t('ThiscoveryFormsModule.base', 'Too many uploads. Please wait and try again.');
        }
        $usage = self::usage($formId, $fieldId);
        if ($usage['count'] >= self::MAX_FILES || ($usage['bytes'] + $bytes) > self::MAX_QUESTION_BYTES) {
            return Yii::t('ThiscoveryFormsModule.base', 'This question has reached its upload limit.');
        }
        return null;
    }

    public static function record(int $formId, int $fieldId, int $bytes): void
    {
        $all = self::all($formId);
        $key = (string)$fieldId;
        $row = $all[$key] ?? ['count' => 0, 'bytes' => 0];
        $row['count'] = (int)$row['count'] + 1;
        $row['bytes'] = (int)$row['bytes'] + max(0, $bytes);
        $all[$key] = $row;
        Yii::$app->session->set(self::sessionKey($formId), $all);
    }

    /**
     * @return array{count:int,bytes:int}
     */
    public static function usage(int $formId, int $fieldId): array
    {
        $row = self::all($formId)[(string)$fieldId] ?? ['count' => 0, 'bytes' => 0];
        return ['count' => (int)$row['count'], 'bytes' => (int)$row['bytes']];
    }

    private static function networkBytesExceeded(int $formId, string $ip, int $bytes): bool
    {
        $key = 'cf-upload-bytes-' . $formId . '-' . hash('sha256', FormActionService::networkOf($ip));
        $used = (int)Yii::$app->cache->get($key);
        if ($used + $bytes > self::NETWORK_BYTES) {
            return true;
        }
        Yii::$app->cache->set($key, $used + $bytes, self::GUEST_WINDOW);
        return false;
    }

    private static function guestLimited(int $formId, string $ip): bool
    {
        $hashes = IntegritySettings::hashIp($ip);
        $identity = $hashes['ip_network_hash'] ?: $hashes['ip_hash'] ?: hash('sha256', 'none');
        $key = 'cf-upload-guest-' . $formId . '-' . $identity;
        $count = (int)Yii::$app->cache->get($key);
        if ($count >= self::GUEST_LIMIT) {
            return true;
        }
        Yii::$app->cache->set($key, $count + 1, self::GUEST_WINDOW);
        return false;
    }

    /**
     * @return array<string, array{count:int,bytes:int}>
     */
    private static function all(int $formId): array
    {
        $all = Yii::$app->session->get(self::sessionKey($formId), []);
        return is_array($all) ? $all : [];
    }

    private static function sessionKey(int $formId): string
    {
        return 'cf-upload-quota-' . $formId;
    }
}
