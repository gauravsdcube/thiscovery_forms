<?php

namespace humhub\modules\thiscoveryForms\services;

use Yii;
use yii\web\Request;

/**
 * Captures one respondent attribute per metadata field (IP, browser, and so on).
 */
class RespondentMetaService
{
    public const KEY_IP = 'ip';
    public const KEY_BROWSER = 'browser';
    public const KEY_OS = 'os';
    public const KEY_DEVICE = 'device';
    public const KEY_SCREEN = 'screen';
    public const KEY_LANGUAGE = 'language';
    public const KEY_TIMEZONE = 'timezone';
    public const KEY_USER_AGENT = 'userAgent';

    /**
     * @return array<string,string> key => studio/fill label
     */
    public static function keyLabels(): array
    {
        return [
            self::KEY_IP => Yii::t('ThiscoveryFormsModule.base', 'IP address'),
            self::KEY_BROWSER => Yii::t('ThiscoveryFormsModule.base', 'Browser'),
            self::KEY_OS => Yii::t('ThiscoveryFormsModule.base', 'Operating system'),
            self::KEY_DEVICE => Yii::t('ThiscoveryFormsModule.base', 'Device'),
            self::KEY_SCREEN => Yii::t('ThiscoveryFormsModule.base', 'Screen size'),
            self::KEY_LANGUAGE => Yii::t('ThiscoveryFormsModule.base', 'Browser language'),
            self::KEY_TIMEZONE => Yii::t('ThiscoveryFormsModule.base', 'Time zone'),
            self::KEY_USER_AGENT => Yii::t('ThiscoveryFormsModule.base', 'User agent'),
        ];
    }

    public static function normalizeKey(string $key): string
    {
        $key = trim($key);
        return isset(self::keyLabels()[$key]) ? $key : '';
    }

    public static function defaultLabel(string $key): string
    {
        $labels = self::keyLabels();
        return $labels[$key] ?? Yii::t('ThiscoveryFormsModule.base', 'Respondent metadata');
    }

    /**
     * One stored value for a metadata field. Combined JSON is only for older fields with no key.
     */
    public function valueFor(string $key, $posted, $request = null): string
    {
        $key = self::normalizeKey($key);
        $overlay = $this->postedOverlay($posted, $key);
        $bundle = $this->capture($overlay, $request);
        if ($key === '') {
            return json_encode($bundle, JSON_UNESCAPED_UNICODE);
        }
        return trim((string)($bundle[$key] ?? ''));
    }

    public function capture($posted, $request = null): array
    {
        $posted = $this->postedOverlay($posted, '');
        $request = $request instanceof Request ? $request : Yii::$app->request;
        $ua = trim((string)($posted['userAgent'] ?? $request->userAgent ?? ''));

        $browser = trim((string)($posted['browser'] ?? ''));
        $os = trim((string)($posted['os'] ?? ''));
        $device = trim((string)($posted['device'] ?? ''));
        if ($ua !== '') {
            if ($browser === '') {
                $browser = $this->guessBrowser($ua);
            }
            if ($os === '') {
                $os = $this->guessOs($ua);
            }
            if ($device === '') {
                $device = $this->guessDevice($ua);
            }
        }

        $language = trim((string)($posted['language'] ?? ''));
        if ($language === '') {
            $language = (string)($request->getPreferredLanguage() ?? '');
        }

        return [
            'browser' => $browser,
            'os' => $os,
            'device' => $device,
            'language' => $language,
            'screen' => trim((string)($posted['screen'] ?? '')),
            'timezone' => trim((string)($posted['timezone'] ?? '')),
            'ip' => $this->clientIp($request),
            'userAgent' => $ua,
        ];
    }

    public function formatDisplay($value): string
    {
        if (is_array($value)) {
            $meta = $value;
        } elseif (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded) && $decoded && !array_is_list($decoded)) {
                $meta = $decoded;
            } else {
                return $value;
            }
        } else {
            return '';
        }
        $parts = [];
        foreach (['ip', 'device', 'os', 'browser', 'screen', 'language', 'timezone'] as $key) {
            $v = trim((string)($meta[$key] ?? ''));
            if ($v !== '') {
                $parts[] = $v;
            }
        }
        return implode(' · ', $parts);
    }

    /**
     * @return array<string,string>
     */
    private function postedOverlay($posted, string $key): array
    {
        if (is_array($posted)) {
            return $posted;
        }
        if (!is_string($posted) || $posted === '') {
            return [];
        }
        $decoded = json_decode($posted, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        $key = self::normalizeKey($key);
        if ($key !== '' && $key !== self::KEY_IP) {
            return [$key => $posted];
        }
        return [];
    }

    /**
     * Client IP from the request only. Never take an address posted by the browser.
     */
    private function clientIp(Request $request): string
    {
        $ip = trim((string)($request->userIP ?? $request->remoteIP ?? ''));
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
        return '';
    }

    private function guessBrowser(string $ua): string
    {
        if (preg_match('/Edg\/[\d.]+/i', $ua)) {
            return 'Edge';
        }
        if (preg_match('/(OPR|Opera)\/[\d.]+/i', $ua)) {
            return 'Opera';
        }
        if (preg_match('/Firefox\/[\d.]+/i', $ua)) {
            return 'Firefox';
        }
        if (preg_match('/Chrome\/[\d.]+/i', $ua) && !preg_match('/Edg\//i', $ua)) {
            return 'Chrome';
        }
        if (preg_match('/Safari\/[\d.]+/i', $ua) && !preg_match('/Chrome\//i', $ua)) {
            return 'Safari';
        }
        return 'Other';
    }

    private function guessOs(string $ua): string
    {
        if (preg_match('/Android/i', $ua)) {
            return 'Android';
        }
        if (preg_match('/iPhone|iPad|iPod/i', $ua)) {
            return 'iOS';
        }
        if (preg_match('/Windows NT/i', $ua)) {
            return 'Windows';
        }
        if (preg_match('/Mac OS X/i', $ua)) {
            return 'macOS';
        }
        if (preg_match('/Linux/i', $ua)) {
            return 'Linux';
        }
        return 'Other';
    }

    private function guessDevice(string $ua): string
    {
        if (preg_match('/iPad|Tablet|PlayBook/i', $ua) || (preg_match('/Android/i', $ua) && !preg_match('/Mobile/i', $ua))) {
            return 'tablet';
        }
        if (preg_match('/Mobi|iPhone|Android.+Mobile/i', $ua)) {
            return 'mobile';
        }
        return 'desktop';
    }
}
