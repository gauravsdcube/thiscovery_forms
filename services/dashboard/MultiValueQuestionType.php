<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services\dashboard;

use humhub\modules\thiscoveryDashboard\interfaces\DataShape;

class MultiValueQuestionType extends BaseQuestionType
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
        return DataShape::MULTIVALUE;
    }

    public function getDefaultVisualisations(): array
    {
        return ['bar', 'pie', 'stacked_bar', 'table'];
    }

    public function extractCells($value, array $questionMeta): array
    {
        $decoded = $this->decode($value);
        $items = is_array($decoded) ? $decoded : (trim((string)$decoded) !== '' ? [trim((string)$decoded)] : []);
        $cells = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $item = (string)($item['code'] ?? $item['value'] ?? '');
            }
            $code = trim((string)$item);
            if ($code === '') {
                continue;
            }
            $cells = array_merge($cells, $this->cell($code, $this->optionLabel($questionMeta, $code)));
        }
        return $cells;
    }
}
