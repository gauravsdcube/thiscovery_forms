<?php

use humhub\components\Migration;

class m260815_150000_custom_forms_project_approval extends Migration
{
    public function safeUp()
    {
        $this->safeCreateTable('custom_form_approval_stage', [
            'id' => $this->primaryKey(),
            'form_id' => $this->integer()->notNull(),
            'name' => $this->string(255)->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'require_all' => $this->boolean()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->null(),
            'updated_at' => $this->dateTime()->null(),
        ]);
        $this->safeAddForeignKey('fk-cf-approval-stage-form', 'custom_form_approval_stage', 'form_id', 'custom_form', 'id', 'CASCADE');
        $this->safeCreateIndex('idx-cf-approval-stage-form', 'custom_form_approval_stage', ['form_id', 'sort_order']);

        $this->safeCreateTable('custom_form_approval_authority', [
            'id' => $this->primaryKey(),
            'stage_id' => $this->integer()->notNull(),
            'type' => $this->string(16)->notNull(),
            'user_id' => $this->integer()->null(),
            'group_id' => $this->integer()->null(),
        ]);
        $this->safeAddForeignKey('fk-cf-approval-auth-stage', 'custom_form_approval_authority', 'stage_id', 'custom_form_approval_stage', 'id', 'CASCADE');
        $this->safeCreateIndex('idx-cf-approval-auth-stage', 'custom_form_approval_authority', 'stage_id');
        $this->safeCreateIndex('idx-cf-approval-auth-user', 'custom_form_approval_authority', 'user_id');
        $this->safeCreateIndex('idx-cf-approval-auth-group', 'custom_form_approval_authority', 'group_id');

        $this->safeAddColumn('custom_form_answer', 'workflow_status', $this->string(32)->notNull()->defaultValue('none'));
        $this->safeAddColumn('custom_form_answer', 'current_stage_id', $this->integer()->null());
        $this->safeAddColumn('custom_form_answer', 'submitted_at', $this->dateTime()->null());
        $this->safeCreateIndex('idx-cf-answer-workflow', 'custom_form_answer', ['form_id', 'workflow_status']);

        $this->safeCreateTable('custom_form_answer_approval', [
            'id' => $this->primaryKey(),
            'answer_id' => $this->integer()->notNull(),
            'stage_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'action' => $this->string(32)->notNull(),
            'comment' => $this->text()->null(),
            'created_at' => $this->dateTime()->null(),
        ]);
        $this->safeAddForeignKey('fk-cf-answer-approval-answer', 'custom_form_answer_approval', 'answer_id', 'custom_form_answer', 'id', 'CASCADE');
        $this->safeAddForeignKey('fk-cf-answer-approval-stage', 'custom_form_answer_approval', 'stage_id', 'custom_form_approval_stage', 'id', 'CASCADE');
        $this->safeCreateIndex('idx-cf-answer-approval-answer', 'custom_form_answer_approval', ['answer_id', 'stage_id']);
    }

    public function safeDown()
    {
        $this->safeDropTable('custom_form_answer_approval');
        $this->safeDropIndex('idx-cf-answer-workflow', 'custom_form_answer');
        $this->safeDropColumn('custom_form_answer', 'submitted_at');
        $this->safeDropColumn('custom_form_answer', 'current_stage_id');
        $this->safeDropColumn('custom_form_answer', 'workflow_status');
        $this->safeDropTable('custom_form_approval_authority');
        $this->safeDropTable('custom_form_approval_stage');
    }
}
