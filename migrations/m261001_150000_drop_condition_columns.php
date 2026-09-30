<?php

use humhub\components\Migration;

/**
 * Show and hide rules live in logic_json. The old condition columns are unused.
 * safeDown puts the columns back empty. It does not invent rules.
 */
class m261001_150000_drop_condition_columns extends Migration
{
    public function safeUp()
    {
        $started = microtime(true);
        $schema = $this->db->schema->getTableSchema('{{%custom_form_field}}', true);
        if (!$schema) {
            return true;
        }
        foreach (['condition_field_id', 'condition_operator', 'condition_value'] as $column) {
            if (isset($schema->columns[$column])) {
                $this->dropColumn('{{%custom_form_field}}', $column);
            }
        }
        $seconds = number_format(microtime(true) - $started, 3, '.', '');
        echo "drop condition columns seconds={$seconds}\n";
        return true;
    }

    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema('{{%custom_form_field}}', true);
        if (!$schema) {
            return true;
        }
        if (!isset($schema->columns['condition_field_id'])) {
            $this->addColumn('{{%custom_form_field}}', 'condition_field_id', $this->integer()->null());
        }
        if (!isset($schema->columns['condition_operator'])) {
            $this->addColumn('{{%custom_form_field}}', 'condition_operator', $this->string(32)->null());
        }
        if (!isset($schema->columns['condition_value'])) {
            $this->addColumn('{{%custom_form_field}}', 'condition_value', $this->string(255)->null());
        }
        return true;
    }
}
