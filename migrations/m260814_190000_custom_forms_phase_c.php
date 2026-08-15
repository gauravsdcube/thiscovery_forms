<?php

use humhub\components\Migration;

class m260814_190000_custom_forms_phase_c extends Migration
{
    public function safeUp()
    {
        $this->safeCreateTable('form_panel', [
            'id' => $this->primaryKey(),
            'contentcontainer_id' => $this->integer()->null(),
            'created_by' => $this->integer()->null(),
            'title' => $this->string(255)->notNull(),
            'description' => $this->text()->null(),
            'created_at' => $this->dateTime()->null(),
            'updated_at' => $this->dateTime()->null(),
        ]);
        $this->safeCreateIndex('idx-form_panel-container', 'form_panel', 'contentcontainer_id');

        $this->safeCreateTable('form_panel_member', [
            'id' => $this->primaryKey(),
            'panel_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->null(),
            'email' => $this->string(255)->null(),
            'display_name' => $this->string(255)->null(),
            'token' => $this->string(64)->notNull(),
            'demographics_json' => $this->text()->null(),
            'weight' => $this->decimal(10, 4)->notNull()->defaultValue(1),
            'consent_at' => $this->dateTime()->null(),
            'status' => $this->string(16)->notNull()->defaultValue('active'),
            'created_at' => $this->dateTime()->null(),
            'updated_at' => $this->dateTime()->null(),
        ]);
        $this->safeAddForeignKey('fk-form_panel_member-panel', 'form_panel_member', 'panel_id', 'form_panel', 'id', 'CASCADE');
        $this->safeCreateIndex('idx-form_panel_member-panel', 'form_panel_member', 'panel_id');
        $this->safeCreateIndex('idx-form_panel_member-user', 'form_panel_member', ['panel_id', 'user_id']);
        $this->safeCreateIndex('idx-form_panel_member-email', 'form_panel_member', ['panel_id', 'email']);
        $this->safeCreateIndex('idx-form_panel_member-token', 'form_panel_member', 'token', true);

        $this->safeCreateTable('form_wave', [
            'id' => $this->primaryKey(),
            'form_id' => $this->integer()->notNull(),
            'wave_number' => $this->integer()->notNull()->defaultValue(1),
            'title' => $this->string(255)->null(),
            'status' => $this->string(16)->notNull()->defaultValue('draft'),
            'opens_at' => $this->dateTime()->null(),
            'closes_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->null(),
            'updated_at' => $this->dateTime()->null(),
        ]);
        $this->safeAddForeignKey('fk-form_wave-form', 'form_wave', 'form_id', 'custom_form', 'id', 'CASCADE');
        $this->safeCreateIndex('idx-form_wave-form-number', 'form_wave', ['form_id', 'wave_number'], true);

        $this->safeCreateTable('form_round', [
            'id' => $this->primaryKey(),
            'form_id' => $this->integer()->notNull(),
            'round_number' => $this->integer()->notNull()->defaultValue(1),
            'title' => $this->string(255)->null(),
            'status' => $this->string(16)->notNull()->defaultValue('draft'),
            'opens_at' => $this->dateTime()->null(),
            'closes_at' => $this->dateTime()->null(),
            'summary_html' => $this->text()->null(),
            'frozen_field_ids_json' => $this->text()->null(),
            'published_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->null(),
            'updated_at' => $this->dateTime()->null(),
        ]);
        $this->safeAddForeignKey('fk-form_round-form', 'form_round', 'form_id', 'custom_form', 'id', 'CASCADE');
        $this->safeCreateIndex('idx-form_round-form-number', 'form_round', ['form_id', 'round_number'], true);

        $this->safeCreateTable('custom_form_i18n', [
            'id' => $this->primaryKey(),
            'form_id' => $this->integer()->notNull(),
            'language' => $this->string(16)->notNull(),
            'title' => $this->string(255)->null(),
            'description' => $this->text()->null(),
            'thank_you_content' => $this->text()->null(),
        ]);
        $this->safeAddForeignKey('fk-custom_form_i18n-form', 'custom_form_i18n', 'form_id', 'custom_form', 'id', 'CASCADE');
        $this->safeCreateIndex('idx-custom_form_i18n-form-lang', 'custom_form_i18n', ['form_id', 'language'], true);

        $this->safeCreateTable('custom_form_field_i18n', [
            'id' => $this->primaryKey(),
            'field_id' => $this->integer()->notNull(),
            'language' => $this->string(16)->notNull(),
            'label' => $this->string(255)->null(),
            'help_text' => $this->string(500)->null(),
            'options_json' => $this->text()->null(),
        ]);
        $this->safeAddForeignKey('fk-custom_form_field_i18n-field', 'custom_form_field_i18n', 'field_id', 'custom_form_field', 'id', 'CASCADE');
        $this->safeCreateIndex('idx-custom_form_field_i18n-field-lang', 'custom_form_field_i18n', ['field_id', 'language'], true);

        $this->safeAddColumn('custom_form_answer', 'panel_member_id', $this->integer()->null());
        $this->safeAddColumn('custom_form_answer', 'wave_id', $this->integer()->null());
        $this->safeAddColumn('custom_form_answer', 'round_id', $this->integer()->null());
        $this->safeAddColumn('custom_form_answer', 'weight', $this->decimal(10, 4)->notNull()->defaultValue(1));
        $this->safeCreateIndex('idx-custom_form_answer-wave', 'custom_form_answer', ['form_id', 'wave_id']);
        $this->safeCreateIndex('idx-custom_form_answer-round', 'custom_form_answer', ['form_id', 'round_id']);
        $this->safeCreateIndex('idx-custom_form_answer-member', 'custom_form_answer', 'panel_member_id');

        $this->safeAddColumn('custom_form_answer_field', 'justification', $this->text()->null()->after('value'));
    }

    public function safeDown()
    {
        $this->safeDropColumn('custom_form_answer_field', 'justification');
        $this->safeDropIndex('idx-custom_form_answer-member', 'custom_form_answer');
        $this->safeDropIndex('idx-custom_form_answer-round', 'custom_form_answer');
        $this->safeDropIndex('idx-custom_form_answer-wave', 'custom_form_answer');
        $this->safeDropColumn('custom_form_answer', 'weight');
        $this->safeDropColumn('custom_form_answer', 'round_id');
        $this->safeDropColumn('custom_form_answer', 'wave_id');
        $this->safeDropColumn('custom_form_answer', 'panel_member_id');
        $this->safeDropTable('custom_form_field_i18n');
        $this->safeDropTable('custom_form_i18n');
        $this->safeDropTable('form_round');
        $this->safeDropTable('form_wave');
        $this->safeDropTable('form_panel_member');
        $this->safeDropTable('form_panel');
    }
}
