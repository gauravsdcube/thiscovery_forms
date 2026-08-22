<?php

use humhub\components\Migration;

class m260818_170000_standalone_panels extends Migration
{
    public function safeUp()
    {
        $this->safeAddColumn('form_panel_member', 'first_name', $this->string(255)->null()->after('email'));
        $this->safeAddColumn('form_panel_member', 'last_name', $this->string(255)->null()->after('first_name'));

        $this->safeCreateTable('form_panel_activity', [
            'id' => $this->primaryKey(),
            'panel_id' => $this->integer()->notNull(),
            'member_id' => $this->integer()->notNull(),
            'form_id' => $this->integer()->notNull(),
            'answer_id' => $this->integer()->null(),
            'created_at' => $this->dateTime()->null(),
        ]);
        $this->safeAddForeignKey('fk-form_panel_activity-panel', 'form_panel_activity', 'panel_id', 'form_panel', 'id', 'CASCADE');
        $this->safeAddForeignKey('fk-form_panel_activity-member', 'form_panel_activity', 'member_id', 'form_panel_member', 'id', 'CASCADE');
        $this->safeCreateIndex('idx-form_panel_activity-panel', 'form_panel_activity', 'panel_id');
        $this->safeCreateIndex('idx-form_panel_activity-member', 'form_panel_activity', 'member_id');
        $this->safeCreateIndex('idx-form_panel_activity-answer', 'form_panel_activity', ['member_id', 'answer_id']);
    }

    public function safeDown()
    {
        $this->safeDropTable('form_panel_activity');
        $this->safeDropColumn('form_panel_member', 'last_name');
        $this->safeDropColumn('form_panel_member', 'first_name');
    }
}
