<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;
use yii\db\Query;

/**
 * Best/Worst and MaxDiff translations must be the label only.
 * Older overlays stored "source | translation", which was then posted as the answer.
 */
class m260928_181000_item_translation_labels extends Migration
{
    public function safeUp()
    {
        $rows = (new Query())
            ->select(['i.id', 'i.options_json'])
            ->from(['i' => 'custom_form_field_i18n'])
            ->innerJoin(['f' => 'custom_form_field'], 'f.id = i.field_id')
            ->where(['f.type' => ['best_worst', 'maxdiff']])
            ->andWhere(['like', 'i.options_json', ' | '])
            ->all();
        foreach ($rows as $row) {
            $decoded = json_decode((string)$row['options_json'], true);
            if (!is_array($decoded) || !isset($decoded['items']) || !is_array($decoded['items'])) {
                continue;
            }
            $changed = false;
            foreach ($decoded['items'] as $index => $item) {
                if (!is_string($item) || !preg_match('/^(.+?)\s+\|\s+(.+)$/u', trim($item), $match)) {
                    continue;
                }
                $decoded['items'][$index] = trim($match[2]);
                $changed = true;
            }
            if ($changed) {
                $this->update('custom_form_field_i18n', [
                    'options_json' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
                ], ['id' => (int)$row['id']]);
            }
        }
        return true;
    }

    public function safeDown()
    {
        echo "m260928_181000_item_translation_labels does not restore the old item strings.\n";
        return true;
    }
}
