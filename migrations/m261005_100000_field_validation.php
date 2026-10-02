<?php

use humhub\components\Migration;

/**
 * Per-question answer rules: text length and pattern, date range, and a cross-answer
 * check formula (LOG-12).
 */
class m261005_100000_field_validation extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%custom_form_field}}', true);
        if ($table !== null && !isset($table->columns['validation_json'])) {
            $this->addColumn('{{%custom_form_field}}', 'validation_json', $this->text()->null()->after('actions_json'));
        }
        return true;
    }

    public function safeDown()
    {
        $table = $this->db->schema->getTableSchema('{{%custom_form_field}}', true);
        if ($table !== null && isset($table->columns['validation_json'])) {
            $this->dropColumn('{{%custom_form_field}}', 'validation_json');
        }
        return true;
    }
}
