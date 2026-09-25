<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services\dashboard;

use humhub\modules\thiscoveryDashboard\interfaces\DataShape;

/**
 * Fallback when a form question type has no dedicated shape mapping.
 * Dashboard still renders a table rather than failing.
 */
class UnknownQuestionType extends BaseQuestionType
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
        return DataShape::UNKNOWN;
    }

    public function getDefaultVisualisations(): array
    {
        return ['table'];
    }

    public function extractCells($value, array $questionMeta): array
    {
        $decoded = $this->decode($value);
        if (is_array($decoded)) {
            $decoded = json_encode($decoded);
        }
        $text = trim((string)$decoded);
        if ($text === '') {
            return [];
        }
        return $this->cell(mb_substr($text, 0, 80), mb_substr($text, 0, 80));
    }
}
