<?php

use humhub\components\Migration;

class m260818_180000_email_templates extends Migration
{
    public function safeUp()
    {
        $this->safeCreateTable('form_email_template', [
            'id' => $this->primaryKey(),
            'contentcontainer_id' => $this->integer()->null(),
            'created_by' => $this->integer()->null(),
            'title' => $this->string(255)->notNull(),
            'subject' => $this->string(255)->notNull(),
            'body_html' => $this->text()->null(),
            'created_at' => $this->dateTime()->null(),
            'updated_at' => $this->dateTime()->null(),
        ]);
        $this->safeCreateIndex('idx-form_email_template-container', 'form_email_template', 'contentcontainer_id');

        $this->safeCreateTable('form_email_send', [
            'id' => $this->primaryKey(),
            'template_id' => $this->integer()->null(),
            'form_id' => $this->integer()->null(),
            'member_id' => $this->integer()->null(),
            'answer_id' => $this->integer()->null(),
            'wave_id' => $this->integer()->null(),
            'field_id' => $this->integer()->null(),
            'kind' => $this->string(32)->notNull(),
            'email' => $this->string(255)->null(),
            'created_at' => $this->dateTime()->null(),
        ]);
        $this->safeCreateIndex('idx-form_email_send-kind', 'form_email_send', ['kind', 'form_id', 'member_id', 'wave_id']);
        $this->safeCreateIndex('idx-form_email_send-answer', 'form_email_send', ['kind', 'answer_id', 'field_id']);
    }

    public function safeDown()
    {
        $this->safeDropTable('form_email_send');
        $this->safeDropTable('form_email_template');
    }
}
