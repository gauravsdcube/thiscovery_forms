<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;
use yii\db\Query;

/**
 * Guard the answer-field foreign key. The swap already ran on the test
 * database (custom_form_answer_field, 102,859 rows): dropping the key took
 * 0.074s and adding ON DELETE RESTRICT took 1.052s. MySQL copied the table
 * ("copy to tmp table", then "rename result table") and locked writes for
 * that second. On a larger table, run this in a maintenance window, or with
 * pt-online-schema-change or gh-ost, instead of letting migrate lock the table.
 *
 * safeUp checks information_schema and does nothing when the key is already
 * RESTRICT. safeDown does not put CASCADE back.
 */
class m260928_200000_answer_field_fk_guard extends Migration
{
    public function safeUp()
    {
        $rule = $this->deleteRule();
        if ($rule === 'RESTRICT') {
            return true;
        }
        if ($rule !== null) {
            $this->dropForeignKey('fk_cfaf_field', 'custom_form_answer_field');
        }
        $this->addForeignKey(
            'fk_cfaf_field',
            'custom_form_answer_field',
            'field_id',
            'custom_form_field',
            'id',
            'RESTRICT',
            'CASCADE'
        );
        return true;
    }

    public function safeDown()
    {
        return true;
    }

    public function deleteRule(): ?string
    {
        $rule = (new Query())
            ->select('DELETE_RULE')
            ->from('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where([
                'CONSTRAINT_SCHEMA' => $this->db->createCommand('SELECT DATABASE()')->queryScalar(),
                'TABLE_NAME' => 'custom_form_answer_field',
                'CONSTRAINT_NAME' => 'fk_cfaf_field',
            ])
            ->scalar();
        return $rule === false || $rule === null ? null : (string)$rule;
    }
}
