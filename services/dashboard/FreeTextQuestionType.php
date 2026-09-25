<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services\dashboard;

use humhub\modules\thiscoveryDashboard\interfaces\DataShape;

class FreeTextQuestionType extends BaseQuestionType
{
    private const STOP = [
        'the', 'and', 'for', 'that', 'this', 'with', 'from', 'have', 'was', 'were',
        'are', 'but', 'not', 'you', 'your', 'they', 'their', 'has', 'had', 'been',
        'a', 'an', 'to', 'of', 'in', 'on', 'it', 'is', 'be', 'or', 'as', 'at', 'by',
    ];

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
        return DataShape::FREETEXT;
    }

    public function getDefaultVisualisations(): array
    {
        return ['wordcloud', 'table'];
    }

    public function extractCells($value, array $questionMeta): array
    {
        $text = is_array($value) ? implode(' ', $value) : (string)$this->decode($value);
        if (is_array($text)) {
            $text = implode(' ', $text);
        }
        $text = mb_strtolower(strip_tags((string)$text));
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [];
        $cells = [];
        foreach ($tokens as $token) {
            if (mb_strlen($token) < 3 || in_array($token, self::STOP, true)) {
                continue;
            }
            $cells = array_merge($cells, $this->cell($token, $token, 1, 1));
        }
        return $cells;
    }
}
