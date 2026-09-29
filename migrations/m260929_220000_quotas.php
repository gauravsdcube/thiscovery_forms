<?php

use humhub\components\Migration;

/**
 * Quota counters. A place is taken inside the submit transaction.
 * safeDown refuses when any counter is above zero.
 */
class m260929_220000_quotas extends Migration
{
    public function safeUp()
    {
        $answer = $this->db->schema->getTableSchema('{{%custom_form_answer}}', true);
        if ($answer && !isset($answer->columns['quota_marker'])) {
            $this->addColumn('{{%custom_form_answer}}', 'quota_marker', $this->integer()->null());
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_quota}}', true)) {
            $this->createTable('{{%custom_form_quota}}', [
                'id' => $this->primaryKey(),
                'form_id' => $this->integer()->notNull(),
                'wave_id' => $this->integer()->null(),
                'name' => $this->string(255)->notNull(),
                'target' => $this->integer()->notNull(),
                'parent_id' => $this->integer()->null(),
                'rules_json' => $this->text()->null(),
                'count_policy' => $this->string(32)->notNull()->defaultValue('complete'),
                'reserve' => $this->tinyInteger()->notNull()->defaultValue(0),
                'reserve_minutes' => $this->integer()->notNull()->defaultValue(60),
                'action' => $this->string(16)->notNull()->defaultValue('end'),
                'action_message' => $this->text()->null(),
                'action_url' => $this->string(2048)->null(),
                'action_page_key' => $this->string(64)->null(),
                'check_page_key' => $this->string(64)->null(),
                'status' => $this->string(16)->notNull()->defaultValue('open'),
                'edition_id' => $this->integer()->null(),
                'sort_order' => $this->integer()->notNull()->defaultValue(0),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('idx_cf_quota_form', '{{%custom_form_quota}}', 'form_id');
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_quota_counter}}', true)) {
            $this->createTable('{{%custom_form_quota_counter}}', [
                'quota_id' => $this->integer()->notNull(),
                'accepted' => $this->integer()->notNull()->defaultValue(0),
                'reserved' => $this->integer()->notNull()->defaultValue(0),
                'reconciled_at' => $this->dateTime()->null(),
            ]);
            $this->addPrimaryKey('pk_cf_quota_counter', '{{%custom_form_quota_counter}}', 'quota_id');
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_quota_reservation}}', true)) {
            $this->createTable('{{%custom_form_quota_reservation}}', [
                'id' => $this->primaryKey(),
                'quota_id' => $this->integer()->notNull(),
                'answer_id' => $this->integer()->notNull(),
                'expires_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('uidx_cf_quota_reservation', '{{%custom_form_quota_reservation}}', ['quota_id', 'answer_id'], true);
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_quota_audit}}', true)) {
            $this->createTable('{{%custom_form_quota_audit}}', [
                'id' => $this->primaryKey(),
                'quota_id' => $this->integer()->notNull(),
                'actor_id' => $this->integer()->null(),
                'change_json' => $this->text()->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('idx_cf_quota_audit', '{{%custom_form_quota_audit}}', 'quota_id');
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_quota_allowhost}}', true)) {
            $this->createTable('{{%custom_form_quota_allowhost}}', [
                'id' => $this->primaryKey(),
                'form_id' => $this->integer()->notNull(),
                'host' => $this->string(255)->notNull(),
            ]);
            $this->createIndex('uidx_cf_quota_host', '{{%custom_form_quota_allowhost}}', ['form_id', 'host'], true);
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_quota_accept}}', true)) {
            $this->createTable('{{%custom_form_quota_accept}}', [
                'quota_id' => $this->integer()->notNull(),
                'answer_id' => $this->integer()->notNull(),
                'released' => $this->tinyInteger()->notNull()->defaultValue(0),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->addPrimaryKey('pk_cf_quota_accept', '{{%custom_form_quota_accept}}', ['quota_id', 'answer_id']);
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_quota_i18n}}', true)) {
            $this->createTable('{{%custom_form_quota_i18n}}', [
                'quota_id' => $this->integer()->notNull(),
                'language' => $this->string(16)->notNull(),
                'name' => $this->string(255)->null(),
                'message' => $this->text()->null(),
            ]);
            $this->addPrimaryKey('pk_cf_quota_i18n', '{{%custom_form_quota_i18n}}', ['quota_id', 'language']);
        }
    }

    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema('{{%custom_form_quota_counter}}', true);
        if ($schema) {
            $busy = (new \yii\db\Query())
                ->from('{{%custom_form_quota_counter}}')
                ->where(['or', ['>', 'accepted', 0], ['>', 'reserved', 0]])
                ->exists();
            if ($busy) {
                echo "Refusing to drop quota tables while a counter is above zero.\n";
                return false;
            }
        }
        $this->dropTable('{{%custom_form_quota_i18n}}');
        $this->dropTable('{{%custom_form_quota_accept}}');
        $this->dropTable('{{%custom_form_quota_allowhost}}');
        $this->dropTable('{{%custom_form_quota_audit}}');
        $this->dropTable('{{%custom_form_quota_reservation}}');
        $this->dropTable('{{%custom_form_quota_counter}}');
        $this->dropTable('{{%custom_form_quota}}');
        $answer = $this->db->schema->getTableSchema('{{%custom_form_answer}}', true);
        if ($answer && isset($answer->columns['quota_marker'])) {
            $this->dropColumn('{{%custom_form_answer}}', 'quota_marker');
        }
        return true;
    }
}
