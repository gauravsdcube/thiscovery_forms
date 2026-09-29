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

    public static function allows(int $formId, int $fieldId, int $bytes, bool $guest, string $ip): ?string
    {
        if ($bytes > self::MAX_FILE_BYTES) {
            return Yii::t('ThiscoveryFormsModule.base', 'That file is too large.');
        }
        if ($guest && self::guestLimited($formId, $ip)) {
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
