<?php

use yii\db\Migration;

class uninstall extends Migration
{
    public function up()
    {
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
