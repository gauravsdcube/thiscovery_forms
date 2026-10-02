<?php

namespace humhub\modules\thiscoveryForms\services\formula;

/**
 * One place for the formula size limits.
 */
final class Limits
{
    public const TEXT = 4000;
    public const DEPTH = 32;
    public const NODES = 500;
    public const STEPS = 10000;
    public const RESULT_TEXT = 2000;

    public static function assertText(string $text): void
    {
        if (mb_strlen($text) > self::TEXT) {
            throw new FormulaException('A formula can be at most ' . self::TEXT . ' characters.', 1, 1);
        }
    }

    /** @param array<string,mixed> $tree */
    public static function assertTree(array $tree): void
    {
        self::measure($tree, 1);
    }

    /** @param array<string,mixed> $tree */
    private static function measure(array $tree, int $depth): int
    {
        if ($depth > self::DEPTH) {
            throw new FormulaException('A formula can be at most ' . self::DEPTH . ' levels deep.', 1, 1);
        }
        $nodes = 1;
        $op = (string)($tree['op'] ?? '');
        foreach ($tree['args'] ?? [] as $arg) {
            if (is_array($arg)) {
                // A chain of the same operator (a + b + c ..., x and y and z ...) is one level,
                // not one level per term (V3-54); the node limit still bounds its length.
                $same = in_array($op, ['add', 'mul', 'and', 'or', 'sub'], true) && (string)($arg['op'] ?? '') === $op;
                $nodes += self::measure($arg, $same ? $depth : $depth + 1);
            }
        }
        if (($tree['op'] ?? '') === 'fn' && is_array($tree['body'] ?? null)) {
            $nodes += self::measure($tree['body'], $depth + 1);
        }
        if ($nodes > self::NODES) {
            throw new FormulaException('A formula can have at most ' . self::NODES . ' parts.', 1, 1);
        }
        return $nodes;
    }
}
