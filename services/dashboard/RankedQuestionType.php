<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services\dashboard;

use humhub\modules\thiscoveryDashboard\interfaces\DataShape;

class RankedQuestionType extends BaseQuestionType
{
    public function __construct(private string $id, private string $label)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDataShape(): string
    {
        return DataShape::RANKED;
    }

    public function getDefaultVisualisations(): array
    {
        return ['bar', 'stacked_bar', 'table'];
    }

    public function extractCells($value, array $questionMeta): array
    {
        $decoded = $this->decode($value);
        if (!is_array($decoded)) {
            return [];
        }
        if ($this->id === 'maxdiff' || (isset($decoded['sets']) && is_array($decoded['sets']))) {
            return $this->maxDiffCells($decoded, $questionMeta);
        }
        if (array_key_exists('best', $decoded) || array_key_exists('worst', $decoded)) {
            $cells = [];
            $best = $decoded['best'] ?? '';
            $worst = $decoded['worst'] ?? '';
            if (!is_array($best) && (string)$best !== '') {
                $code = (string)$best;
                $cells[] = [
                    'bucket_key' => mb_substr($code, 0, 190),
                    'bucket_label' => mb_substr($this->optionLabel($questionMeta, $code), 0, 255),
                    'metric' => 'rank_score',
                    'value' => 1.0,
                    'n' => 1,
                ];
            }
            if (!is_array($worst) && (string)$worst !== '') {
                $code = (string)$worst;
                $cells[] = [
                    'bucket_key' => mb_substr($code, 0, 190),
                    'bucket_label' => mb_substr($this->optionLabel($questionMeta, $code), 0, 255),
                    'metric' => 'rank_score',
                    'value' => -1.0,
                    'n' => 1,
                ];
            }
            return $cells;
        }
        $n = count($decoded);
        $cells = [];
        $i = 0;
        foreach ($decoded as $item) {
            if (is_array($item)) {
                $code = (string)($item['code'] ?? $item['best'] ?? $item['worst'] ?? $item['value'] ?? '');
            } else {
                $code = (string)$item;
            }
            if ($code === '') {
                $i++;
                continue;
            }
            $weight = max(1, $n - $i);
            $cells[] = [
                'bucket_key' => mb_substr($code, 0, 190),
                'bucket_label' => mb_substr($this->optionLabel($questionMeta, $code), 0, 255),
                'metric' => 'rank_score',
                'value' => (float)$weight,
                'n' => 1,
            ];
            $i++;
        }
        return $cells;
    }

    /**
     * MaxDiff (SCO-3): per set, the best item scores +1 and the worst -1 (rank_score), and every
     * item shown in the set counts once (shown), so a dashboard can report
     * (best - worst) / shown per item, the standard count score.
     *
     * @param array<mixed> $decoded
     */
    private function maxDiffCells(array $decoded, array $questionMeta): array
    {
        $sets = $decoded['sets'] ?? $decoded;
        if (!is_array($sets)) {
            return [];
        }
        $cell = fn (string $code, string $metric, float $value): array => [
            'bucket_key' => mb_substr($code, 0, 190),
            'bucket_label' => mb_substr($this->optionLabel($questionMeta, $code), 0, 255),
            'metric' => $metric,
            'value' => $value,
            'n' => 1,
        ];
        $cells = [];
        foreach ($sets as $pair) {
            if (!is_array($pair)) {
                continue;
            }
            $best = is_scalar($pair['best'] ?? null) ? (string)$pair['best'] : '';
            $worst = is_scalar($pair['worst'] ?? null) ? (string)$pair['worst'] : '';
            if ($best === '' && $worst === '') {
                continue;
            }
            if ($best !== '') {
                $cells[] = $cell($best, 'rank_score', 1.0);
            }
            if ($worst !== '' && $worst !== $best) {
                $cells[] = $cell($worst, 'rank_score', -1.0);
            }
            $shown = is_array($pair['items'] ?? null) ? $pair['items'] : array_filter([$best, $worst], 'strlen');
            foreach (array_unique(array_map('strval', $shown)) as $item) {
                if ($item !== '') {
                    $cells[] = $cell($item, 'shown', 1.0);
                }
            }
        }
        return $cells;
    }
}
