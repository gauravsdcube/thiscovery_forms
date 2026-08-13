<?php

use humhub\components\Migration;

class m260813_104500_custom_forms_allow_edit extends Migration
{
    public function safeUp()
    {
        $this->safeAddColumn(
            'custom_form',
            'allow_edit',
            $this->boolean()->notNull()->defaultValue(1)->after('allow_anonymous')
        );
    }

    public function safeDown()
    {
        $this->safeDropColumn('custom_form', 'allow_edit');
    }
}
