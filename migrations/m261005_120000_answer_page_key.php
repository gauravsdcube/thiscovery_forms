<?php

use humhub\components\Migration;

/**
 * A saved draft remembers its page by key as well as by position, so it resumes on the right
 * page even if the page list is different when it is reopened (DAT-16).
 */
class m261005_120000_answer_page_key extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%custom_form_answer}}', true);
        if ($table !== null && !isset($table->columns['current_page_key'])) {
            $this->addColumn('{{%custom_form_answer}}', 'current_page_key', $this->string(64)->null()->after('current_page'));
        }
        return true;
    }

    public function safeDown()
    {
        $table = $this->db->schema->getTableSchema('{{%custom_form_answer}}', true);
        if ($table !== null && isset($table->columns['current_page_key'])) {
            $this->dropColumn('{{%custom_form_answer}}', 'current_page_key');
        }
        return true;
    }
}
