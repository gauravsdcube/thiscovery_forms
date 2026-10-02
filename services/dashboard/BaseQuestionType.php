<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services\dashboard;

use humhub\modules\thiscoveryDashboard\interfaces\QuestionTypeInterface;

abstract class BaseQuestionType implements QuestionTypeInterface
{
    public function aggregate(array $cells, array $filters = []): array
    {
        $grouped = [];
        foreach ($cells as $cell) {
            $key = (string)($cell['bucket_key'] ?? '');
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'bucket_key' => $key,
                    'bucket_label' => (string)($cell['bucket_label'] ?? $key),
                    'value' => 0,
                    'n' => 0,
                ];
            }
            // A cell may carry its response's weight (packed by FormsDataProvider): weighted
            // totals use it, and the unweighted count stays in n (SCO-14).
            $weight = isset($cell['weight']) && is_numeric($cell['weight']) ? (float)$cell['weight'] : 1.0;
            $grouped[$key]['value'] += (float)($cell['value'] ?? 0) * $weight;
            $grouped[$key]['n'] += (int)($cell['n'] ?? 0);
        }
        return [
            'shape' => $this->getDataShape(),
            'cells' => array_values($grouped),
        ];
    }

    /**
     * @return list<array{bucket_key:string,bucket_label:string,metric:string,value:float,n:int}>
     */
    protected function cell(string $key, string $label, float $value = 1, int $n = 1, string $metric = 'count'): array
    {
        if ($key === '') {
            return [];
        }
        return [[
            'bucket_key' => mb_substr($key, 0, 190),
            'bucket_label' => mb_substr($label !== '' ? $label : $key, 0, 255),
            'metric' => $metric,
            'value' => $value,
            'n' => $n,
        ]];
    }

    protected function decode($value)
    {
        if (is_array($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return $value;
        }
        // Only stored lists and objects are JSON. A plain code stays exactly as stored: "1.0"
        // and "1e2" are not numbers to re-format (SCO-21).
        $first = ltrim($value)[0] ?? '';
        if ($first !== '[' && $first !== '{') {
            return $value;
        }
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    protected function optionLabel(array $meta, string $code): string
    {
        foreach ((array)($meta['options'] ?? []) as $opt) {
            if (is_array($opt) && (string)($opt['code'] ?? '') === $code) {
                return (string)($opt['label'] ?? $code);
            }
            if (is_string($opt) && $opt === $code) {
                return $code;
            }
        }
        return $code;
    }
}
