<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;
use yii\db\Expression;
use yii\db\Query;

/**
 * Remove "<" from CSS that was stored before the character was rejected.
 * The original text cannot be put back.
 */
class m260928_210000_strip_css_markup extends Migration
{
    public function safeUp()
    {
        $this->strip('custom_form', 'custom_css');
        if ($this->db->getTableSchema('custom_form_theme', true)) {
            $this->strip('custom_form_theme', 'custom_css');
        }
        if ($this->db->getTableSchema('tc_version_revision', true)) {
            $rows = (new Query())
                ->select(['id', 'snapshot_json'])
                ->from('tc_version_revision')
                ->where(['like', 'snapshot_json', '"custom_css"'])
                ->all();
            foreach ($rows as $row) {
                $decoded = json_decode((string)$row['snapshot_json'], true);
                if (!is_array($decoded) || !isset($decoded['meta']['custom_css']) || !is_string($decoded['meta']['custom_css'])) {
                    continue;
                }
                if (!str_contains($decoded['meta']['custom_css'], '<')) {
                    continue;
                }
                $decoded['meta']['custom_css'] = str_replace('<', '', $decoded['meta']['custom_css']);
                $this->update('tc_version_revision', [
                    'snapshot_json' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
                ], ['id' => (int)$row['id']]);
            }
        }
        return true;
    }

    public function safeDown()
    {
        echo "m260928_210000_strip_css_markup cannot restore the removed characters.\n";
        return true;
    }

    private function strip(string $table, string $column): void
    {
        $this->update($table, [
            $column => new Expression("REPLACE(`$column`, '<', '')"),
        ], ['like', $column, '<']);
    }
}
