<?php

use humhub\components\Migration;
use yii\db\Query;

/**
 * Response outcome, and the tables that record what a respondent was shown and which arm they were given.
 * Arm definitions themselves stay in settings_json so a published edition keeps the arms it snapshotted.
 */
class m260929_180000_randomisation extends Migration
{
    public function safeUp()
    {
        $answer = $this->db->schema->getTableSchema('{{%custom_form_answer}}', true);
        if ($answer && !isset($answer->columns['outcome'])) {
            $this->addColumn('{{%custom_form_answer}}', 'outcome', $this->string(32)->notNull()->defaultValue(''));
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_presentation}}', true)) {
            $this->createTable('{{%custom_form_presentation}}', [
                'answer_id' => $this->integer()->notNull(),
                'seed' => $this->char(32)->notNull(),
                'orders_json' => $this->text()->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->addPrimaryKey('pk_cf_presentation', '{{%custom_form_presentation}}', 'answer_id');
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_arm_assignment}}', true)) {
            $this->createTable('{{%custom_form_arm_assignment}}', [
                'answer_id' => $this->integer()->notNull(),
                'arm_code' => $this->string(64)->notNull(),
                'arm_name' => $this->string(255)->notNull(),
                'method' => $this->string(32)->notNull(),
                'stratum_key' => $this->string(255)->notNull()->defaultValue(''),
                'assigned_at' => $this->dateTime()->notNull(),
                'assigned_by' => $this->integer()->null(),
            ]);
            $this->addPrimaryKey('pk_cf_arm_assignment', '{{%custom_form_arm_assignment}}', 'answer_id');
            $this->createIndex('idx_cf_arm_assignment_code', '{{%custom_form_arm_assignment}}', 'arm_code');
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_arm_allocation}}', true)) {
            $this->createTable('{{%custom_form_arm_allocation}}', [
                'form_id' => $this->integer()->notNull(),
                'stratum_key' => $this->string(255)->notNull()->defaultValue(''),
                'next_index' => $this->integer()->notNull()->defaultValue(0),
                'block_json' => $this->text()->null(),
            ]);
            $this->addPrimaryKey('pk_cf_arm_allocation', '{{%custom_form_arm_allocation}}', ['form_id', 'stratum_key']);
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_arm_override}}', true)) {
            $this->createTable('{{%custom_form_arm_override}}', [
                'id' => $this->primaryKey(),
                'answer_id' => $this->integer()->notNull(),
                'from_code' => $this->string(64)->notNull(),
                'to_code' => $this->string(64)->notNull(),
                'reason' => $this->text()->notNull(),
                'actor_id' => $this->integer()->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('idx_cf_arm_override_answer', '{{%custom_form_arm_override}}', 'answer_id');
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_rotate_seq}}', true)) {
            $this->createTable('{{%custom_form_rotate_seq}}', [
                'form_id' => $this->integer()->notNull(),
                'scope_key' => $this->string(64)->notNull(),
                'next_offset' => $this->integer()->notNull()->defaultValue(0),
            ]);
            $this->addPrimaryKey('pk_cf_rotate_seq', '{{%custom_form_rotate_seq}}', ['form_id', 'scope_key']);
        }
    }

    public function safeDown()
    {
        $answer = $this->db->schema->getTableSchema('{{%custom_form_answer}}', true);
        if ($answer && isset($answer->columns['outcome'])) {
            $kept = (new Query())
                ->from('{{%custom_form_answer}}')
                ->where(['not in', 'outcome', ['', 'complete']])
                ->exists($this->db);
            if ($kept) {
                echo "custom_form_answer.outcome has screened-out or other values; refusing to drop it.\n";
                return false;
            }
            $this->dropColumn('{{%custom_form_answer}}', 'outcome');
        }
        foreach ([
            '{{%custom_form_rotate_seq}}',
            '{{%custom_form_arm_override}}',
            '{{%custom_form_arm_allocation}}',
            '{{%custom_form_arm_assignment}}',
            '{{%custom_form_presentation}}',
        ] as $table) {
            if ($this->db->schema->getTableSchema($table, true)) {
                $this->dropTable($table);
            }
        }
        return true;
    }
}
