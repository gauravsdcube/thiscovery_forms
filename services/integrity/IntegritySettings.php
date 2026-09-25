<?php

namespace humhub\modules\thiscoveryForms\services\integrity;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\Module;
use Yii;

/**
 * Global defaults with optional per-form overlays.
 * Empty / null overlay values inherit the site default.
 */
class IntegritySettings
{
    public const SETTING_KEY = 'integrity';
    public const PEPPER_KEY = 'integrity_pepper';

    public const ACCESS_PUBLIC = 'public';
    public const ACCESS_UNIQUE = 'unique_invite';
    public const ACCESS_EMAIL = 'email_verified';
    public const ACCESS_LOGGED_IN = 'logged_in';
    public const ACCESS_RESTRICTED = 'restricted';

    public const CAPTCHA_OFF = 'off';
    public const CAPTCHA_SUSPICIOUS = 'suspicious';
    public const CAPTCHA_ALWAYS = 'always';

    public const CAPTCHA_PROVIDER_ALTCHA = 'altcha';
    public const CAPTCHA_PROVIDER_TURNSTILE = 'turnstile';

    public static function defaults(): array
    {
        return [
            'enabled' => 0,
            'bot_protection' => 1,
            'rate_limiting' => 1,
            'captcha' => 1,
            'open_captcha' => 0,
            'duplicate_detection' => 1,
            'speed_detection' => 1,
            'straightline_detection' => 1,
            'attention_checks' => 1,
            'consistency_checks' => 1,
            'freetext_checks' => 1,
            'similarity_detection' => 1,
            'integrity_scoring' => 1,
            'question_timing' => 0,
            'hash_ip' => 1,
            'auto_exclude' => 0,
            'allow_multiple' => null,
            'access_mode' => self::ACCESS_PUBLIC,
            'captcha_mode' => self::CAPTCHA_SUSPICIOUS,
            'captcha_provider' => self::CAPTCHA_PROVIDER_ALTCHA,
            'turnstile_site_key' => '',
            'turnstile_secret' => '',
            'rate_limit_count' => 8,
            'rate_limit_window' => 10,
            'open_rate_count' => 30,
            'open_rate_window' => 10,
            'speed_percent' => 40,
            'speed_min_seconds' => 15,
            'straightline_min_items' => 5,
            'freetext_min_chars' => 8,
            'similarity_threshold' => 90,
            'trust_threshold' => 80,
            'review_threshold' => 55,
            'weight_bot' => 20,
            'weight_duplicate' => 15,
            'weight_speed' => 15,
            'weight_attention' => 15,
            'weight_straightline' => 10,
            'weight_consistency' => 10,
            'weight_freetext' => 10,
            'weight_similarity' => 5,
            'consistency_rules' => [],
        ];
    }

    public static function featureKeys(): array
    {
        return [
            'enabled', 'bot_protection', 'rate_limiting', 'captcha', 'open_captcha', 'duplicate_detection',
            'speed_detection', 'straightline_detection', 'attention_checks', 'consistency_checks',
            'freetext_checks', 'similarity_detection', 'integrity_scoring', 'question_timing',
            'hash_ip', 'auto_exclude',
        ];
    }

    public static function accessModeLabels(): array
    {
        return [
            self::ACCESS_PUBLIC => Yii::t('ThiscoveryFormsModule.base', 'Public / open link'),
            self::ACCESS_UNIQUE => Yii::t('ThiscoveryFormsModule.base', 'Unique invitation link'),
            self::ACCESS_EMAIL => Yii::t('ThiscoveryFormsModule.base', 'Signed-in account with an email address'),
            self::ACCESS_LOGGED_IN => Yii::t('ThiscoveryFormsModule.base', 'Logged-in user only'),
            self::ACCESS_RESTRICTED => Yii::t('ThiscoveryFormsModule.base', 'Restricted to space members'),
        ];
    }

    public static function captchaModeLabels(): array
    {
        return [
            self::CAPTCHA_OFF => Yii::t('ThiscoveryFormsModule.base', 'Off'),
            self::CAPTCHA_SUSPICIOUS => Yii::t('ThiscoveryFormsModule.base', 'Only when behaviour looks suspicious'),
            self::CAPTCHA_ALWAYS => Yii::t('ThiscoveryFormsModule.base', 'Always (when CAPTCHA provider is available)'),
        ];
    }

    public static function captchaProviderLabels(): array
    {
        return [
            self::CAPTCHA_PROVIDER_ALTCHA => Yii::t('ThiscoveryFormsModule.base', 'HumHub Altcha (default)'),
            self::CAPTCHA_PROVIDER_TURNSTILE => Yii::t('ThiscoveryFormsModule.base', 'Cloudflare Turnstile'),
        ];
    }

    /**
     * Whether the configured CAPTCHA provider can run with this settings array.
     */
    public static function captchaAvailable(array $cfg): bool
    {
        $provider = (string)($cfg['captcha_provider'] ?? self::CAPTCHA_PROVIDER_ALTCHA);
        if ($provider === self::CAPTCHA_PROVIDER_TURNSTILE) {
            return trim((string)($cfg['turnstile_site_key'] ?? '')) !== ''
                && trim((string)($cfg['turnstile_secret'] ?? '')) !== '';
        }
        if (!Yii::$app->has('captcha')) {
            return false;
        }
        try {
            $class = Yii::$app->captcha->getValidatorClass();
            return is_string($class) && $class !== '' && class_exists($class);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function module(): ?Module
    {
        $module = Yii::$app->getModule('thiscovery-forms');
        return $module instanceof Module ? $module : null;
    }

    public static function pepper(): string
    {
        $module = self::module();
        if (!$module) {
            return 'thiscovery-forms';
        }
        $pepper = (string)$module->settings->get(self::PEPPER_KEY, '');
        if ($pepper === '') {
            $pepper = bin2hex(random_bytes(16));
            $module->settings->set(self::PEPPER_KEY, $pepper);
        }
        return $pepper;
    }

    public static function global(): array
    {
        $module = self::module();
        $stored = [];
        if ($module) {
            $decoded = json_decode((string)$module->settings->get(self::SETTING_KEY, ''), true);
            $stored = is_array($decoded) ? $decoded : [];
        }
        return self::merge(self::defaults(), $stored, false);
    }

    public static function forForm(?CustomForm $form): array
    {
        $base = self::global();
        if (!$form) {
            return $base;
        }
        $overlay = $form->getSetting(self::SETTING_KEY, []);
        if (!is_array($overlay)) {
            $overlay = [];
        }
        return self::merge($base, $overlay, true);
    }

    /**
     * @param bool $inheritEmpty when true, empty overlay values keep the base
     */
    public static function merge(array $base, array $overlay, bool $inheritEmpty): array
    {
        foreach ($overlay as $key => $value) {
            if ($inheritEmpty && self::isInherit($value)) {
                continue;
            }
            $base[$key] = $value;
        }
        if (!is_array($base['consistency_rules'] ?? null)) {
            $base['consistency_rules'] = [];
        }
        foreach (['weight_bot', 'weight_duplicate', 'weight_speed', 'weight_attention', 'weight_straightline', 'weight_consistency', 'weight_freetext', 'weight_similarity'] as $wk) {
            if (isset($base[$wk]) && is_numeric($base[$wk])) {
                $base[$wk] = max(0, min(40, (float)$base[$wk]));
            }
        }
        foreach (['trust_threshold', 'review_threshold', 'similarity_threshold', 'speed_percent'] as $tk) {
            if (isset($base[$tk]) && is_numeric($base[$tk])) {
                $base[$tk] = max(0, min(100, (float)$base[$tk]));
            }
        }
        return $base;
    }

    public static function isInherit($value): bool
    {
        return $value === null || $value === '' || $value === 'inherit';
    }

    public static function isOn(array $cfg, string $key): bool
    {
        // CAPTCHA gates can run without integrity scoring metadata.
        if (empty($cfg['enabled']) && !in_array($key, ['enabled', 'captcha', 'open_captcha'], true)) {
            return false;
        }
        $v = $cfg[$key] ?? 0;
        return $v === 1 || $v === '1' || $v === true;
    }

    public static function saveGlobal(array $post): bool
    {
        $module = self::module();
        if (!$module) {
            return false;
        }
        $clean = self::sanitize($post, false);
        $module->settings->set(self::SETTING_KEY, json_encode($clean, JSON_UNESCAPED_UNICODE));
        return true;
    }

    public static function saveForm(CustomForm $form, array $post): void
    {
        $clean = self::sanitize($post, true);
        $form->setSetting(self::SETTING_KEY, $clean);
        $mode = (string)($clean['access_mode'] ?? '');
        if ($mode === self::ACCESS_PUBLIC || $mode === self::ACCESS_UNIQUE) {
            $form->allow_anonymous = 1;
        } elseif (in_array($mode, [self::ACCESS_EMAIL, self::ACCESS_LOGGED_IN, self::ACCESS_RESTRICTED], true)) {
            $form->allow_anonymous = 0;
        }
        if (array_key_exists('allow_multiple', $clean) && $clean['allow_multiple'] !== null && $clean['allow_multiple'] !== '') {
            $form->allow_multiple = (int)!empty($clean['allow_multiple']);
        }
        $form->updateAttributes([
            'settings_json' => $form->settings_json,
            'allow_anonymous' => $form->allow_anonymous,
            'allow_multiple' => $form->allow_multiple,
        ]);
    }

    public static function overlayForForm(CustomForm $form): array
    {
        $overlay = $form->getSetting(self::SETTING_KEY, []);
        return is_array($overlay) ? $overlay : [];
    }

    public static function sanitize(array $post, bool $allowInherit): array
    {
        $out = [];
        $defaults = self::defaults();
        if ($allowInherit && !empty($post['enabled_inherit'])) {
            // omit enabled so the form inherits the site default
        } elseif (array_key_exists('enabled', $post) || array_key_exists('enabled_inherit', $post)) {
            $out['enabled'] = !empty($post['enabled']) ? 1 : 0;
        }
        foreach (self::featureKeys() as $key) {
            if ($key === 'enabled') {
                continue;
            }
            if (!array_key_exists($key, $post)) {
                continue;
            }
            if ($allowInherit && self::isInherit($post[$key])) {
                continue;
            }
            $out[$key] = !empty($post[$key]) ? 1 : 0;
        }
        $scalars = [
            'access_mode', 'captcha_mode', 'captcha_provider', 'turnstile_site_key', 'turnstile_secret',
            'rate_limit_count', 'rate_limit_window', 'open_rate_count', 'open_rate_window',
            'speed_percent', 'speed_min_seconds',
            'straightline_min_items', 'freetext_min_chars', 'similarity_threshold',
            'trust_threshold', 'review_threshold',
            'weight_bot', 'weight_duplicate', 'weight_speed', 'weight_attention',
            'weight_straightline', 'weight_consistency', 'weight_freetext', 'weight_similarity',
        ];
        $stringScalars = [
            'turnstile_site_key', 'turnstile_secret', 'access_mode', 'captcha_mode', 'captcha_provider',
        ];
        foreach ($scalars as $key) {
            if (!array_key_exists($key, $post)) {
                continue;
            }
            // Turnstile keys are administration-only (never stored per form).
            if ($allowInherit && in_array($key, ['turnstile_site_key', 'turnstile_secret'], true)) {
                continue;
            }
            if ($allowInherit && self::isInherit($post[$key])) {
                continue;
            }
            $out[$key] = is_numeric($defaults[$key] ?? null) && !in_array($key, $stringScalars, true)
                ? (0 + $post[$key])
                : trim((string)$post[$key]);
        }
        if (array_key_exists('consistency_rules', $post)) {
            $out['consistency_rules'] = self::sanitizeRules($post['consistency_rules']);
        }
        if (array_key_exists('allow_multiple', $post) && (!$allowInherit || !self::isInherit($post['allow_multiple']))) {
            $out['allow_multiple'] = !empty($post['allow_multiple']) ? 1 : 0;
        }
        if (!empty($out['access_mode']) && !isset(self::accessModeLabels()[$out['access_mode']])) {
            unset($out['access_mode']);
        }
        if (!empty($out['captcha_mode']) && !isset(self::captchaModeLabels()[$out['captcha_mode']])) {
            $out['captcha_mode'] = self::CAPTCHA_SUSPICIOUS;
        }
        if (!empty($out['captcha_provider']) && !isset(self::captchaProviderLabels()[$out['captcha_provider']])) {
            $out['captcha_provider'] = self::CAPTCHA_PROVIDER_ALTCHA;
        }
        return $out;
    }

    public static function sanitizeRules($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $rules = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $conditions = [];
            foreach (($row['conditions'] ?? []) as $cond) {
                if (!is_array($cond)) {
                    continue;
                }
                $fieldId = (int)($cond['field_id'] ?? 0);
                if ($fieldId < 1) {
                    continue;
                }
                $conditions[] = [
                    'field_id' => $fieldId,
                    'operator' => in_array(($cond['operator'] ?? ''), ['equals', 'not_equals', 'contains'], true)
                        ? $cond['operator'] : 'equals',
                    'value' => trim((string)($cond['value'] ?? '')),
                ];
            }
            if (count($conditions) < 2) {
                continue;
            }
            $rules[] = [
                'id' => trim((string)($row['id'] ?? '')) ?: ('r' . (count($rules) + 1)),
                'label' => trim((string)($row['label'] ?? '')) ?: Yii::t('ThiscoveryFormsModule.base', 'Consistency rule {n}', ['n' => count($rules) + 1]),
                'conditions' => $conditions,
            ];
        }
        return $rules;
    }

    public static function hashValue(string $value): string
    {
        return hash_hmac('sha256', $value, self::pepper());
    }

    /**
     * Never stores a raw IP. Caller must skip this when hash_ip is Off.
     *
     * @return array{ip_hash:?string,ip_network_hash:?string}
     */
    public static function hashIp(string $ip): array
    {
        $ip = trim($ip);
        if ($ip === '') {
            return ['ip_hash' => null, 'ip_network_hash' => null];
        }
        $network = $ip;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $network = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0';
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $hextets = explode(':', $ip);
            $network = implode(':', array_slice($hextets, 0, 3)) . '::';
        }
        return [
            'ip_hash' => self::hashValue('ip:' . $ip),
            'ip_network_hash' => self::hashValue('net:' . $network),
        ];
    }
}
