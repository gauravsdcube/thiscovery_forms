<?php

use humhub\components\Migration;

/**
 * The erasure log records how (pseudonymise or delete), who, why, and for which panel member,
 * so single-response deletion and member erasure are audited too (GOV-4).
 */
class m261005_130000_erasure_modes extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%custom_form_erasure}}', true);
        if ($table === null) {
            return true;
        }
        $this->alterColumn('{{%custom_form_erasure}}', 'answer_id', $this->integer()->null());
        $add = [
            'mode' => $this->string(16)->notNull()->defaultValue('pseudonymise'),
            'member_id' => $this->integer()->null(),
            'actor_id' => $this->integer()->null(),
            'reason' => $this->string(255)->null(),
        ];
        foreach ($add as $column => $type) {
            if (!isset($table->columns[$column])) {
                $this->addColumn('{{%custom_form_erasure}}', $column, $type);
            }
        }
        return true;
    }

    public function safeDown()
    {
        $table = $this->db->schema->getTableSchema('{{%custom_form_erasure}}', true);
        if ($table === null) {
            return true;
        }
        foreach (['mode', 'member_id', 'actor_id', 'reason'] as $column) {
            if (isset($table->columns[$column])) {
                $this->dropColumn('{{%custom_form_erasure}}', $column);
            }
        }
        return true;
    }
}
