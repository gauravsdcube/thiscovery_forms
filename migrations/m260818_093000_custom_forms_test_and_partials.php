<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use yii\db\Migration;

class m260818_093000_custom_forms_test_and_partials extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('custom_form_answer', true);
        if ($table === null || isset($table->columns['is_test'])) {
            return;
        }

        $this->addColumn('custom_form_answer', 'is_test', $this->boolean()->notNull()->defaultValue(false)->after('status'));
        $this->createIndex('idx_cf_answer_form_test_status', 'custom_form_answer', ['form_id', 'is_test', 'status']);
    }

    public function safeDown()
    {
        $table = $this->db->schema->getTableSchema('custom_form_answer', true);
        if ($table === null || !isset($table->columns['is_test'])) {
            return;
        }
        $this->dropIndex('idx_cf_answer_form_test_status', 'custom_form_answer');
        $this->dropColumn('custom_form_answer', 'is_test');
    }
}
