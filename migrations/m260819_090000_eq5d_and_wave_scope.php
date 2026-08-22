<?php

use humhub\components\Migration;
use yii\db\Query;

class m260819_090000_eq5d_and_wave_scope extends Migration
{
    public function safeUp()
    {
        $this->safeAddColumn('form_wave', 'panel_id', $this->integer()->null()->after('form_id'));
        $this->alterColumn('form_wave', 'form_id', $this->integer()->null());
        $this->safeAddForeignKey('fk-form_wave-panel', 'form_wave', 'panel_id', 'form_panel', 'id', 'CASCADE');
        $this->safeCreateIndex('idx-form_wave-panel-number', 'form_wave', ['panel_id', 'wave_number']);

        $row = (new Query())
            ->from('setting')
            ->where(['module_id' => 'thiscovery-forms', 'name' => 'enabled_kinds'])
            ->one();
        if ($row && !empty($row['value'])) {
            $kinds = json_decode((string)$row['value'], true);
            if (is_array($kinds) && !in_array('eq5d', $kinds, true)) {
                $kinds[] = 'eq5d';
                $this->update('setting', [
                    'value' => json_encode(array_values($kinds)),
                ], ['id' => (int)$row['id']]);
            }
        }
    }

    public function safeDown()
    {
        $this->safeDropForeignKey('fk-form_wave-panel', 'form_wave');
        $this->safeDropIndex('idx-form_wave-panel-number', 'form_wave');
        $this->safeDropColumn('form_wave', 'panel_id');
        $this->alterColumn('form_wave', 'form_id', $this->integer()->notNull());
    }
}
