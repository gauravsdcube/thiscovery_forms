<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use humhub\modules\thiscoveryForms\services\PanelFieldService;
use humhub\modules\user\models\User;
use Yii;

class VariableSubstitutor
{
    /**
     * Replace {{user.*}}, {{form.title}}, {{answer:ID|Label}}, {{field:ID}}, {{var:name}} placeholders.
     *
     * @param array $answers fieldId => value
     * @param FormField[] $fields
     * @param array<string,string> $vars
     * @param FormPanelMember|null $member
     */
    public function substitute(string $html, ?User $user, CustomForm $form, array $answers = [], array $fields = [], bool $escape = true, array $vars = [], ?FormPanelMember $member = null): string
    {
        $map = $this->tokenMap($user, $form, $member, $escape);
        foreach ($vars as $key => $value) {
            $text = (string)$value;
            $map[strtolower((string)$key)] = $escape
                ? htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                : $text;
        }

        $html = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', static function ($m) use ($map) {
            $key = strtolower($m[1]);
            return array_key_exists($key, $map) ? $map[$key] : $m[0];
        }, $html) ?? $html;

        $fieldsById = [];
        $labelMap = [];
        foreach ($fields as $field) {
            $fieldsById[(int)$field->id] = $field;
            $labelMap[mb_strtolower(trim($field->label))] = (int)$field->id;
            $labelMap[(string)$field->id] = (int)$field->id;
            $var = trim((string)$field->variable);
            if ($var !== '') {
                $labelMap[mb_strtolower($var)] = (int)$field->id;
            }
            $internal = trim((string)$field->internal_label);
            if ($internal !== '') {
                $labelMap[mb_strtolower($internal)] = (int)$field->id;
            }
        }

        $format = function ($fieldId, $asLabel = false) use ($answers, $fieldsById, $escape) {
            if (!$fieldId || !array_key_exists($fieldId, $answers) && !array_key_exists((int)$fieldId, $answers)) {
                return '';
            }
            $val = $answers[$fieldId] ?? $answers[(int)$fieldId] ?? null;
            $field = $fieldsById[(int)$fieldId] ?? null;
            $text = $this->formatAnswer($val, $field, $asLabel);
            return $escape
                ? htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                : $text;
        };

        $html = preg_replace_callback('/\{\{\s*answer:([^}]+)\s*\}\}/i', static function ($m) use ($labelMap, $format) {
            $parts = array_map('trim', explode(':', $m[1]));
            $key = $parts[0] ?? '';
            $asLabel = isset($parts[1]) && in_array(strtolower($parts[1]), ['option', 'label'], true);
            $fieldId = $labelMap[mb_strtolower($key)] ?? $labelMap[$key] ?? (ctype_digit($key) ? (int)$key : null);
            return $format($fieldId, $asLabel);
        }, $html) ?? $html;

        $html = preg_replace_callback('/\{\{\s*field:([^}]+)\s*\}\}/i', static function ($m) use ($labelMap, $format) {
            $key = trim($m[1]);
            $fieldId = $labelMap[mb_strtolower($key)] ?? $labelMap[$key] ?? (ctype_digit($key) ? (int)$key : null);
            return $format($fieldId, true);
        }, $html) ?? $html;

        $html = preg_replace_callback('/\{\{\s*var:([^}]+)\s*\}\}/i', static function ($m) use ($vars, $escape) {
            $key = trim($m[1]);
            if ($key === '' || !array_key_exists($key, $vars)) {
                $lower = strtolower($key);
                foreach ($vars as $name => $value) {
                    if (strtolower((string)$name) === $lower) {
                        $text = (string)$value;
                        return $escape
                            ? htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                            : $text;
                    }
                }
                return '';
            }
            $text = (string)$vars[$key];
            return $escape
                ? htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                : $text;
        }, $html) ?? $html;

        return $html;
    }

    public function substitutePlain(string $text, ?User $user, CustomForm $form, array $answers = [], array $fields = [], array $vars = [], ?FormPanelMember $member = null): string
    {
        $safe = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return $this->substitute($safe, $user, $form, $answers, $fields, true, $vars, $member);
    }

    /**
     * Lowercase placeholder keys for fill JS and PHP piping.
     *
     * @return array<string,string>
     */
    public function tokenMap(?User $user, CustomForm $form, ?FormPanelMember $member = null, bool $escape = false): array
    {
        $map = $this->userFormMap($user, $form);
        foreach (PanelFieldService::variableMap($member, $member ? $member->panel : $form->getAttachedPanel()) as $key => $value) {
            $map[strtolower((string)$key)] = (string)$value;
        }
        if ($escape) {
            foreach ($map as $k => $v) {
                $map[$k] = htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }
        return $map;
    }

    /**
     * @param mixed $val
     */
    public function formatAnswer($val, ?FormField $field = null, bool $asLabel = false): string
    {
        if ($val === null || $val === '' || $val === []) {
            return '';
        }
        if ($field && $field->type === FormField::TYPE_IMAGE_AREA && is_array($val)) {
            $ids = $val['regions'] ?? $val;
            if (!is_array($ids)) {
                $ids = [$ids];
            }
            $labels = [];
            $cfg = $field->getImageAreaConfig();
            $byId = [];
            foreach ($cfg['regions'] as $region) {
                $byId[(string)($region['id'] ?? '')] = (string)($region['label'] ?? '');
            }
            foreach ($ids as $id) {
                if (is_array($id)) {
                    continue;
                }
                $id = (string)$id;
                $labels[] = $byId[$id] ?? $id;
            }
            $text = implode(', ', $labels);
            if (isset($val['score'])) {
                $text .= ($text !== '' ? ' · ' : '') . 'score ' . $val['score'];
            }
            return $text;
        }
        if ($field && $field->type === FormField::TYPE_MAP && is_array($val)) {
            $n = count($val['features'] ?? []);
            if ($n < 1) {
                return '';
            }
            return Yii::t('ThiscoveryFormsModule.base', '{n,plural,=1{1 map drawing} other{# map drawings}}', ['n' => $n]);
        }
        if (is_array($val) && ($val['type'] ?? '') === 'FeatureCollection') {
            $n = count($val['features'] ?? []);
            if ($n < 1) {
                return '';
            }
            return Yii::t('ThiscoveryFormsModule.base', '{n,plural,=1{1 map drawing} other{# map drawings}}', ['n' => $n]);
        }
        if (is_array($val)) {
            if (array_is_list($val)) {
                return implode(', ', $this->scalarList($val));
            }
            if (isset($val['best']) || isset($val['worst'])) {
                $parts = [];
                if (!empty($val['best'])) {
                    $parts[] = 'best: ' . $val['best'];
                }
                if (!empty($val['worst'])) {
                    $parts[] = 'worst: ' . $val['worst'];
                }
                return implode(', ', $parts);
            }
            if (isset($val['sets']) && is_array($val['sets'])) {
                $parts = [];
                foreach ($val['sets'] as $i => $set) {
                    if (!is_array($set)) {
                        continue;
                    }
                    $parts[] = '#' . ((int)$i + 1) . ' best ' . ($set['best'] ?? '') . ' / worst ' . ($set['worst'] ?? '');
                }
                return implode('; ', $parts);
            }
            $parts = [];
            foreach ($val as $k => $v) {
                $parts[] = $k . ': ' . (is_array($v) ? implode(', ', $this->scalarList($v)) : (string)$v);
            }
            return implode('; ', $parts);
        }
        return (string)$val;
    }

    /**
     * @param mixed $items
     * @return string[]
     */
    private function scalarList($items): array
    {
        if (!is_array($items)) {
            return [];
        }
        $out = [];
        foreach ($items as $item) {
            if (is_scalar($item) || $item === null) {
                $out[] = (string)$item;
            }
        }
        return $out;
    }

    private function userFormMap(?User $user, CustomForm $form): array
    {
        $first = '';
        $last = '';
        if ($user && !empty($user->profile)) {
            $first = trim((string)($user->profile->firstname ?? ''));
            $last = trim((string)($user->profile->lastname ?? ''));
        }
        return [
            'user.displayname' => $user ? (string)$user->displayName : '',
            'user.email' => $user ? (string)$user->email : '',
            'user.guid' => $user ? (string)$user->guid : '',
            'user.firstname' => $first,
            'user.lastname' => $last,
            'form.title' => (string)$form->title,
        ];
    }
}
