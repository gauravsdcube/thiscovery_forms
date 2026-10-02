<?php

namespace humhub\modules\thiscoveryForms\services\formula;

/**
 * Renames question references when a variable is renamed or imported under a new name
 * (V3-52): [old], [old.row], [old[*]], [old["x"]] in formula text, ref nodes in parsed trees,
 * and {{answer:old}} / {{field:old}} pipes, including {{answer:old[repeat]}}.
 */
final class FormulaRefs
{
    /**
     * @param array<string,string> $map old variable => new variable
     */
    public static function renameText(string $text, array $map): string
    {
        foreach ($map as $from => $to) {
            $from = (string)$from;
            $to = (string)$to;
            if ($from === '' || $to === '' || strcasecmp($from, $to) === 0) {
                continue;
            }
            $quoted = preg_quote($from, '/');
            $text = preg_replace('/\[' . $quoted . '(?=[\].\[])/i', '[' . $to, $text) ?? $text;
            $text = preg_replace('/(\{\{\s*(?:answer|field)\s*:\s*)' . $quoted . '(?=\s*[}\[:])/i', '${1}' . $to, $text) ?? $text;
        }
        return $text;
    }

    /**
     * @param array<string,mixed> $tree
     * @param array<string,string> $map
     * @return array<string,mixed>
     */
    public static function renameTree(array $tree, array $map): array
    {
        if (($tree['op'] ?? '') === 'ref' && ($tree['ref'] ?? 'field') === 'field') {
            $name = (string)($tree['name'] ?? '');
            foreach ($map as $from => $to) {
                if ($name !== '' && strcasecmp($name, (string)$from) === 0 && (string)$to !== '') {
                    $tree['name'] = (string)$to;
                    break;
                }
            }
        }
        if (isset($tree['args']) && is_array($tree['args'])) {
            foreach ($tree['args'] as $i => $arg) {
                if (is_array($arg)) {
                    $tree['args'][$i] = self::renameTree($arg, $map);
                }
            }
        }
        return $tree;
    }

    /**
     * A stored rule ({text, when, ...}): both the text and the tree.
     *
     * @param array<string,mixed> $rule
     * @param array<string,string> $map
     * @return array<string,mixed>
     */
    public static function renameRule(array $rule, array $map): array
    {
        if (isset($rule['text']) && is_string($rule['text'])) {
            $rule['text'] = self::renameText($rule['text'], $map);
        }
        if (isset($rule['when']) && is_array($rule['when'])) {
            $rule['when'] = self::renameTree($rule['when'], $map);
        }
        return $rule;
    }
}
