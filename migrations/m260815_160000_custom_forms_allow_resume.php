<?php

use humhub\components\Migration;

class m260815_160000_custom_forms_allow_resume extends Migration
{
    public function safeUp()
    {
        $this->safeAddColumn(
            'custom_form',
            'allow_resume',
            $this->boolean()->notNull()->defaultValue(0)->after('allow_edit')
        );
        $this->safeAddColumn(
            'custom_form',
            'already_submitted_message',
            $this->text()->null()->after('thank_you_content')
        );
    }

    public function safeDown()
    {
        $this->safeDropColumn('custom_form', 'already_submitted_message');
        $this->safeDropColumn('custom_form', 'allow_resume');
    }
}
