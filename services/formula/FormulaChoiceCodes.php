<?php

namespace humhub\modules\thiscoveryForms\services\formula;

use humhub\modules\thiscoveryForms\models\FormField;
use Yii;

/**
 * A choice comparison stores the option code. A typed label is rewritten, and an unknown value is rejected.
 */
final class FormulaChoiceCodes
{
    /**
     * @param FormField[] $fields
     * @return array{text:string,notices:string[]}
     */
    public static function rewrite(string $text, array $fields): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['text' => $text, 'notices' => []];
        }
        try {
            $tree = (new Parser())->parse($text);
        } catch (FormulaException $e) {
            return ['text' => $text, 'notices' => []];
        }
        $changes = [];
        self::walk($tree, $fields, $changes);
        $notices = [];
        $offset = 0;
        foreach ($changes as $change) {
            $replaced = false;
            foreach (['"' . $change['label'] . '"', "'" . $change['label'] . "'"] as $token) {
                $pos = strpos($text, $token, $offset);
                if ($pos === false) {
                    continue;
                }
                $insert = '"' . $change['code'] . '"';
                $text = substr($text, 0, $pos) . $insert . substr($text, $pos + strlen($token));
                $offset = $pos + strlen($insert);
                $replaced = true;
                break;
            }
            if ($replaced) {
                $notices[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” was saved as the option code “{code}”.', [
                    'label' => $change['label'],
                    'code' => $change['code'],
                ]);
            }
        }
        return ['text' => $text, 'notices' => $notices];
    }

    /**
     * @param array<string,mixed> $tree
     * @param FormField[] $fields
     * @param list<array{label:string,code:string}> $changes
     */
    private static function walk(array $tree, array $fields, array &$changes): void
    {
        $op = (string)($tree['op'] ?? '');
        $args = is_array($tree['args'] ?? null) ? $tree['args'] : [];
        if (in_array($op, ['eq', 'ne', 'in', 'not_in', 'any_eq', 'all_eq', 'count_eq', 'selected'], true)) {
            $ref = null;
            $literals = [];
            foreach ($args as $arg) {
                if (!is_array($arg)) {
                    continue;
                }
                if (($arg['op'] ?? '') === 'ref' && ($arg['ref'] ?? 'field') === 'field') {
                    $ref = $arg;
                }
                foreach (self::textLiterals($arg) as $literal) {
                    $literals[] = $literal;
                }
            }
            if ($ref) {
                $field = self::choiceField($fields, (string)($ref['name'] ?? ''));
                if ($field) {
                    foreach ($literals as $literal) {
                        $code = self::codeFor($field, $literal);
                        if ($code !== $literal) {
                            $changes[] = ['label' => $literal, 'code' => $code];
                        }
                    }
                }
            }
        }
        foreach ($args as $arg) {
            if (is_array($arg)) {
                self::walk($arg, $fields, $changes);
            }
        }
    }

    /**
     * @param array<string,mixed> $node
     * @return list<string>
     */
    private static function textLiterals(array $node): array
    {
        if (($node['op'] ?? '') === 'lit' && ($node['lit'] ?? '') === 'text') {
            return [(string)($node['v'] ?? '')];
        }
        if (($node['op'] ?? '') === 'list') {
            $out = [];
            foreach ($node['args'] ?? [] as $arg) {
                if (is_array($arg)) {
                    $out = array_merge($out, self::textLiterals($arg));
                }
            }
            return $out;
        }
        return [];
    }

    /** @param FormField[] $fields */
    private static function choiceField(array $fields, string $name): ?FormField
    {
        if ($name === '') {
            return null;
        }
        foreach ($fields as $field) {
            if (!$field instanceof FormField || !FormField::isChoiceType($field->type)) {
                continue;
            }
            $variable = trim((string)$field->variable);
            if ($name === $variable || $name === (string)$field->id || $name === 'id' . (int)$field->id) {
                return $field;
            }
        }
        return null;
    }

    private static function codeFor(FormField $field, string $literal): string
    {
        $labelMatches = [];
        foreach ($field->getChoicePairs() as $pair) {
            if ((string)$pair['code'] === $literal) {
                return $literal;
            }
            if ((string)$pair['label'] === $literal) {
                $labelMatches[] = (string)$pair['code'];
            }
        }
        $labelMatches = array_values(array_unique($labelMatches));
        if (count($labelMatches) === 1) {
            return $labelMatches[0];
        }
        throw new FormulaException(
            Yii::t('ThiscoveryFormsModule.base', '“{value}” is not an option code on “{question}”.', [
                'value' => $literal,
                'question' => (string)$field->label,
            ]),
            1,
            1
        );
    }
}
