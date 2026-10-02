<?php

use humhub\components\Migration;

/**
 * Prepared files that a named contact can download with a one-time code.
 */
class m261002_193000_secure_send extends Migration
{
    public function safeUp()
    {
        $release = '{{%custom_form_secure_release}}';
        if ($this->db->schema->getTableSchema($release, true) === null) {
            $this->createTable($release, [
                'id' => $this->primaryKey(),
                'form_id' => $this->integer()->notNull(),
                'label' => $this->string(160)->notNull(),
                'contact_name' => $this->string(160)->notNull(),
                'contact_email' => $this->string(190)->notNull(),
                'source' => $this->string(16)->notNull(),
                'storage_name' => $this->string(64)->null(),
                'original_name' => $this->string(200)->notNull(),
                'byte_size' => $this->integer()->notNull()->defaultValue(0),
                'row_count' => $this->integer()->null(),
                'link_hash' => $this->string(64)->notNull(),
                'status' => $this->string(16)->notNull()->defaultValue('active'),
                'failed_attempts' => $this->smallInteger()->notNull()->defaultValue(0),
                'created_by' => $this->integer()->null(),
                'created_at' => $this->dateTime()->notNull(),
                'revoked_at' => $this->dateTime()->null(),
                'revoked_by' => $this->integer()->null(),
            ]);
            $this->createIndex('uidx_cf_secure_release_link', $release, 'link_hash', true);
            $this->createIndex('idx_cf_secure_release_form', $release, 'form_id');
        }

        $code = '{{%custom_form_secure_code}}';
        if ($this->db->schema->getTableSchema($code, true) === null) {
            $this->createTable($code, [
                'id' => $this->primaryKey(),
                'release_id' => $this->integer()->notNull(),
                'code_hash' => $this->string(64)->notNull(),
                'expires_at' => $this->dateTime()->notNull(),
                'used_at' => $this->dateTime()->null(),
                'revoked_at' => $this->dateTime()->null(),
                'source' => $this->string(16)->notNull(),
                'created_by' => $this->integer()->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('idx_cf_secure_code_release', $code, 'release_id');
        }

        $event = '{{%custom_form_secure_event}}';
        if ($this->db->schema->getTableSchema($event, true) === null) {
            $this->createTable($event, [
                'id' => $this->primaryKey(),
                'release_id' => $this->integer()->notNull(),
                'form_id' => $this->integer()->notNull(),
                'event' => $this->string(32)->notNull(),
                'actor_id' => $this->integer()->null(),
                'ip' => $this->string(45)->null(),
                'user_agent' => $this->string(500)->null(),
                'accept_language' => $this->string(255)->null(),
                'detail' => $this->text()->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('idx_cf_secure_event_release', $event, ['release_id', 'id']);
            $this->createIndex('idx_cf_secure_event_form', $event, 'form_id');
        }
    }

    public function safeDown()
    {
        foreach (['{{%custom_form_secure_event}}', '{{%custom_form_secure_code}}', '{{%custom_form_secure_release}}'] as $table) {
            if ($this->db->schema->getTableSchema($table, true) !== null) {
                $this->dropTable($table);
            }
        }
    }
}
