<?php

namespace humhub\modules\thiscoveryForms\services\formula;

/**
 * Question and variable names a formula reads.
 */
final class FormulaDeps
{
    /**
     * @param array<string,mixed> $tree
     * @return list<string>
     */
    public static function names(array $tree): array
    {
        $names = [];
        self::walk($tree, $names);
        $names = array_values(array_unique($names));
        sort($names);
        return $names;
    }

    /** @param array<string,mixed> $tree
     * @param list<string> $names
     */
    private static function walk(array $tree, array &$names): void
    {
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
                self::walk($arg, $names);
            }
        }
    }
}
