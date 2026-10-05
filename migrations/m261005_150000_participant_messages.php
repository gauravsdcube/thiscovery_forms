<?php

use humhub\components\Migration;

/**
 * Site and per-form wording for buttons, checks, and endings, plus a language of each email template.
 */
class m261005_150000_participant_messages extends Migration
{
    public function safeUp()
    {
        $messages = '{{%custom_form_message_i18n}}';
        if ($this->db->schema->getTableSchema($messages, true) === null) {
            $this->createTable($messages, [
                'id' => $this->primaryKey(),
                'form_id' => $this->integer()->notNull()->defaultValue(0),
                'language' => $this->string(16)->notNull(),
                'message_key' => $this->string(80)->notNull(),
                'text' => $this->text()->notNull(),
                'locked' => $this->smallInteger()->notNull()->defaultValue(0),
                'source_hash' => $this->string(64)->null(),
                'updated_at' => $this->dateTime()->null(),
            ]);
            $this->createIndex('uidx_cf_message_i18n', $messages, ['form_id', 'language', 'message_key'], true);
        }

        $emails = '{{%form_email_template_i18n}}';
        if ($this->db->schema->getTableSchema($emails, true) === null) {
            $this->createTable($emails, [
                'id' => $this->primaryKey(),
                'template_id' => $this->integer()->notNull(),
                'language' => $this->string(16)->notNull(),
                'subject' => $this->string(255)->null(),
                'header_html' => $this->text()->null(),
                'body_html' => $this->text()->null(),
                'footer_html' => $this->text()->null(),
                'locked' => $this->smallInteger()->notNull()->defaultValue(0),
                'updated_at' => $this->dateTime()->null(),
            ]);
            $this->createIndex('uidx_form_email_template_i18n', $emails, ['template_id', 'language'], true);
        }
    }

    public function safeDown()
    {
        foreach (['{{%form_email_template_i18n}}', '{{%custom_form_message_i18n}}'] as $table) {
            if ($this->db->schema->getTableSchema($table, true) !== null) {
                $this->dropTable($table);
            }
        }
    }
}
