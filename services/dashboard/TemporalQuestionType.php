<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services\dashboard;

use humhub\modules\thiscoveryDashboard\interfaces\DataShape;

class TemporalQuestionType extends BaseQuestionType
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
        return DataShape::TEMPORAL;
    }

    public function getDefaultVisualisations(): array
    {
        return ['line', 'column', 'table'];
    }

    public function extractCells($value, array $questionMeta): array
    {
        $decoded = $this->decode($value);
        $raw = is_array($decoded) ? (string)($decoded['date'] ?? $decoded['value'] ?? '') : (string)$decoded;
        $ts = strtotime($raw);
        if (!$ts) {
            return [];
        }
        $day = date('Y-m-d', $ts);
        return $this->cell($day, $day);
    }
}
