<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;

/**
 * Stop a question delete from cascading into stored answers.
 * safeDown restores ON DELETE CASCADE and drops deleted_at.
 */
class m260926_130000_answer_field_restrict extends Migration
{
    public function safeUp()
    {
        $schema = $this->db->getTableSchema('custom_form_field', true);
        if ($schema !== null && !isset($schema->columns['deleted_at'])) {
            $this->addColumn('custom_form_field', 'deleted_at', $this->dateTime()->null());
        }
        $this->dropForeignKey('fk_cfaf_field', 'custom_form_answer_field');
        $this->addForeignKey(
            'fk_cfaf_field',
            'custom_form_answer_field',
            'field_id',
            'custom_form_field',
            'id',
            'RESTRICT',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_cfaf_field', 'custom_form_answer_field');
        $this->addForeignKey(
            'fk_cfaf_field',
            'custom_form_answer_field',
            'field_id',
            'custom_form_field',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $schema = $this->db->getTableSchema('custom_form_field', true);
        if ($schema !== null && isset($schema->columns['deleted_at'])) {
            $this->dropColumn('custom_form_field', 'deleted_at');
        }
    }
}
