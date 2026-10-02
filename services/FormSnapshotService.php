<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormApprovalAuthority;
use humhub\modules\thiscoveryForms\models\FormApprovalStage;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormFieldI18n;
use humhub\modules\thiscoveryForms\models\FormI18n;
use humhub\modules\thiscoveryForms\services\integrity\IntegritySettings;
use Yii;

/**
 * Export / import / in-memory hydrate of a form definition for versioning.
 */
class FormSnapshotService
{
    public const SCHEMA_VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public function export(CustomForm $form): array
    {
        $fields = [];
        foreach ($form->getFields()->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])->all() as $field) {
            /** @var FormField $field */
            $row = $field->toPostRow();
            $row['id'] = (int)$field->id;
            $row['sort_order'] = (int)$field->sort_order;
            $fields[] = $row;
        }

        $formI18n = [];
        foreach (FormI18n::find()->where(['form_id' => $form->id])->all() as $row) {
            /** @var FormI18n $row */
            $formI18n[] = [
                'language' => $row->language,
                'title' => $row->title,
                'description' => $row->description,
                'thank_you_content' => $row->thank_you_content,
            ];
        }

        $fieldI18n = [];
        $fieldIds = array_column($fields, 'id');
        if ($fieldIds) {
            foreach (FormFieldI18n::find()->where(['field_id' => $fieldIds])->all() as $row) {
                /** @var FormFieldI18n $row */
                $fieldI18n[] = [
                    'field_id' => (int)$row->field_id,
                    'language' => $row->language,
                    'label' => $row->label,
                    'help_text' => $row->help_text,
                    'options_json' => $row->options_json,
                ];
            }
        }

        $stages = [];
        foreach (FormApprovalStage::find()->where(['form_id' => $form->id])->orderBy(['sort_order' => SORT_ASC])->all() as $stage) {
            /** @var FormApprovalStage $stage */
            $authorities = [];
            foreach ($stage->authorities as $auth) {
                $authorities[] = [
                    'type' => $auth->type,
                    'user_id' => $auth->user_id,
                    'group_id' => $auth->group_id,
                ];
            }
            $stages[] = [
                'name' => $stage->name,
                'sort_order' => (int)$stage->sort_order,
                'require_all' => (int)$stage->require_all,
                'authorities' => $authorities,
            ];
        }

        return [
            'schema' => self::SCHEMA_VERSION,
            'meta' => [
                'title' => $form->title,
                'description' => $form->description,
                'thank_you_content' => $form->thank_you_content,
                'already_submitted_message' => $form->already_submitted_message,
                'custom_css' => $form->custom_css,
                'kind' => $form->kind,
                'status' => (int)$form->status,
                'allow_multiple' => (int)$form->allow_multiple,
                'allow_anonymous' => (int)$form->allow_anonymous,
                'allow_edit' => (int)$form->allow_edit,
                'allow_resume' => (int)$form->allow_resume,
                'show_in_menu' => (int)$form->show_in_menu,
                'answers_visibility' => $form->answers_visibility,
                'settings_json' => $form->settings_json,
            ],
            'fields' => $fields,
            'translations' => [
                'form' => $formI18n,
                'fields' => $fieldI18n,
            ],
            'integrity' => IntegritySettings::overlayForForm($form),
            'approval_stages' => $stages,
        ];
    }

    /**
     * Persist snapshot onto the working draft form (restore / backfill).
     */
    public function import(CustomForm $form, array $snapshot, bool $preserveStatus = true): bool
    {
        // Restore is all or nothing: settings, questions, translations and stages (V3-37).
        $tx = \Yii::$app->db->beginTransaction();
        try {
            $ok = $this->importUnsafe($form, $snapshot, $preserveStatus);
        } catch (\Throwable $e) {
            $tx->rollBack();
            unset($form->fields);
            throw $e;
        }
        if ($ok) {
            $tx->commit();
            return true;
        }
        $tx->rollBack();
        $form->refresh();
        unset($form->fields);
        return false;
    }

    private function importUnsafe(CustomForm $form, array $snapshot, bool $preserveStatus): bool
    {
        $meta = is_array($snapshot['meta'] ?? null) ? $snapshot['meta'] : [];
        $currentStatus = (int)$form->status;

        $form->title = (string)($meta['title'] ?? $form->title);
        $form->description = $meta['description'] ?? $form->description;
        $form->thank_you_content = $meta['thank_you_content'] ?? $form->thank_you_content;
        $form->already_submitted_message = $meta['already_submitted_message'] ?? $form->already_submitted_message;
        $form->custom_css = str_replace('<', '', (string)($meta['custom_css'] ?? $form->custom_css));
        if (isset($meta['kind'])) {
            $form->kind = (string)$meta['kind'];
        }
        $form->allow_multiple = (int)($meta['allow_multiple'] ?? $form->allow_multiple);
        $form->allow_anonymous = (int)($meta['allow_anonymous'] ?? $form->allow_anonymous);
        $form->allow_edit = (int)($meta['allow_edit'] ?? $form->allow_edit);
        $form->allow_resume = (int)($meta['allow_resume'] ?? $form->allow_resume);
        $form->show_in_menu = (int)($meta['show_in_menu'] ?? $form->show_in_menu);
        if (isset($meta['answers_visibility'])) {
            $form->answers_visibility = (string)$meta['answers_visibility'];
        }
        if (array_key_exists('settings_json', $meta)) {
            $form->settings_json = $meta['settings_json'];
        }
        if (!$preserveStatus && isset($meta['status'])) {
            $form->status = (int)$meta['status'];
        } else {
            $form->status = $currentStatus;
        }

        if (!$form->save()) {
            return false;
        }

        $fieldRows = [];
        foreach (($snapshot['fields'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = isset($row['id']) ? (string)$row['id'] : ('new_' . count($fieldRows));
            $fieldRows[$key] = $row;
        }
        if (!$form->saveFieldsFromPost($fieldRows)) {
            return false;
        }

        unset($form->fields);
        $this->importTranslations($form, is_array($snapshot['translations'] ?? null) ? $snapshot['translations'] : []);
        $this->importApprovalStages($form, is_array($snapshot['approval_stages'] ?? null) ? $snapshot['approval_stages'] : []);

        if (isset($snapshot['integrity']) && is_array($snapshot['integrity'])) {
            IntegritySettings::saveForm($form, $snapshot['integrity']);
        }

        return true;
    }

    /**
     * Apply snapshot onto an in-memory form for fill/preview without writing the draft.
     */
    public function hydrateInMemory(CustomForm $form, array $snapshot): void
    {
        $meta = is_array($snapshot['meta'] ?? null) ? $snapshot['meta'] : [];
        foreach ([
            'title', 'description', 'thank_you_content', 'already_submitted_message', 'custom_css',
            'kind', 'answers_visibility', 'settings_json',
        ] as $attr) {
            if (array_key_exists($attr, $meta)) {
                $form->$attr = $meta[$attr];
            }
        }
        foreach (['allow_multiple', 'allow_anonymous', 'allow_edit', 'allow_resume', 'show_in_menu'] as $attr) {
            if (array_key_exists($attr, $meta)) {
                $form->$attr = (int)$meta[$attr];
            }
        }

        $fields = $this->fieldsFromRows($form, is_array($snapshot['fields'] ?? null) ? $snapshot['fields'] : []);
        $this->bindHydratedFieldsToLiveRows($form, $fields);
        unset($form->fields);
        $form->populateRelation('fields', $fields);
        $form->syncSettingsAttributes();
    }

    /**
     * Unsaved questions built from post rows, in posted order. The studio re-renders these
     * when a save is refused, so the author keeps their changes (V3-37).
     *
     * @return FormField[]
     */
    public function fieldsFromRows(CustomForm $form, array $rows): array
    {
        $fields = [];
        $i = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || trim((string)($row['type'] ?? '')) === '') {
                continue;
            }
            $field = FormField::fromPostRow($row);
            $field->form_id = (int)$form->id;
            $rawId = $row['id'] ?? '';
            $field->id = ($rawId !== '' && (int)$rawId > 0) ? (int)$rawId : (1000000 + $i);
            $field->sort_order = (int)($row['sort_order'] ?? ($i * 10));
            $formula = trim((string)($row['logic_formula'] ?? ''));
            $rules = is_array($row['logic_rules'] ?? null) ? $row['logic_rules'] : [];
            if (LogicEngine::containsLegacy($rules) || trim((string)($row['condition_field'] ?? '')) !== '') {
                throw new \InvalidArgumentException(LogicEngine::legacyMessage());
            }
            if ($formula !== '') {
                $field->setLogic(LogicEngine::fromFormula(
                    $formula,
                    (string)($row['logic_action'] ?? 'show'),
                    (string)($row['logic_goto'] ?? '')
                ));
            }
            $fields[] = $field;
            $i++;
        }
        usort($fields, static fn(FormField $a, FormField $b): int => (int)$a->sort_order <=> (int)$b->sort_order);
        return $fields;
    }

    /**
     * Snapshot field IDs can be stale after import/replace. Answers still FK to
     * custom_form_field, so map each hydrated field onto a live row, including
     * a soft-deleted row with the same id so a restored edition undeletes it
     * instead of inserting a new question.
     *
     * @param FormField[] $fields
     */
    protected function bindHydratedFieldsToLiveRows(CustomForm $form, array $fields): void
    {
        if (!$form->id || $fields === []) {
            return;
        }

        /** @var FormField[] $live */
        $live = FormField::find()->where(['form_id' => (int)$form->id])->all();
        $byId = [];
        $byVar = [];
        foreach ($live as $row) {
            $byId[(int)$row->id] = $row;
            $var = strtolower(trim((string)$row->variable));
            if ($var !== '') {
                $byVar[$var][] = $row;
            }
        }

        $used = [];
        $pick = static function (array $candidates) use (&$used): ?FormField {
            foreach ($candidates as $candidate) {
                if (!isset($used[(int)$candidate->id])) {
                    return $candidate;
                }
            }
            return null;
        };

        $idMap = [];
        foreach ($fields as $field) {
            $oldId = (int)$field->id;
            $resolved = ($oldId && isset($byId[$oldId]) && !isset($used[$oldId])) ? $byId[$oldId] : null;
            $var = strtolower(trim((string)$field->variable));
            if (!$resolved && $var !== '' && !empty($byVar[$var])) {
                $resolved = $pick($byVar[$var]);
            }
            if (!$resolved && $var === '') {
                // No variable: bind one live row of the same label and type, and only once (DAT-4).
                $label = strtolower(trim((string)$field->label));
                $candidates = [];
                foreach ($live as $row) {
                    if (strtolower(trim((string)$row->label)) === $label && (string)$row->type === (string)$field->type) {
                        $candidates[] = $row;
                    }
                }
                $resolved = $pick($candidates);
            }
            // No label-and-type fallback: two "Please specify" boxes would be bound to one row
            // and their answers merged. Variable names are unique per form (DAT-10), so a
            // variable match is safe; anything else is left unbound (DAT-4).
            if ($resolved) {
                $newId = (int)$resolved->id;
                if ($oldId) {
                    $idMap[$oldId] = $newId;
                }
                $field->id = $newId;
                $used[$newId] = true;
                continue;
            }
            Yii::warning(
                'Thiscovery Forms snapshot field #' . $oldId
                . ' has no live custom_form_field row; answers for it will be skipped.',
                'thiscovery-forms'
            );
        }

        if ($idMap === []) {
            return;
        }

        $mapKey = static function (string $key) use ($idMap): string {
            if ($key !== '' && ctype_digit($key) && isset($idMap[(int)$key])) {
                return (string)$idMap[(int)$key];
            }
            return $key;
        };

        $mapRules = static function (array $rules) use (&$mapRules, $mapKey): array {
            foreach ($rules as $i => $rule) {
                if (!is_array($rule)) {
                    continue;
                }
                if (!empty($rule['all']) && is_array($rule['all'])) {
                    $rules[$i]['all'] = $mapRules($rule['all']);
                }
                if (!empty($rule['any']) && is_array($rule['any'])) {
                    $rules[$i]['any'] = $mapRules($rule['any']);
                }
                if (isset($rule['fieldKey'])) {
                    $rules[$i]['fieldKey'] = $mapKey((string)$rule['fieldKey']);
                }
            }
            return $rules;
        };

        foreach ($fields as $field) {
            $logic = $field->getLogic();
            $mapped = $mapRules($logic['rules'] ?? []);
            if ($mapped !== ($logic['rules'] ?? [])) {
                $logic['rules'] = $mapped;
                $field->setLogic($logic);
            }

            if ($field->type === FormField::TYPE_PAGE_BREAK) {
                $cfg = $field->getPageBreakConfig();
                $branchChanged = false;
                foreach ($cfg['branches'] as $i => $branch) {
                    $next = $mapKey((string)($branch['fieldKey'] ?? ''));
                    if ($next !== (string)($branch['fieldKey'] ?? '')) {
                        $cfg['branches'][$i]['fieldKey'] = $next;
                        $branchChanged = true;
                    }
                }
                if ($branchChanged) {
                    $field->setPageBreakConfig($cfg);
                }
            }

            $carry = $field->getCarryForward();
            $from = (string)($carry['from'] ?? '');
            $mappedFrom = $mapKey($from);
            if ($mappedFrom !== $from) {
                $field->setCarryForward($mappedFrom, (string)($carry['mode'] ?? FormField::CARRY_SELECTED));
            }
            FieldRefRewriter::rewriteField($field, $idMap);
        }
        FieldRefRewriter::rewriteForm($form, $idMap);
    }

    protected function importTranslations(CustomForm $form, array $translations): void
    {
        FormI18n::deleteAll(['form_id' => $form->id]);
        foreach (($translations['form'] ?? []) as $row) {
            if (!is_array($row) || trim((string)($row['language'] ?? '')) === '') {
                continue;
            }
            $rec = new FormI18n();
            $rec->form_id = (int)$form->id;
            $rec->language = (string)$row['language'];
            $rec->title = $row['title'] ?? null;
            $rec->description = $row['description'] ?? null;
            $rec->thank_you_content = $row['thank_you_content'] ?? null;
            $rec->save(false);
        }

        $fieldIds = [];
        foreach ($form->getFields()->all() as $field) {
            $fieldIds[] = (int)$field->id;
        }
        if ($fieldIds) {
            FormFieldI18n::deleteAll(['field_id' => $fieldIds]);
        }
        $idSet = array_flip($fieldIds);
        foreach (($translations['fields'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $fid = (int)($row['field_id'] ?? 0);
            if (!$fid || !isset($idSet[$fid])) {
                continue;
            }
            $rec = new FormFieldI18n();
            $rec->field_id = $fid;
            $rec->language = (string)($row['language'] ?? '');
            $rec->label = $row['label'] ?? null;
            $rec->help_text = $row['help_text'] ?? null;
            $rec->options_json = $row['options_json'] ?? null;
            if ($rec->language === '') {
                continue;
            }
            $rec->save(false);
        }
    }

    protected function importApprovalStages(CustomForm $form, array $stages): void
    {
        foreach (FormApprovalStage::find()->where(['form_id' => $form->id])->all() as $stage) {
            FormApprovalAuthority::deleteAll(['stage_id' => $stage->id]);
            $stage->delete();
        }
        foreach ($stages as $row) {
            if (!is_array($row)) {
                continue;
            }
            $stage = new FormApprovalStage();
            $stage->form_id = (int)$form->id;
            $stage->name = (string)($row['name'] ?? 'Stage');
            $stage->sort_order = (int)($row['sort_order'] ?? 0);
            $stage->require_all = (int)($row['require_all'] ?? 0);
            $stage->save(false);
            foreach (($row['authorities'] ?? []) as $authRow) {
                if (!is_array($authRow)) {
                    continue;
                }
                $auth = new FormApprovalAuthority();
                $auth->stage_id = (int)$stage->id;
                $auth->type = (string)($authRow['type'] ?? '');
                $auth->user_id = $authRow['user_id'] ?? null;
                $auth->group_id = $authRow['group_id'] ?? null;
                $auth->save(false);
            }
        }
    }
}
