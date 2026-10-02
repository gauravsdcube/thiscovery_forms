<?php

use humhub\components\Migration;

/**
 * Trash, restore and permanent delete of whole forms are logged (GOV-7).
 */
class m261004_100000_form_lifecycle_log extends Migration
{
    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('{{%custom_form_lifecycle_log}}', true) === null) {
            $this->createTable('{{%custom_form_lifecycle_log}}', [
                'id' => $this->primaryKey(),
                'form_id' => $this->integer()->notNull(),
                'title' => $this->string(255)->notNull()->defaultValue(''),
                'action' => $this->string(16)->notNull(),
                'previous_state' => $this->integer()->null(),
                'answers' => $this->integer()->notNull()->defaultValue(0),
                'actor_id' => $this->integer()->null(),
                'reason' => $this->string(255)->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('idx_cf_lifecycle_form', '{{%custom_form_lifecycle_log}}', ['form_id', 'id']);
        }
        return true;
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema('{{%custom_form_lifecycle_log}}', true) !== null) {
            $this->dropTable('{{%custom_form_lifecycle_log}}');
        }
        return true;
    }
}
