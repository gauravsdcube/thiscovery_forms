<?php

use humhub\components\Migration;

class m260814_200000_custom_forms_wave_invite extends Migration
{
    public function safeUp()
    {
        $this->safeAddColumn(
            'form_wave',
            'invited_at',
            $this->dateTime()->null()->after('closes_at')
        );
    }

    public function safeDown()
    {
        $this->safeDropColumn('form_wave', 'invited_at');
    }
}
