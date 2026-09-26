<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;

/**
 * Store the wave on an unlinked anonymous completion so reminders can see it
 * without joining through the answer.
 */
class m260926_120000_anonymous_completion_wave extends Migration
{
    public function safeUp()
    {
        $table = 'form_panel_activity';
        $schema = $this->db->getTableSchema($table, true);
        if ($schema === null) {
            return true;
        }
        if (!isset($schema->columns['wave_id'])) {
            $this->addColumn($table, 'wave_id', $this->integer()->null()->after('answer_id'));
        }
        if ($this->db->getTableSchema($table, true)->getColumn('wave_id') !== null) {
            try {
                $this->createIndex('idx-form_panel_activity-anon', $table, ['member_id', 'form_id', 'wave_id']);
            } catch (\Throwable $e) {
                // Index already present.
            }
        }
        return true;
    }

    public function safeDown()
    {
        $table = 'form_panel_activity';
        $schema = $this->db->getTableSchema($table, true);
        if ($schema === null || !isset($schema->columns['wave_id'])) {
            return true;
        }
        try {
            $this->dropIndex('idx-form_panel_activity-anon', $table);
        } catch (\Throwable $e) {
            // Index already gone.
        }
        $this->dropColumn($table, 'wave_id');
        return true;
    }
}
