<?php

namespace humhub\modules\thiscoveryForms\services\formula;

/**
 * Decimal strings at scale 12. Halves round away from zero.
 * The JavaScript copy in thiscoveryForms.formula.js must stay in step.
 */
final class Decimal
{
    public const SCALE = 12;

    public static function canonical(string $text): ?string
    {
        $parsed = self::parse($text);
        if ($parsed === null) {
            return null;
        }
        return self::format($parsed[0], $parsed[1], self::SCALE);
    }

    public static function add(string $left, string $right): ?string
    {
        $a = self::parse($left);
        $b = self::parse($right);
        if ($a === null || $b === null) {
            return null;
        }
        $sum = self::addScaled($a, $b);
        return self::format($sum[0], $sum[1], self::SCALE);
    }

    public static function sub(string $left, string $right): ?string
    {
        $negative = self::negate($right);
        if ($negative === null) {
            return null;
        }
        return self::add($left, $negative);
    }

    public static function mul(string $left, string $right): ?string
    {
        $a = self::parse($left);
        $b = self::parse($right);
        if ($a === null || $b === null) {
            return null;
        }
        $sign = $a[0] * $b[0];
        $product = self::mulInt($a[1], $b[1]);
        $rounded = self::divIntRound($product, self::pow10(self::SCALE));
        return self::format($sign, $rounded, self::SCALE);
    }

    public static function div(string $left, string $right): ?string
    {
        $a = self::parse($left);
        $b = self::parse($right);
        if ($a === null || $b === null || $b[1] === '0') {
            return null;
        }
        $sign = $a[0] * $b[0];
        $numerator = self::mulInt($a[1], self::pow10(self::SCALE));
        $rounded = self::divIntRound($numerator, $b[1]);
        return self::format($sign, $rounded, self::SCALE);
    }

    public static function round(string $text, int $places): ?string
    {
        if ($places < 0 || $places > self::SCALE) {
            return null;
        }
        $parsed = self::parse($text);
        if ($parsed === null) {
            return null;
        }
        $drop = self::SCALE - $places;
        $rounded = $drop === 0 ? $parsed[1] : self::divIntRound($parsed[1], self::pow10($drop));
        $scaled = $drop === 0 ? $rounded : self::mulInt($rounded, self::pow10($drop));
        return self::format($parsed[0], $scaled, self::SCALE);
    }

    public static function cmp(string $left, string $right): ?int
    {
        $a = self::parse($left);
        $b = self::parse($right);
        if ($a === null || $b === null) {
            return null;
        }
        if ($a[1] === '0' && $b[1] === '0') {
            return 0;
        }
        if ($a[0] !== $b[0]) {
            return $a[0] < $b[0] ? -1 : 1;
        }
        $order = self::cmpInt($a[1], $b[1]);
        return $a[0] < 0 ? -$order : $order;
    }

    public static function negate(string $text): ?string
    {
        $parsed = self::parse($text);
        if ($parsed === null) {
            return null;
        }
        if ($parsed[1] === '0') {
            return '0';
        }
        return self::format(-$parsed[0], $parsed[1], self::SCALE);
    }

    /**
     * @return array{0:int,1:string}|null
     */
    private static function parse(string $text): ?array
    {
        $text = trim($text);
        if (!preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/', $text)) {
            return null;
        }
        $sign = 1;
        if ($text[0] === '-') {
            $sign = -1;
            $text = substr($text, 1);
        }
        [$whole, $frac] = array_pad(explode('.', $text, 2), 2, '');
        $roundUp = false;
        if (strlen($frac) > self::SCALE) {
            $roundUp = $frac[self::SCALE] >= '5';
            $frac = substr($frac, 0, self::SCALE);
        }
        $frac = str_pad($frac, self::SCALE, '0');
        $digits = ltrim($whole . $frac, '0');
        if ($digits === '') {
            $digits = '0';
        }
        if ($roundUp) {
            $digits = self::addInt($digits, '1');
        }
        if ($digits === '0') {
            return [1, '0'];
        }
        return [$sign, $digits];
    }

    private static function format(int $sign, string $digits, int $scale): string
    {
        if ($digits === '0') {
            return '0';
        }
        $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -$scale);
        $frac = rtrim(substr($digits, -$scale), '0');
        $body = $frac === '' ? $whole : $whole . '.' . $frac;
        return $sign < 0 ? '-' . $body : $body;
    }

    /**
     * @param array{0:int,1:string} $a
     * @param array{0:int,1:string} $b
     * @return array{0:int,1:string}
     */
    private static function addScaled(array $a, array $b): array
    {
        if ($a[1] === '0') {
            return $b;
        }
        if ($b[1] === '0') {
            return $a;
        }
        if ($a[0] === $b[0]) {
            return [$a[0], self::addInt($a[1], $b[1])];
        }
        $order = self::cmpInt($a[1], $b[1]);
        if ($order === 0) {
            return [1, '0'];
        }
        if ($order > 0) {
            return [$a[0], self::subInt($a[1], $b[1])];
        }
        return [$b[0], self::subInt($b[1], $a[1])];
    }

    private static function addInt(string $a, string $b): string
    {
        $a = ltrim($a, '0');
        $b = ltrim($b, '0');
        $a = $a === '' ? '0' : $a;
        $b = $b === '' ? '0' : $b;
        $carry = 0;
        $out = '';
        $i = strlen($a) - 1;
        $j = strlen($b) - 1;
        while ($i >= 0 || $j >= 0 || $carry) {
            $sum = $carry;
            if ($i >= 0) {
                $sum += ord($a[$i]) - 48;
                $i--;
            }
            if ($j >= 0) {
                $sum += ord($b[$j]) - 48;
                $j--;
            }
            $out = (string)($sum % 10) . $out;
            $carry = intdiv($sum, 10);
        }
        return ltrim($out, '0') ?: '0';
    }

    private static function subInt(string $a, string $b): string
    {
        $borrow = 0;
        $out = '';
        $i = strlen($a) - 1;
        $j = strlen($b) - 1;
        while ($i >= 0) {
            $digit = (ord($a[$i]) - 48) - $borrow;
            if ($j >= 0) {
                $digit -= ord($b[$j]) - 48;
                $j--;
            }
            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $out = (string)$digit . $out;
            $i--;
        }
        return ltrim($out, '0') ?: '0';
    }

    private static function mulInt(string $a, string $b): string
    {
        if ($a === '0' || $b === '0') {
            return '0';
        }
        $result = '0';
        for ($j = strlen($b) - 1, $shift = 0; $j >= 0; $j--, $shift++) {
            $digit = ord($b[$j]) - 48;
            if ($digit === 0) {
                continue;
            }
            $carry = 0;
            $part = str_repeat('0', $shift);
            for ($i = strlen($a) - 1; $i >= 0; $i--) {
                $prod = (ord($a[$i]) - 48) * $digit + $carry;
                $part = (string)($prod % 10) . $part;
                $carry = intdiv($prod, 10);
            }
            if ($carry) {
                $part = (string)$carry . $part;
            }
            $result = self::addInt($result, $part);
        }
        return $result;
    }

    private static function divIntRound(string $numerator, string $denominator): string
    {
        if ($numerator === '0') {
            return '0';
        }
        $quotient = '0';
        $remainder = '0';
        $length = strlen($numerator);
        for ($i = 0; $i < $length; $i++) {
            $remainder = self::addInt(self::mulInt($remainder, '10'), $numerator[$i]);
            $digit = 0;
            while (self::cmpInt($remainder, $denominator) >= 0) {
                $remainder = self::subInt($remainder, $denominator);
                $digit++;
                if ($digit === 10) {
                    break;
                }
            }
            $quotient = self::addInt(self::mulInt($quotient, '10'), (string)$digit);
        }
        $twice = self::mulInt($remainder, '2');
        if (self::cmpInt($twice, $denominator) >= 0) {
            $quotient = self::addInt($quotient, '1');
        }
        return $quotient;
    }

    private static function cmpInt(string $a, string $b): int
    {
        $a = ltrim($a, '0') ?: '0';
        $b = ltrim($b, '0') ?: '0';
        if (strlen($a) !== strlen($b)) {
            return strlen($a) < strlen($b) ? -1 : 1;
        }
        return $a <=> $b;
    }

    private static function pow10(int $places): string
    {
        return '1' . str_repeat('0', $places);
    }
}
