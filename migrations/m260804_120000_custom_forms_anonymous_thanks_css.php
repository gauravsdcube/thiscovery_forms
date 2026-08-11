<?php

use humhub\components\Migration;
use yii\db\Query;

class m260804_120000_custom_forms_anonymous_thanks_css extends Migration
{
    public function safeUp()
    {
        $this->safeAddColumn('custom_form', 'allow_anonymous', $this->boolean()->notNull()->defaultValue(0)->after('allow_multiple'));
        $this->safeAddColumn('custom_form', 'thank_you_content', $this->text()->null()->after('description'));
        $this->safeAddColumn('custom_form', 'custom_css', $this->text()->null()->after('thank_you_content'));

        // Allow anonymous answers without a user FK.
        try {
            $this->dropForeignKey('fk_cfa_created_by', 'custom_form_answer');
        } catch (\Throwable $e) {
        }
        try {
            $this->dropForeignKey('fk_cfa_updated_by', 'custom_form_answer');
        } catch (\Throwable $e) {
        }

        $this->alterColumn('custom_form_answer', 'created_by', $this->integer()->null());
        $this->alterColumn('custom_form_answer', 'updated_by', $this->integer()->null());

        $this->safeAddForeignKey('fk_cfa_created_by', 'custom_form_answer', 'created_by', 'user', 'id', 'SET NULL', 'CASCADE');
        $this->safeAddForeignKey('fk_cfa_updated_by', 'custom_form_answer', 'updated_by', 'user', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown()
    {
        // Re-assign orphaned answers to first admin-like user if needed before making NOT NULL.
        $fallback = (new Query())->from('user')->select('id')->orderBy(['id' => SORT_ASC])->scalar();
        if ($fallback) {
            $this->update('custom_form_answer', ['created_by' => $fallback], ['created_by' => null]);
            $this->update('custom_form_answer', ['updated_by' => $fallback], ['updated_by' => null]);
        }

        try {
            $this->dropForeignKey('fk_cfa_created_by', 'custom_form_answer');
        } catch (\Throwable $e) {
        }
        try {
            $this->dropForeignKey('fk_cfa_updated_by', 'custom_form_answer');
        } catch (\Throwable $e) {
        }

        $this->alterColumn('custom_form_answer', 'created_by', $this->integer()->notNull());
        $this->alterColumn('custom_form_answer', 'updated_by', $this->integer()->notNull());
        $this->safeAddForeignKey('fk_cfa_created_by', 'custom_form_answer', 'created_by', 'user', 'id', 'CASCADE');
        $this->safeAddForeignKey('fk_cfa_updated_by', 'custom_form_answer', 'updated_by', 'user', 'id', 'CASCADE');

        $this->safeDropColumn('custom_form', 'custom_css');
        $this->safeDropColumn('custom_form', 'thank_you_content');
        $this->safeDropColumn('custom_form', 'allow_anonymous');
    }
}
