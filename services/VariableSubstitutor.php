<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\user\models\User;

class VariableSubstitutor
{
    /**
     * Replace {{user.*}}, {{form.title}}, {{answer:ID|Label}} placeholders.
     *
     * @param array $answers fieldId => value
     * @param FormField[] $fields
     */
    public function substitute(string $html, ?User $user, CustomForm $form, array $answers = [], array $fields = []): string
    {
        $map = [
            'user.displayname' => $user ? (string)$user->displayName : '',
            'user.email' => $user ? (string)$user->email : '',
            'user.guid' => $user ? (string)$user->guid : '',
            'form.title' => (string)$form->title,
        ];

        $html = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', static function ($m) use ($map) {
            $key = strtolower($m[1]);
            return array_key_exists($key, $map) ? $map[$key] : $m[0];
        }, $html) ?? $html;

        $labelMap = [];
        foreach ($fields as $field) {
            $labelMap[mb_strtolower(trim($field->label))] = (int)$field->id;
            $labelMap[(string)$field->id] = (int)$field->id;
        }

        $html = preg_replace_callback('/\{\{\s*answer:([^}]+)\s*\}\}/i', static function ($m) use ($answers, $labelMap) {
            $key = trim($m[1]);
            $fieldId = $labelMap[mb_strtolower($key)] ?? $labelMap[$key] ?? (ctype_digit($key) ? (int)$key : null);
            if (!$fieldId || !array_key_exists($fieldId, $answers)) {
                return '';
            }
            $val = $answers[$fieldId];
            if (is_array($val)) {
                return htmlspecialchars(implode(', ', $val), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }, $html) ?? $html;

        return $html;
    }
}
