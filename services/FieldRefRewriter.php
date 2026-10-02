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
        foreach (['options_json', 'logic_json'] as $column) {
            $raw = (string)$field->$column;
            $next = self::text($raw, $idMap);
            if ($next !== $raw) {
                $field->$column = $next;
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
