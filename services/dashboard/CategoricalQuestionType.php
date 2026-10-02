<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services\dashboard;

use humhub\modules\thiscoveryDashboard\interfaces\DataShape;

class CategoricalQuestionType extends BaseQuestionType
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
        return DataShape::CATEGORICAL;
    }

    public function getDefaultVisualisations(): array
    {
        return ['bar', 'pie', 'donut', 'column', 'table'];
    }

    public function extractCells($value, array $questionMeta): array
    {
        $decoded = $this->decode($value);
        if (is_array($decoded)) {
            // Code "0" is an answer: never let ?: turn it into nothing (SCO-21).
            $first = reset($decoded);
            $decoded = (string)($decoded['code'] ?? $decoded['value'] ?? ($first === false ? '' : $first));
        }
        $code = trim((string)$decoded);
        if ($code === '') {
            return [];
        }
        return $this->cell($code, $this->optionLabel($questionMeta, $code));
    }
}
