<?php

namespace humhub\modules\thiscoveryForms\services;

/**
 * Conservative export-time redaction of emails, phones, and IP addresses.
 * Does not change stored answers. Does not target postcodes or NHS numbers.
 */
class PiiRedactor
{
    public const REPLACEMENT = '[redacted]';

    /**
     * @param mixed $value
     * @return mixed
     */
    public function redact($value)
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        $text = $value;
        $text = preg_replace(self::emailPattern(), self::REPLACEMENT, $text) ?? $text;
        $text = preg_replace(self::e164Pattern(), self::REPLACEMENT, $text) ?? $text;
        $text = preg_replace(self::ukPhonePattern(), self::REPLACEMENT, $text) ?? $text;
        $text = preg_replace_callback(self::ipv4Pattern(), static function (array $m) {
            return filter_var($m[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? self::REPLACEMENT : $m[0];
        }, $text) ?? $text;
        $text = preg_replace_callback(self::ipv6Pattern(), static function (array $m) {
            $candidate = trim($m[0], '[]');
            return filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? self::REPLACEMENT : $m[0];
        }, $text) ?? $text;

        return $text;
    }

    public static function emailPattern(): string
    {
        return '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i';
    }

    public static function e164Pattern(): string
    {
        return '/(?<!\d)\+(?:[1-9]\d{6,14})(?!\d)/';
    }

    public static function ukPhonePattern(): string
    {
        // National numbers starting 0, 10–11 digits, optional separators. Avoid NHS 3-3-4 blocks.
        return '/(?<!\d)0(?:[\s.\-()]*\d){9,10}(?!\d)/';
    }

    public static function ipv4Pattern(): string
    {
        return '/\b(?:(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\.){3}(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\b/';
    }

    public static function ipv6Pattern(): string
    {
        // Full / compressed IPv6, optional [brackets]. Validated with filter_var.
        return '/(?:\[)?(?:[0-9A-F]{0,4}:){2,7}[0-9A-F]{0,4}(?:\])?/i';
    }
}
