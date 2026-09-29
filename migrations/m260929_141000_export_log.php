<?php

use humhub\components\Migration;

/**
 * Records each answers CSV download.
 */
class m260929_141000_export_log extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%custom_form_export_log}}', true);
        if ($table) {
            return;
        }
        $this->createTable('{{%custom_form_export_log}}', [
            'id' => $this->primaryKey(),
            'form_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'scrubbed' => $this->boolean()->notNull()->defaultValue(0),
            'row_count' => $this->integer()->notNull()->defaultValue(0),
        ]);
        $this->createIndex('idx_cf_export_log_form', '{{%custom_form_export_log}}', 'form_id');
    }

    public function safeDown()
    {
        $table = $this->db->schema->getTableSchema('{{%custom_form_export_log}}', true);
        if ($table) {
            $this->dropTable('{{%custom_form_export_log}}');
        }
    }
}
