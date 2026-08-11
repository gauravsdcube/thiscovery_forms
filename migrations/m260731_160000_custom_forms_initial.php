<?php

use humhub\components\Migration;

class m260731_160000_custom_forms_initial extends Migration
{
    public function safeUp()
    {
        $this->safeCreateTable('custom_form', [
            'id' => $this->primaryKey(),
            'title' => $this->string(255)->notNull(),
            'description' => $this->text()->null(),
            'status' => $this->tinyInteger()->notNull()->defaultValue(0),
            'allow_multiple' => $this->boolean()->notNull()->defaultValue(0),
            'show_in_menu' => $this->boolean()->notNull()->defaultValue(0),
            'answers_visibility' => $this->string(32)->notNull()->defaultValue('managers'),
        ]);

        $this->safeCreateTable('custom_form_field', [
            'id' => $this->primaryKey(),
            'form_id' => $this->integer()->notNull(),
            'type' => $this->string(32)->notNull(),
            'label' => $this->string(255)->notNull(),
            'help_text' => $this->string(500)->null(),
            'required' => $this->boolean()->notNull()->defaultValue(0),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'options_json' => $this->text()->null(),
            'condition_field_id' => $this->integer()->null(),
            'condition_operator' => $this->string(32)->null(),
            'condition_value' => $this->string(255)->null(),
        ]);
        $this->safeAddForeignKey('fk_cff_form', 'custom_form_field', 'form_id', 'custom_form', 'id', 'CASCADE');
        $this->safeCreateIndex('idx_cff_form_sort', 'custom_form_field', ['form_id', 'sort_order']);

        $this->safeCreateTable('custom_form_answer', [
            'id' => $this->primaryKey(),
            'form_id' => $this->integer()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'created_by' => $this->integer()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
            'updated_by' => $this->integer()->notNull(),
        ]);
        $this->safeAddForeignKey('fk_cfa_form', 'custom_form_answer', 'form_id', 'custom_form', 'id', 'CASCADE');
        $this->safeAddForeignKey('fk_cfa_created_by', 'custom_form_answer', 'created_by', 'user', 'id', 'CASCADE');
        $this->safeAddForeignKey('fk_cfa_updated_by', 'custom_form_answer', 'updated_by', 'user', 'id', 'CASCADE');
        $this->safeCreateIndex('idx_cfa_form_user', 'custom_form_answer', ['form_id', 'created_by']);

        $this->safeCreateTable('custom_form_answer_field', [
            'id' => $this->primaryKey(),
            'answer_id' => $this->integer()->notNull(),
            'field_id' => $this->integer()->notNull(),
            'value' => $this->text()->null(),
        ]);
        $this->safeAddForeignKey('fk_cfaf_answer', 'custom_form_answer_field', 'answer_id', 'custom_form_answer', 'id', 'CASCADE');
        $this->safeAddForeignKey('fk_cfaf_field', 'custom_form_answer_field', 'field_id', 'custom_form_field', 'id', 'CASCADE');
        $this->safeCreateIndex('idx_cfaf_answer_field', 'custom_form_answer_field', ['answer_id', 'field_id'], true);
    }

    public function safeDown()
    {
        $this->safeDropTable('custom_form_answer_field');
        $this->safeDropTable('custom_form_answer');
        $this->safeDropTable('custom_form_field');
        $this->safeDropTable('custom_form');
    }
}
