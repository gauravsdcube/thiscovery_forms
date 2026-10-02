<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;

/**
 * Clone, import, and restore must point piping tokens at the new question ids.
 */
class FieldRefRewriter
{
    /**
     * @param array<int,int> $idMap old field id => new field id
     */
    public static function text(string $text, array $idMap): string
    {
        if ($text === '' || $idMap === []) {
            return $text;
        }
        $rewritten = preg_replace_callback(
            '/\{\{\s*(answer|field)\s*:\s*(\d+)/i',
            static function (array $match) use ($idMap): string {
                $id = (int)$match[2];
                if (!isset($idMap[$id])) {
                    return $match[0];
                }
                return '{{' . $match[1] . ':' . $idMap[$id];
            },
            $text
        );
        return $rewritten ?? $text;
    }

    /**
     * @param array<string,int|string> $createdMap
     * @return array<int,int>
     */
    public static function mapFromCreated(array $createdMap): array
    {
        $idMap = [];
        foreach ($createdMap as $key => $newId) {
            $text = (string)$key;
            if (ctype_digit($text) && (int)$text !== (int)$newId) {
                $idMap[(int)$text] = (int)$newId;
            }
        }
        return $idMap;
    }

    /**
     * @param array<int,int> $idMap
     */
    public static function rewriteField(FormField $field, array $idMap): void
    {
        if ($idMap === []) {
            return;
        }
        $help = (string)$field->help_text;
        $nextHelp = self::text($help, $idMap);
        if ($nextHelp !== $help) {
            $field->help_text = $nextHelp;
        }
        // Piped labels ({{answer:12}}) point at the copy too (V3-52).
        $label = (string)$field->label;
        $nextLabel = self::text($label, $idMap);
        if ($nextLabel !== $label) {
            $field->label = $nextLabel;
        }
        foreach (['options_json', 'logic_json', 'actions_json', 'validation_json'] as $column) {
            if (!$field->hasAttribute($column)) {
                continue;
            }
            $raw = (string)$field->$column;
            $next = self::text($raw, $idMap);
            if ($next !== $raw) {
                $field->$column = $next;
            }
        }
        // A loop's source or name question given by id must point at the copy (V3-46).
        $options = json_decode((string)$field->options_json, true);
        if (is_array($options) && is_array($options['loop'] ?? null)) {
            $changed = false;
            foreach (['field_key', 'label_field'] as $key) {
                $value = trim((string)($options['loop'][$key] ?? ''));
                $id = ctype_digit($value) ? (int)$value : (preg_match('/^id(\d+)$/', $value, $m) ? (int)$m[1] : 0);
                if ($id > 0 && isset($idMap[$id])) {
                    $options['loop'][$key] = (string)$idMap[$id];
                    $changed = true;
                }
            }
            if ($changed) {
                $field->options_json = json_encode($options, JSON_UNESCAPED_UNICODE);
            }
        }
    }

    /**
     * @param array<int,int> $idMap
     */
    public static function rewriteForm(CustomForm $form, array $idMap): void
    {
        if ($idMap === []) {
            return;
        }
        $changed = [];
        foreach (['description', 'thank_you_content', 'already_submitted_message'] as $column) {
            $raw = (string)$form->$column;
            $next = self::text($raw, $idMap);
            if ($next !== $raw) {
                $form->$column = $next;
                $changed[] = $column;
            }
        }
        if ($changed !== [] && !$form->isNewRecord) {
            $form->save(false, $changed);
        }
    }
}
