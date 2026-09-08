<?php

namespace humhub\modules\thiscoveryForms\services;

/**
 * Choice lists may store an internal code plus a participant-facing label.
 * Lines use "code | Label". A line without | keeps the same string for both.
 */
class ChoiceOptions
{
    /**
     * @return array{code:string,label:string}
     */
    public static function parseLine(string $line): array
    {
        $line = trim($line);
        if ($line === '') {
            return ['code' => '', 'label' => ''];
        }
        if (preg_match('/^(.+?)\s+\|\s+(.+)$/u', $line, $m)) {
            $code = trim($m[1]);
            $label = trim($m[2]);
            if ($code !== '' && $label !== '') {
                return ['code' => $code, 'label' => $label];
            }
        }
        return ['code' => $line, 'label' => $line];
    }

    /**
     * @return array<int, array{code:string,label:string}>
     */
    public static function parseText(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $out = [];
        $seen = [];
        foreach ($lines as $line) {
            $pair = self::parseLine((string)$line);
            if ($pair['code'] === '') {
                continue;
            }
            $code = $pair['code'];
            $n = 2;
            while (isset($seen[$code])) {
                $code = $pair['code'] . '-' . $n;
                $n++;
            }
            $seen[$code] = true;
            $out[] = ['code' => $code, 'label' => $pair['label']];
        }
        return $out;
    }

    /**
     * @return array<int, array{code:string,label:string}>
     */
    public static function itemsFromDecoded($decoded): array
    {
        $raw = [];
        if (!is_array($decoded)) {
            return [];
        }
        if (isset($decoded['__type'])) {
            if (isset($decoded['options']) && is_array($decoded['options'])) {
                $raw = $decoded['options'];
            } elseif (isset($decoded['items']) && is_array($decoded['items'])) {
                $raw = $decoded['items'];
            }
        } elseif (isset($decoded['options']) && is_array($decoded['options'])) {
            $raw = $decoded['options'];
        } elseif (array_is_list($decoded)) {
            $raw = $decoded;
        }
        $out = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                $code = trim((string)($item['code'] ?? $item['value'] ?? ''));
                $label = trim((string)($item['label'] ?? $code));
                if ($code === '' && $label !== '') {
                    $code = $label;
                }
                if ($code === '') {
                    continue;
                }
                $out[] = ['code' => $code, 'label' => $label !== '' ? $label : $code];
                continue;
            }
            $pair = self::parseLine(trim((string)$item));
            if ($pair['code'] === '') {
                continue;
            }
            $out[] = $pair;
        }
        return $out;
    }

    /**
     * @param array<int, array{code:string,label:string}> $items
     * @return string[]
     */
    public static function codes(array $items): array
    {
        $codes = [];
        foreach ($items as $item) {
            $code = trim((string)($item['code'] ?? ''));
            if ($code !== '') {
                $codes[] = $code;
            }
        }
        return array_values($codes);
    }

    /**
     * If any option has a code, every option must have a non-empty code.
     *
     * @param array<int, array{code:string,label:string}> $items
     * @throws \yii\base\InvalidArgumentException
     */
    public static function assertCodeConsistency(array $items): void
    {
        if (!$items) {
            return;
        }
        $withCode = 0;
        $without = 0;
        foreach ($items as $item) {
            $code = trim((string)($item['code'] ?? ''));
            $label = trim((string)($item['label'] ?? ''));
            if ($label === '' && $code === '') {
                continue;
            }
            // Distinct code, or code present while allowing code===label
            if ($code !== '') {
                $withCode++;
            } else {
                $without++;
            }
        }
        if ($withCode > 0 && $without > 0) {
            throw new \InvalidArgumentException(\Yii::t(
                'ThiscoveryFormsModule.base',
                'If you set an internal code on one choice, every choice in this question needs an internal code.'
            ));
        }
    }

    /**
     * @param array<int, array{code:string,label:string}> $items
     */
    public static function toStorage(array $items): array
    {
        $hasCode = false;
        foreach ($items as $item) {
            if (trim((string)($item['code'] ?? '')) !== '') {
                $hasCode = true;
                break;
            }
        }
        if (!$hasCode) {
            return array_values(array_map(static function ($item) {
                $label = (string)($item['label'] ?? '');
                return $label !== '' ? $label : (string)($item['code'] ?? '');
            }, $items));
        }
        return array_values(array_map(static fn($item) => [
            'code' => (string)$item['code'],
            'label' => (string)($item['label'] !== '' ? $item['label'] : $item['code']),
        ], $items));
    }

    /**
     * @param array<int, array{code:string,label:string}> $items
     */
    public static function toText(array $items): string
    {
        $lines = [];
        foreach ($items as $item) {
            $code = (string)($item['code'] ?? '');
            $label = (string)($item['label'] ?? $code);
            if ($label === '' && $code === '') {
                continue;
            }
            if ($code === '' || $code === $label) {
                $lines[] = $label !== '' ? $label : $code;
            } else {
                $lines[] = $code . ' | ' . $label;
            }
        }
        return implode("\n", $lines);
    }

    /**
     * @param array<int, array{code:string,label:string}> $items
     */
    public static function labelFor(array $items, string $code): string
    {
        foreach ($items as $item) {
            if ((string)$item['code'] === $code) {
                return (string)$item['label'];
            }
        }
        return $code;
    }
}
