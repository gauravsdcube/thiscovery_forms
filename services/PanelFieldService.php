<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\FormPanel;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use Yii;

/**
 * Extra attributes on a panel (schema) and values stored on each member.
 */
class PanelFieldService
{
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DATE = 'date';
    public const TYPE_EMAIL = 'email';
    public const TYPE_DROPDOWN = 'dropdown';

    public const KEY_FIRST = 'first_name';
    public const KEY_LAST = 'last_name';
    public const KEY_EMAIL = 'email';
    public const KEY_DISPLAY = 'display_name';

    /**
     * Identity fields every panel already has. Not stored in fields_json.
     *
     * @return array<string, array{label:string,type:string,readonly?:bool}>
     */
    public static function identityFields(): array
    {
        return [
            self::KEY_FIRST => [
                'label' => Yii::t('ThiscoveryFormsModule.base', 'First name'),
                'type' => self::TYPE_TEXT,
            ],
            self::KEY_LAST => [
                'label' => Yii::t('ThiscoveryFormsModule.base', 'Last name'),
                'type' => self::TYPE_TEXT,
            ],
            self::KEY_EMAIL => [
                'label' => Yii::t('ThiscoveryFormsModule.base', 'Email'),
                'type' => self::TYPE_EMAIL,
            ],
            self::KEY_DISPLAY => [
                'label' => Yii::t('ThiscoveryFormsModule.base', 'Display name'),
                'type' => self::TYPE_TEXT,
                'readonly' => true,
            ],
        ];
    }

    /**
     * Read-only facts recorded on every member.
     *
     * @return array<string, string> key => label
     */
    public static function recordFieldLabels(): array
    {
        return [
            'source' => Yii::t('ThiscoveryFormsModule.base', 'Source'),
            'linked_user' => Yii::t('ThiscoveryFormsModule.base', 'Linked account'),
            'consent_at' => Yii::t('ThiscoveryFormsModule.base', 'Consent recorded'),
            'created_at' => Yii::t('ThiscoveryFormsModule.base', 'Joined'),
            'weight' => Yii::t('ThiscoveryFormsModule.base', 'Weight'),
        ];
    }

    public static function reservedKeys(): array
    {
        return [
            'id', 'panel_id', 'user_id', 'token', 'status', 'weight',
            'consent_at', 'created_at', 'updated_at', 'demographics_json',
            'source', 'linked_user',
            self::KEY_FIRST, self::KEY_LAST, self::KEY_EMAIL, self::KEY_DISPLAY,
            'firstname', 'lastname', 'first', 'last', 'e_mail',
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_TEXT => Yii::t('ThiscoveryFormsModule.base', 'Text'),
            self::TYPE_TEXTAREA => Yii::t('ThiscoveryFormsModule.base', 'Long text'),
            self::TYPE_NUMBER => Yii::t('ThiscoveryFormsModule.base', 'Number'),
            self::TYPE_DATE => Yii::t('ThiscoveryFormsModule.base', 'Date'),
            self::TYPE_EMAIL => Yii::t('ThiscoveryFormsModule.base', 'Email'),
            self::TYPE_DROPDOWN => Yii::t('ThiscoveryFormsModule.base', 'Dropdown'),
        ];
    }

    public static function slugify(string $label): string
    {
        $key = strtolower(trim($label));
        $key = preg_replace('/[^a-z0-9]+/i', '_', $key) ?? '';
        $key = trim($key, '_');
        if ($key === '') {
            $key = 'field';
        }
        if (preg_match('/^[0-9]/', $key)) {
            $key = 'f_' . $key;
        }
        return substr($key, 0, 40);
    }

    /**
     * @param array $rows posted field rows
     * @return array<int, array{key:string,label:string,type:string,options:string[]}>
     */
    public static function normalizeSchema(array $rows): array
    {
        $reserved = array_fill_keys(self::reservedKeys(), true);
        $used = [];
        $out = [];
        $types = self::typeLabels();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = trim((string)($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $type = (string)($row['type'] ?? self::TYPE_TEXT);
            if (!isset($types[$type])) {
                $type = self::TYPE_TEXT;
            }
            $key = self::slugify((string)($row['key'] ?? $label));
            if (isset($reserved[$key])) {
                $key = 'custom_' . $key;
            }
            $base = $key;
            $n = 2;
            while (isset($used[$key])) {
                $key = $base . '_' . $n;
                $n++;
            }
            $used[$key] = true;
            $options = [];
            if ($type === self::TYPE_DROPDOWN) {
                $raw = $row['options'] ?? [];
                if (is_string($raw)) {
                    $raw = preg_split('/\r\n|\r|\n/', $raw) ?: [];
                }
                if (is_array($raw)) {
                    foreach ($raw as $opt) {
                        $opt = trim((string)$opt);
                        if ($opt !== '') {
                            $options[] = $opt;
                        }
                    }
                }
            }
            $out[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'options' => $options,
            ];
        }
        return $out;
    }

    /**
     * @return array<int, array{key:string,label:string,type:string,options:string[]}>
     */
    public static function schema(FormPanel $panel): array
    {
        return $panel->getMemberFields();
    }

    /**
     * Identity plus custom fields, for survey palette and variables.
     *
     * @return array<string, array{label:string,type:string,options?:string[],custom?:bool,readonly?:bool}>
     */
    public static function surveyFields(?FormPanel $panel): array
    {
        $fields = [];
        foreach (self::identityFields() as $key => $meta) {
            $fields[$key] = $meta;
        }
        if ($panel) {
            foreach ($panel->getMemberFields() as $field) {
                $fields[$field['key']] = [
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'options' => $field['options'],
                    'custom' => true,
                ];
            }
        }
        return $fields;
    }

    /**
     * @return array<string,string> key => label for studio dropdowns
     */
    public static function surveyFieldLabels(?FormPanel $panel): array
    {
        $labels = [];
        foreach (self::surveyFields($panel) as $key => $meta) {
            $labels[$key] = $meta['label'];
        }
        return $labels;
    }

    public static function normalizeKey(string $key, ?FormPanel $panel = null): string
    {
        $key = self::slugify($key);
        if ($key === '') {
            return '';
        }
        if (isset(self::identityFields()[$key])) {
            return $key;
        }
        if ($panel) {
            foreach ($panel->getMemberFields() as $field) {
                if ($field['key'] === $key) {
                    return $key;
                }
            }
        }
        return $key;
    }

    public static function memberValue(FormPanelMember $member, string $key): string
    {
        $key = trim($key);
        if ($key === self::KEY_FIRST) {
            return trim((string)$member->first_name);
        }
        if ($key === self::KEY_LAST) {
            return trim((string)$member->last_name);
        }
        if ($key === self::KEY_EMAIL) {
            return trim((string)($member->email ?: ($member->user->email ?? '')));
        }
        if ($key === self::KEY_DISPLAY) {
            return $member->getDisplayLabel();
        }
        $demo = $member->getDemographics();
        return trim((string)($demo[$key] ?? ''));
    }

    public static function setMemberValue(FormPanelMember $member, string $key, string $value): void
    {
        $key = trim($key);
        $value = trim($value);
        if ($key === '' || $key === self::KEY_DISPLAY) {
            return;
        }
        if ($key === self::KEY_FIRST) {
            $member->first_name = $value !== '' ? $value : $member->first_name;
            return;
        }
        if ($key === self::KEY_LAST) {
            $member->last_name = $value !== '' ? $value : $member->last_name;
            return;
        }
        if ($key === self::KEY_EMAIL) {
            if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $member->email = strtolower($value);
            }
            return;
        }
        $demo = $member->getDemographics();
        if ($value === '') {
            unset($demo[$key]);
        } else {
            $demo[$key] = $value;
        }
        $member->setDemographics($demo);
    }

    /**
     * @param array<string,string> $attrs
     */
    public static function applyAttrs(FormPanelMember $member, array $attrs, FormPanel $panel): void
    {
        $allowed = [];
        foreach ($panel->getMemberFields() as $field) {
            $allowed[$field['key']] = true;
        }
        foreach ($attrs as $key => $value) {
            $key = (string)$key;
            if (!isset($allowed[$key])) {
                continue;
            }
            self::setMemberValue($member, $key, (string)$value);
        }
    }

    /**
     * Tokens for piping and email: member.*, panel.*, plus short aliases.
     *
     * @return array<string,string> lowercase key => value
     */
    public static function variableMap(?FormPanelMember $member, ?FormPanel $panel = null): array
    {
        $panel = $panel ?: ($member ? $member->panel : null);
        $map = [
            'panel.title' => $panel ? (string)$panel->title : '',
            'panel.name' => $panel ? (string)$panel->title : '',
        ];
        if (!$member) {
            return $map;
        }
        foreach (self::surveyFields($panel) as $key => $meta) {
            $value = self::memberValue($member, $key);
            $map['member.' . $key] = $value;
            $map[$key] = $value;
        }
        $map['member.displayname'] = $member->getDisplayLabel();
        $map['displayname'] = $member->getDisplayLabel();
        return $map;
    }

    public static function fieldType(FormPanel $panel, string $key): string
    {
        if (isset(self::identityFields()[$key])) {
            return self::identityFields()[$key]['type'];
        }
        foreach ($panel->getMemberFields() as $field) {
            if ($field['key'] === $key) {
                return $field['type'];
            }
        }
        return self::TYPE_TEXT;
    }

    /**
     * @return string[]
     */
    public static function fieldOptions(FormPanel $panel, string $key): array
    {
        foreach ($panel->getMemberFields() as $field) {
            if ($field['key'] === $key) {
                return $field['options'];
            }
        }
        return [];
    }
}
