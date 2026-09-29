<?php

namespace humhub\modules\thiscoveryForms\helpers;

/**
 * Stops a spreadsheet from treating an exported cell as a formula.
 */
class CsvCell
{
    public static function neutralise($value): string
    {
        $value = (string)$value;
        if ($value !== '' && self::isFormula($value[0])) {
            return "'" . $value;
        }
        return $value;
    }

    /**
     * @param array<int|string, mixed> $cells
     * @return string[]
     */
    public static function row(array $cells): array
    {
        $out = [];
        foreach ($cells as $cell) {
            $out[] = self::neutralise($cell);
        }
        return $out;
    }

    public static function restore(string $value): string
    {
        if (strlen($value) > 1 && $value[0] === "'" && self::isFormula($value[1])) {
            return substr($value, 1);
        }
        return $value;
    }

    private static function isFormula(string $char): bool
    {
        return $char === '=' || $char === '+' || $char === '-' || $char === '@'
            || $char === "\t" || $char === "\r";
    }
}
