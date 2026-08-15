<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\user\models\User;

class VariableSubstitutor
{
    /**
     * Replace {{user.*}}, {{form.title}}, {{answer:ID|Label}}, {{field:ID}} placeholders.
     *
     * @param array $answers fieldId => value
     * @param FormField[] $fields
     */
    public function substitute(string $html, ?User $user, CustomForm $form, array $answers = [], array $fields = [], bool $escape = true): string
    {
        $map = $this->userFormMap($user, $form);

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

        return $html;
    }

    public function substitutePlain(string $text, ?User $user, CustomForm $form, array $answers = [], array $fields = []): string
    {
        $safe = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return $this->substitute($safe, $user, $form, $answers, $fields, true);
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
        if (is_array($val)) {
            if (array_is_list($val)) {
                return implode(', ', array_map('strval', $val));
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
                $parts[] = $k . ': ' . (is_array($v) ? implode(', ', array_map('strval', $v)) : (string)$v);
            }
            return implode('; ', $parts);
        }
        return (string)$val;
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
