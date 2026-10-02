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
}
