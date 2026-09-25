<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;

/**
 * Store UAT test results and proposed new scenarios from testers.
 */
class m260905_134600_uat_submissions extends Migration
{
    public function safeUp()
    {
        $table = '{{%custom_form_uat_submission}}';
        if ($this->db->getTableSchema($table, true) !== null) {
            return;
        }

        $this->createTable($table, [
            'id' => $this->primaryKey(),
            'kind' => $this->string(16)->notNull()->defaultValue('result'),
            'test_id' => $this->string(32)->null(),
            'feature' => $this->string(128)->null(),
            'scenario' => $this->string(255)->null(),
            'explanation' => $this->text()->null(),
            'preconditions' => $this->text()->null(),
            'steps' => $this->text()->null(),
            'expected_behaviour' => $this->text()->null(),
            'priority' => $this->string(16)->null(),
            'roles' => $this->string(128)->null(),
            'result' => $this->string(16)->null(),
            'comments' => $this->text()->null(),
            'tester_name' => $this->string(120)->null(),
            'tester_email' => $this->string(255)->null(),
            'environment_url' => $this->string(512)->null(),
            'module_version' => $this->string(32)->null(),
            'evidence_guid' => $this->string(45)->null(),
            'status' => $this->string(16)->notNull()->defaultValue('new'),
            'admin_notes' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'created_by' => $this->integer()->null(),
            'updated_at' => $this->dateTime()->null(),
            'updated_by' => $this->integer()->null(),
        ]);

        $this->createIndex('idx-cf-uat-kind-status', $table, ['kind', 'status']);
        $this->createIndex('idx-cf-uat-test-id', $table, ['test_id']);
        $this->createIndex('idx-cf-uat-created', $table, ['created_at']);
    }

    public function safeDown()
    {
        $table = '{{%custom_form_uat_submission}}';
        if ($this->db->getTableSchema($table, true) !== null) {
            $this->dropTable($table);
        }
    }
}
