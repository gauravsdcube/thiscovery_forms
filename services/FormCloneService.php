<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\content\models\Content;
use humhub\modules\thiscoveryForms\models\CustomForm;
use Yii;

/**
 * Clone a form (fields only — never answers) for templates and "create from template".
 */
class FormCloneService
{
    /**
     * Copy settings and fields from $source onto a new unsaved/saved $target.
     */
    public function copyInto(CustomForm $source, CustomForm $target, array $overrides = []): bool
    {
        $target->title = $overrides['title'] ?? $source->title;
        $target->description = $source->description;
        $target->thank_you_content = $source->thank_you_content;
        $target->already_submitted_message = $source->already_submitted_message;
        $target->custom_css = $source->custom_css;
        $target->kind = $overrides['kind'] ?? $source->kind;
        $settings = $source->getSettings();
        unset($settings['panel_id'], $settings['enrol_panel_id'], $settings['test_token'], $settings['public_dashboard_token']);
        if (($settings['enrol_panel_mode'] ?? '') === 'existing') {
            $settings['enrol_panel_mode'] = 'none';
        }
        $settings['public_dashboard_enabled'] = false;
        $target->settings_json = json_encode($settings, JSON_UNESCAPED_UNICODE);
        $target->answers_visibility = $source->answers_visibility;
        $target->allow_multiple = $source->allow_multiple;
        $target->allow_anonymous = $source->allow_anonymous;
        $target->allow_edit = $source->allow_edit;
        $target->allow_resume = $source->allow_resume;
        $target->keep_partials = $source->keep_partials;
        $target->enrol_panel_mode = $settings['enrol_panel_mode'] ?? CustomForm::ENROL_PANEL_NONE;
        $target->enrol_panel_id = 0;
        $target->enrol_panel_title = (string)($settings['enrol_panel_title'] ?? '');
        $target->log_panel_activity = !empty($settings['log_panel_activity']) ? 1 : 0;
        $target->invite_email_template_id = (int)($settings['invite_email_template_id'] ?? 0);
        $target->wave_email_template_id = (int)($settings['wave_email_template_id'] ?? 0);
        $target->reminder_email_template_id = (int)($settings['reminder_email_template_id'] ?? 0);
        $target->reminder_days = (int)($settings['reminder_days'] ?? 0);
        $target->completion_email_template_id = (int)($settings['completion_email_template_id'] ?? 0);
        $target->submit_actions = \humhub\modules\thiscoveryForms\services\FormActionService::normalizeList($settings['submit_actions'] ?? []);
        $target->custom_functions = \humhub\modules\thiscoveryForms\services\FormActionService::normalizeFunctions($settings['custom_functions'] ?? []);
        $target->public_dashboard_enabled = 0;
        $target->show_in_menu = $overrides['show_in_menu'] ?? 0;
        $target->status = $overrides['status'] ?? CustomForm::STATUS_DRAFT;
        $target->is_template = $overrides['is_template'] ?? 0;
        $target->source_template_id = $overrides['source_template_id'] ?? null;

        if (!empty($overrides['silent'])) {
            $target->silentContentCreation = true;
        }

        if (!$target->save()) {
            return false;
        }

        $rows = [];
        foreach ($source->fields as $field) {
            $rows[(string)$field->id] = $field->toPostRow();
        }

        if (!$target->saveFieldsFromPost($rows)) {
            return false;
        }

        unset($source->fields, $target->fields);
        (new TranslationService())->copyOnto($source, $target);
        if ($source->isProject()) {
            (new ApprovalWorkflowService())->copyStages($source, $target);
        }
        return true;
    }

    public function saveAsTemplate(CustomForm $source): ?CustomForm
    {
        $target = $source->isGlobal()
            ? new CustomForm()
            : new CustomForm($source->content->getContainer());

        if ($source->isGlobal()) {
            $target->content->visibility = Content::VISIBILITY_PUBLIC;
        }

        $title = $source->title;
        $suffix = Yii::t('ThiscoveryFormsModule.base', '(template)');
        if (!str_contains($title, $suffix)) {
            $title = trim($title . ' ' . $suffix);
        }

        $ok = $this->copyInto($source, $target, [
            'title' => $title,
            'is_template' => 1,
            'status' => CustomForm::STATUS_DRAFT,
            'show_in_menu' => 0,
            'silent' => true,
            'source_template_id' => (int)$source->id,
        ]);

        return $ok ? $target : null;
    }

    public function createFromTemplate(CustomForm $template, $container = null): ?CustomForm
    {
        $target = $container
            ? new CustomForm($container)
            : new CustomForm();

        if ($container === null) {
            $target->content->visibility = Content::VISIBILITY_PUBLIC;
        }

        $title = preg_replace('/\s*\(template\)\s*$/i', '', (string)$template->title) ?: $template->title;

        $ok = $this->copyInto($template, $target, [
            'title' => $title,
            'is_template' => 0,
            'status' => CustomForm::STATUS_DRAFT,
            'show_in_menu' => 0,
            'source_template_id' => (int)$template->id,
        ]);

        return $ok ? $target : null;
    }
}
