<?php

use humhub\components\Migration;

/**
 * Distinguishes action emails when several people have no answer id yet.
 */
class m260929_100000_email_send_actor extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%form_email_send}}', true);
        if ($table && !isset($table->columns['actor_key'])) {
            $this->addColumn('{{%form_email_send}}', 'actor_key', $this->string(64)->null());
        }
    }

    public function safeDown()
    {
        $table = $this->db->schema->getTableSchema('{{%form_email_send}}', true);
        if ($table && isset($table->columns['actor_key'])) {
            $this->dropColumn('{{%form_email_send}}', 'actor_key');
        }
    }
}
