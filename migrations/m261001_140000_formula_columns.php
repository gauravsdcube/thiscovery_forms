<?php

use humhub\components\Migration;

/**
 * Calculated variables and the frozen calendar date for today().
 * Existing show/hide rules stay in logic_json. This does not rewrite them.
 */
class m261001_140000_formula_columns extends Migration
{
    public function safeUp()
    {
        $started = microtime(true);
        $schema = $this->db->schema->getTableSchema('{{%custom_form_answer}}', true);
        if ($schema && !isset($schema->columns['variables_json'])) {
            $this->addColumn('{{%custom_form_answer}}', 'variables_json', $this->text()->null());
        }
        $schema = $this->db->schema->getTableSchema('{{%custom_form_answer}}', true);
        if ($schema && !isset($schema->columns['formula_today'])) {
            $this->addColumn('{{%custom_form_answer}}', 'formula_today', $this->date()->null());
        }
        $seconds = number_format(microtime(true) - $started, 3, '.', '');
        echo "formula columns migration seconds={$seconds}\n";
    }

    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema('{{%custom_form_answer}}', true);
        if ($schema && isset($schema->columns['formula_today'])) {
            $this->dropColumn('{{%custom_form_answer}}', 'formula_today');
        }
        $schema = $this->db->schema->getTableSchema('{{%custom_form_answer}}', true);
        if ($schema && isset($schema->columns['variables_json'])) {
            $this->dropColumn('{{%custom_form_answer}}', 'variables_json');
        }
        return true;
    }
}
