<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;

class m260907_130000_llm_usage extends Migration
{
    public function safeUp()
    {
        $table = '{{%custom_form_llm_usage}}';
        if ($this->db->getTableSchema($table, true) !== null) {
            return;
        }

        $this->createTable($table, [
            'id' => $this->primaryKey(),
            'created_at' => $this->dateTime()->notNull(),
            'user_id' => $this->integer()->null(),
            'form_id' => $this->integer()->null(),
            'session_id' => $this->string(64)->null(),
            'purpose' => $this->string(32)->notNull()->defaultValue('map'),
            'provider' => $this->string(32)->notNull()->defaultValue('openai'),
            'model' => $this->string(128)->null(),
            'prompt_tokens' => $this->integer()->notNull()->defaultValue(0),
            'completion_tokens' => $this->integer()->notNull()->defaultValue(0),
            'total_tokens' => $this->integer()->notNull()->defaultValue(0),
            'estimated_cost' => $this->decimal(12, 6)->notNull()->defaultValue(0),
            'latency_ms' => $this->integer()->notNull()->defaultValue(0),
            'success' => $this->tinyInteger(1)->notNull()->defaultValue(1),
            'error_code' => $this->string(190)->null(),
        ]);

        $this->createIndex('idx-cf-llm-created', $table, ['created_at']);
        $this->createIndex('idx-cf-llm-user', $table, ['user_id', 'created_at']);
        $this->createIndex('idx-cf-llm-session', $table, ['session_id']);
        $this->createIndex('idx-cf-llm-form', $table, ['form_id']);
    }

    public function safeDown()
    {
        $table = '{{%custom_form_llm_usage}}';
        if ($this->db->getTableSchema($table, true) !== null) {
            $this->dropTable($table);
        }
    }
}
