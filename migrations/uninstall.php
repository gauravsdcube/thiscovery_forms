<?php

use yii\db\Migration;

class uninstall extends Migration
{
    public function up()
    {
        $this->dropTable('custom_form_quota_i18n');
        $this->dropTable('custom_form_quota_accept');
        $this->dropTable('custom_form_quota_allowhost');
        $this->dropTable('custom_form_quota_audit');
        $this->dropTable('custom_form_quota_reservation');
        $this->dropTable('custom_form_quota_counter');
        $this->dropTable('custom_form_quota');
        $this->dropTable('custom_form_consent_audit');
        $this->dropTable('custom_form_admin_task');
        $this->dropTable('custom_form_consent_withdrawal');
        $this->dropTable('custom_form_consent_requirement');
        $this->dropTable('custom_form_consent_record');
        $this->dropTable('custom_form_consent_i18n');
        $this->dropTable('custom_form_consent_file');
        $this->dropTable('custom_form_consent_item');
        $this->dropTable('custom_form_consent_document');
        $this->dropTable('custom_form_arm_override');
        $this->dropTable('custom_form_arm_assignment');
        $this->dropTable('custom_form_arm_allocation');
        $this->dropTable('custom_form_presentation');
        $this->dropTable('custom_form_rotate_seq');
        $this->dropTable('custom_form_llm_usage');
        $this->dropTable('custom_form_theme');
        $this->dropTable('custom_form_access_token');
        $this->dropTable('custom_form_integrity_audit');
        $this->dropTable('custom_form_integrity_meta');
        $this->dropTable('custom_form_folder_acl');
        $this->dropTable('custom_form_answer_approval');
        $this->dropTable('custom_form_approval_authority');
        $this->dropTable('custom_form_approval_stage');
        $this->dropTable('custom_form_field_i18n');
        $this->dropTable('custom_form_i18n');
        $this->dropTable('form_round');
        $this->dropTable('form_wave');
        $this->dropTable('form_email_send');
        $this->dropTable('form_email_template');
        $this->dropTable('form_panel_activity');
        $this->dropTable('form_panel_member');
        $this->dropTable('form_panel');
        $this->dropTable('form_library_item');
        $this->dropTable('custom_form_answer_field');
        $this->dropTable('custom_form_answer');
        $this->dropTable('custom_form_field');
        $this->dropTable('custom_form');
        $this->dropTable('custom_form_folder');
    }

    public function down()
    {
        echo "uninstall cannot be reverted.\n";
        return false;
    }
}
