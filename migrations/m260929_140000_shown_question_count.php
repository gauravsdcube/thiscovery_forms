<?php

use humhub\components\Migration;

/**
 * Stores how many questions were on the route, so speeding is seconds per question.
 */
class m260929_140000_shown_question_count extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%custom_form_integrity_meta}}', true);
        if ($table && !isset($table->columns['shown_question_count'])) {
            $this->addColumn('{{%custom_form_integrity_meta}}', 'shown_question_count', $this->integer()->null());
        }
    }

    public function safeDown()
    {
        $table = $this->db->schema->getTableSchema('{{%custom_form_integrity_meta}}', true);
        if ($table && isset($table->columns['shown_question_count'])) {
            $this->dropColumn('{{%custom_form_integrity_meta}}', 'shown_question_count');
        }
    }
}
