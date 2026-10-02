<?php

use humhub\components\Migration;

/**
 * An edited answer keeps the previous value. Deleting a user records erasure
 * without deleting the research answers.
 */
class m261002_100000_answer_audit_and_erasure extends Migration
{
    public function safeUp()
    {
        if (!$this->db->schema->getTableSchema('{{%custom_form_answer_audit}}', true)) {
            $this->createTable('{{%custom_form_answer_audit}}', [
                'id' => $this->primaryKey(),
                'answer_id' => $this->integer()->notNull(),
                'field_id' => $this->integer()->notNull(),
                'instance_key' => $this->string(191)->notNull()->defaultValue(''),
                'old_value' => $this->text()->null(),
                'new_value' => $this->text()->null(),
                'actor_id' => $this->integer()->null(),
                'reason' => $this->string(255)->notNull()->defaultValue('edit'),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('idx_cf_answer_audit_answer', '{{%custom_form_answer_audit}}', 'answer_id');
        }
        if (!$this->db->schema->getTableSchema('{{%custom_form_erasure}}', true)) {
            $this->createTable('{{%custom_form_erasure}}', [
                'id' => $this->primaryKey(),
                'form_id' => $this->integer()->notNull(),
                'answer_id' => $this->integer()->notNull(),
                'kept_research' => $this->boolean()->notNull()->defaultValue(1),
                'erased_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('idx_cf_erasure_answer', '{{%custom_form_erasure}}', 'answer_id');
        }
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema('{{%custom_form_erasure}}', true)) {
            $this->dropTable('{{%custom_form_erasure}}');
        }
        if ($this->db->schema->getTableSchema('{{%custom_form_answer_audit}}', true)) {
            $this->dropTable('{{%custom_form_answer_audit}}');
        }
    }
}
