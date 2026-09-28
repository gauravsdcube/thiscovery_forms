<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;

/**
 * Audit log for identity repair, and a round id on anonymous completions
 * so unlinking an answer does not drop the round.
 */
class m260928_170000_identity_repair_log extends Migration
{
    public function safeUp()
    {
        if ($this->db->getTableSchema('custom_form_identity_repair_log', true) === null) {
            $this->createTable('custom_form_identity_repair_log', [
                'id' => $this->primaryKey(),
                'run_id' => $this->string(64)->notNull(),
                'ran_at' => $this->dateTime()->notNull(),
                'ran_by' => $this->integer()->null(),
                'answer_id' => $this->integer()->null(),
                'table_name' => $this->string(64)->notNull(),
                'row_id' => $this->integer()->notNull(),
                'column_name' => $this->string(64)->notNull(),
                'old_value' => $this->text()->null(),
                'old_is_null' => $this->tinyInteger()->notNull()->defaultValue(0),
            ]);
            $this->createIndex('idx-cf-identity-repair-run', 'custom_form_identity_repair_log', 'run_id');
        }

        $schema = $this->db->getTableSchema('form_panel_activity', true);
        if ($schema !== null && !isset($schema->columns['round_id'])) {
            $this->addColumn('form_panel_activity', 'round_id', $this->integer()->null());
        }
        return true;
    }

    public function safeDown()
    {
        if ($this->db->getTableSchema('custom_form_identity_repair_log', true) !== null) {
            $this->dropTable('custom_form_identity_repair_log');
        }
        $schema = $this->db->getTableSchema('form_panel_activity', true);
        if ($schema !== null && isset($schema->columns['round_id'])) {
            $this->dropColumn('form_panel_activity', 'round_id');
        }
        return true;
    }
}
