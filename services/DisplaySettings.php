<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\Module;
use Yii;

/**
 * Show/hide fill chrome: title, description, progress, page indicator.
 * Global defaults with optional per-form overrides (empty string = inherit).
 */
class DisplaySettings
{
    public const KEYS = [
        'show_title',
        'show_description',
        'show_progress',
        'show_page_indicator',
    ];

    public static function defaults(): array
    {
        return [
            'show_title' => 1,
            'show_description' => 1,
            'show_progress' => 1,
            'show_page_indicator' => 1,
        ];
    }

    public static function labels(): array
    {
        return [
            'show_title' => Yii::t('ThiscoveryFormsModule.base', 'Show form title'),
            'show_description' => Yii::t('ThiscoveryFormsModule.base', 'Show form description'),
            'show_progress' => Yii::t('ThiscoveryFormsModule.base', 'Show progress bar'),
            'show_page_indicator' => Yii::t('ThiscoveryFormsModule.base', 'Show page numbers'),
        ];
    }

    public static function global(): array
    {
        $module = Yii::$app->getModule('thiscovery-forms');
        $raw = $module instanceof Module ? $module->settings->get(Module::SETTING_DISPLAY) : null;
        $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);
        return self::normalizeConcrete(is_array($decoded) ? $decoded : []);
    }

    public static function saveGlobal(array $post): void
    {
        $module = Yii::$app->getModule('thiscovery-forms');
        if (!$module instanceof Module) {
            return;
        }
        $module->settings->set(Module::SETTING_DISPLAY, json_encode(self::normalizeConcrete($post), JSON_UNESCAPED_UNICODE));
    }

    /**
     * Form overlay: '' = inherit, '1'/'0' = force.
     */
    public static function formOverlay(CustomForm $form): array
    {
        $raw = $form->getSetting('display', []);
        if (!is_array($raw)) {
            $raw = [];
        }
        $out = [];
        foreach (self::KEYS as $key) {
            $v = $raw[$key] ?? '';
            if ($v === '' || $v === null) {
                $out[$key] = '';
            } else {
                $out[$key] = !empty($v) ? '1' : '0';
            }
        }
        return $out;
    }

    public static function resolve(CustomForm $form): array
    {
        $global = self::global();
        $overlay = self::formOverlay($form);
        $out = [];
        foreach (self::KEYS as $key) {
            if ($overlay[$key] === '') {
                $out[$key] = !empty($global[$key]);
            } else {
                $out[$key] = $overlay[$key] === '1';
            }
        }
        return $out;
    }

    public static function normalizeConcrete(array $values): array
    {
        $out = self::defaults();
        foreach (self::KEYS as $key) {
            if (array_key_exists($key, $values)) {
                $out[$key] = !empty($values[$key]) ? 1 : 0;
            }
        }
        return $out;
    }

    public static function normalizeOverlay(array $values): array
    {
        $out = [];
        foreach (self::KEYS as $key) {
            if (!array_key_exists($key, $values) || $values[$key] === '' || $values[$key] === null) {
                $out[$key] = '';
            } else {
                $out[$key] = !empty($values[$key]) ? '1' : '0';
            }
        }
        return $out;
    }
}
