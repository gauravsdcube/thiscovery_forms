<?php

use yii\db\Migration;

class uninstall extends Migration
{
    public function up()
    {
        $this->dropTable('custom_form_answer_approval');
        $this->dropTable('custom_form_approval_authority');
        $this->dropTable('custom_form_approval_stage');
        $this->dropTable('custom_form_field_i18n');
        $this->dropTable('custom_form_i18n');
        $this->dropTable('form_round');
        $this->dropTable('form_wave');
        $this->dropTable('form_panel_member');
        $this->dropTable('form_panel');
        $this->dropTable('form_library_item');
        $this->dropTable('custom_form_answer_field');
        $this->dropTable('custom_form_answer');
        $this->dropTable('custom_form_field');
        $this->dropTable('custom_form');
    }

    public function down()
    {
        echo "uninstall cannot be reverted.\n";
        return false;
    }
}
