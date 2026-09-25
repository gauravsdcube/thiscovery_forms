<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services\dashboard;

use humhub\modules\thiscoveryDashboard\interfaces\DataShape;

class NumericQuestionType extends BaseQuestionType
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
        return DataShape::NUMERIC;
    }

    public function getDefaultVisualisations(): array
    {
        return ['kpi', 'gauge', 'bar', 'column', 'table'];
    }

    public function extractCells($value, array $questionMeta): array
    {
        $decoded = $this->decode($value);
        if (is_array($decoded)) {
            $decoded = $decoded['value'] ?? $decoded['score'] ?? reset($decoded);
        }
        if (!is_numeric($decoded)) {
            return [];
        }
        $num = (float)$decoded;
        $key = (string)$num;
        $cells = $this->cell($key, $key, 1, 1, 'count');
        $cells[] = [
            'bucket_key' => '__sum',
            'bucket_label' => 'Sum',
            'metric' => 'sum',
            'value' => $num,
            'n' => 1,
        ];
        return $cells;
    }
}
