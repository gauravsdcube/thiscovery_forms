<?php

use humhub\components\Migration;

/**
 * The instance-key migration could leave the old (answer_id, field_id) unique
 * index in place. A second loop cell cannot be stored while that index exists.
 */
class m261001_110000_drop_legacy_answer_field_index extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getRawTableName('{{%custom_form_answer_field}}');
        $legacy = $this->db->createCommand(
            'SHOW INDEX FROM ' . $table . ' WHERE Key_name = ' . $this->db->quoteValue('idx_cfaf_answer_field')
        )->queryAll();
        if ($legacy !== []) {
            $this->execute('ALTER TABLE ' . $table . ' DROP INDEX idx_cfaf_answer_field');
        }
    }

    public function safeDown()
    {
        return true;
    }
}
