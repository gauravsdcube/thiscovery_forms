<?php

use humhub\components\Migration;

/**
 * In-progress answers with resume codes for save-and-continue-later.
 */
class m260811_140000_custom_forms_answer_resume extends Migration
{
    public function safeUp()
    {
        $this->safeAddColumn(
            'custom_form_answer',
            'status',
            $this->tinyInteger()->notNull()->defaultValue(1)->after('form_id')
        );
        $this->safeAddColumn(
            'custom_form_answer',
            'resume_code',
            $this->string(32)->null()->after('status')
        );
        $this->safeAddColumn(
            'custom_form_answer',
            'resume_email',
            $this->string(255)->null()->after('resume_code')
        );
        $this->safeAddColumn(
            'custom_form_answer',
            'current_page',
            $this->integer()->null()->after('resume_email')
        );

        // Existing rows are completed submissions.
        $this->update('custom_form_answer', ['status' => 1]);

        $this->createIndex('idx_cfa_resume_code', 'custom_form_answer', 'resume_code', true);
        $this->createIndex('idx_cfa_form_status', 'custom_form_answer', ['form_id', 'status']);
    }

    public function safeDown()
    {
        $this->dropIndex('idx_cfa_form_status', 'custom_form_answer');
        $this->dropIndex('idx_cfa_resume_code', 'custom_form_answer');
        $this->safeDropColumn('custom_form_answer', 'current_page');
        $this->safeDropColumn('custom_form_answer', 'resume_email');
        $this->safeDropColumn('custom_form_answer', 'resume_code');
        $this->safeDropColumn('custom_form_answer', 'status');
    }
}
