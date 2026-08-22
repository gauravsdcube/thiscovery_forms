<?php

use humhub\components\Migration;

class m260821_170000_panel_member_fields extends Migration
{
    public function safeUp()
    {
        $this->safeAddColumn('form_panel', 'fields_json', $this->text()->null()->after('description'));
    }

    public function safeDown()
    {
        $this->safeDropColumn('form_panel', 'fields_json');
    }
}
