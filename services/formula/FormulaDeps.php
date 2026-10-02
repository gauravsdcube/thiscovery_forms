<?php

namespace humhub\modules\thiscoveryForms\services\formula;

/**
 * Question and variable names a formula reads.
 */
final class FormulaDeps
{
    /**
     * @param array<string,mixed> $tree
     * @param array<string, array<string,mixed>> $named named formulas (fn:name), followed into
     *        so a calculation that calls fn:bmi depends on what fn:bmi reads (V3-38)
     * @return list<string>
     */
    public static function names(array $tree, array $named = []): array
    {
        $names = [];
        $seen = [];
        self::walk($tree, $names, $named, $seen);
        $names = array_values(array_unique($names));
        sort($names);
        return $names;
    }

    /** @param array<string,mixed> $tree
     * @param list<string> $names
     */
    private static function walk(array $tree, array &$names, array $named = [], array &$seen = []): void
    {
        if (($tree['op'] ?? '') === 'fn') {
            $fn = (string)($tree['name'] ?? '');
            if ($fn !== '' && isset($named[$fn]) && is_array($named[$fn]) && !isset($seen[$fn])) {
                $seen[$fn] = true;
                self::walk($named[$fn], $names, $named, $seen);
            }
            return;
        }
        if (($tree['op'] ?? '') === 'ref') {
            $kind = (string)($tree['ref'] ?? 'field');
            $name = (string)($tree['name'] ?? '');
            if ($kind === 'arm') {
                $names[] = 'arm';
            } elseif ($name !== '') {
                $names[] = ($kind === 'field' ? '' : $kind . ':') . $name;
            }
        }
        foreach ($tree['args'] ?? [] as $arg) {
            if (is_array($arg)) {
                self::walk($arg, $names, $named, $seen);
            }
        }
    }
}
