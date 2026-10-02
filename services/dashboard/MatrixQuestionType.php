<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services\dashboard;

use humhub\modules\thiscoveryDashboard\interfaces\DataShape;

class MatrixQuestionType extends BaseQuestionType
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
        return DataShape::MATRIX;
    }

    public function getDefaultVisualisations(): array
    {
        return ['heatmap', 'stacked_bar', 'table'];
    }

    public function extractCells($value, array $questionMeta): array
    {
        $decoded = $this->decode($value);
        if (!is_array($decoded)) {
            return [];
        }
        $cells = [];
        foreach ($decoded as $rowKey => $col) {
            if (is_array($col)) {
                $list = array_is_list($col);
                foreach ($col as $colKey => $on) {
                    if ($on === '' || $on === null || $on === false || $on === 0 || $on === '0') {
                        continue;
                    }
                    $ck = $list ? (string)$on : (string)$colKey;
                    $rk = (string)$rowKey;
                    $key = $rk . '|' . $ck;
                    $cells = array_merge($cells, $this->cell($key, $rk . ' / ' . $ck));
                }
                continue;
            }
            $rk = (string)$rowKey;
            $ck = (string)$col;
            if ($ck === '') {
                continue;
            }
            $cells = array_merge($cells, $this->cell($rk . '|' . $ck, $rk . ' / ' . $ck));
        }
        return $cells;
    }
}
