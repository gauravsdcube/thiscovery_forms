<?php

use humhub\components\Migration;

class m260828_200000_form_response_integrity extends Migration
{
    public function safeUp()
    {
        $this->createTable('custom_form_integrity_meta', [
            'id' => $this->primaryKey(),
            'answer_id' => $this->integer()->notNull(),
            'form_id' => $this->integer()->notNull(),
            'started_at' => $this->dateTime()->null(),
            'completed_at' => $this->dateTime()->null(),
            'duration_seconds' => $this->integer()->null(),
            'median_seconds' => $this->integer()->null(),
            'page_timings_json' => $this->text()->null(),
            'question_timings_json' => $this->text()->null(),
            'ip_hash' => $this->string(64)->null(),
            'ip_network_hash' => $this->string(64)->null(),
            'session_hash' => $this->string(64)->null(),
            'user_agent_hash' => $this->string(64)->null(),
            'access_token_hash' => $this->string(64)->null(),
            'access_mode' => $this->string(32)->null(),
            'session_established' => $this->tinyInteger(1)->notNull()->defaultValue(0),
            'honeypot_triggered' => $this->tinyInteger(1)->notNull()->defaultValue(0),
            'rate_limited' => $this->tinyInteger(1)->notNull()->defaultValue(0),
            'captcha_shown' => $this->tinyInteger(1)->notNull()->defaultValue(0),
            'captcha_passed' => $this->tinyInteger(1)->null(),
            'bot_score' => $this->decimal(5, 2)->notNull()->defaultValue(0),
            'duplicate_score' => $this->decimal(5, 2)->notNull()->defaultValue(0),
            'speed_score' => $this->decimal(5, 2)->notNull()->defaultValue(0),
            'straightline_score' => $this->decimal(5, 2)->notNull()->defaultValue(0),
            'attention_score' => $this->decimal(5, 2)->notNull()->defaultValue(0),
            'consistency_score' => $this->decimal(5, 2)->notNull()->defaultValue(0),
            'freetext_score' => $this->decimal(5, 2)->notNull()->defaultValue(0),
            'similarity_score' => $this->decimal(5, 2)->notNull()->defaultValue(0),
            'overall_score' => $this->decimal(5, 2)->notNull()->defaultValue(100),
            'integrity_status' => $this->string(24)->notNull()->defaultValue('trusted'),
            'analysis_status' => $this->string(24)->notNull()->defaultValue('included'),
            'status_override' => $this->string(24)->null(),
            'exclusion_reason' => $this->string(255)->null(),
            'flags_json' => $this->text()->null(),
            'similar_answer_ids_json' => $this->text()->null(),
            'notes' => $this->text()->null(),
            'created_at' => $this->dateTime()->null(),
            'updated_at' => $this->dateTime()->null(),
        ]);
        $this->createIndex('idx-cf-integrity-answer', 'custom_form_integrity_meta', 'answer_id', true);
        $this->createIndex('idx-cf-integrity-form-status', 'custom_form_integrity_meta', ['form_id', 'integrity_status']);
        $this->createIndex('idx-cf-integrity-form-analysis', 'custom_form_integrity_meta', ['form_id', 'analysis_status']);
        $this->createIndex('idx-cf-integrity-ip', 'custom_form_integrity_meta', ['form_id', 'ip_hash']);
        $this->createIndex('idx-cf-integrity-session', 'custom_form_integrity_meta', ['form_id', 'session_hash']);
        $this->addForeignKey('fk-cf-integrity-answer', 'custom_form_integrity_meta', 'answer_id', 'custom_form_answer', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-cf-integrity-form', 'custom_form_integrity_meta', 'form_id', 'custom_form', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('custom_form_integrity_audit', [
            'id' => $this->primaryKey(),
            'form_id' => $this->integer()->notNull(),
            'answer_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->null(),
            'action' => $this->string(48)->notNull(),
            'from_value' => $this->string(64)->null(),
            'to_value' => $this->string(64)->null(),
            'reason' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx-cf-integrity-audit-answer', 'custom_form_integrity_audit', 'answer_id');
        $this->createIndex('idx-cf-integrity-audit-form', 'custom_form_integrity_audit', 'form_id');
        $this->addForeignKey('fk-cf-integrity-audit-answer', 'custom_form_integrity_audit', 'answer_id', 'custom_form_answer', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-cf-integrity-audit-form', 'custom_form_integrity_audit', 'form_id', 'custom_form', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('custom_form_access_token', [
            'id' => $this->primaryKey(),
            'form_id' => $this->integer()->notNull(),
            'token_hash' => $this->string(64)->notNull(),
            'token_hint' => $this->string(8)->null(),
            'label' => $this->string(120)->null(),
            'one_time' => $this->tinyInteger(1)->notNull()->defaultValue(1),
            'max_uses' => $this->integer()->notNull()->defaultValue(1),
            'use_count' => $this->integer()->notNull()->defaultValue(0),
            'expires_at' => $this->dateTime()->null(),
            'last_used_at' => $this->dateTime()->null(),
            'created_by' => $this->integer()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx-cf-access-token-hash', 'custom_form_access_token', 'token_hash', true);
        $this->createIndex('idx-cf-access-token-form', 'custom_form_access_token', 'form_id');
        $this->addForeignKey('fk-cf-access-token-form', 'custom_form_access_token', 'form_id', 'custom_form', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('custom_form_access_token');
        $this->dropTable('custom_form_integrity_audit');
        $this->dropTable('custom_form_integrity_meta');
    }
}
