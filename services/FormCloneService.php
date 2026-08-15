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
        $target->custom_css = $source->custom_css;
        $target->kind = $overrides['kind'] ?? $source->kind;
        $settings = $source->getSettings();
        unset($settings['panel_id']);
        $target->settings_json = json_encode($settings, JSON_UNESCAPED_UNICODE);
        $target->answers_visibility = $source->answers_visibility;
        $target->allow_multiple = $source->allow_multiple;
        $target->allow_anonymous = $source->allow_anonymous;
        $target->allow_edit = $source->allow_edit;
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
