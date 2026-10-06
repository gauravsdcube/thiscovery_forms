<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use Yii;

/**
 * Import/export question definitions (not answers).
 */
class QuestionImportExportService
{
    public const FORMAT = 'thiscovery-forms-questions';
    public const VERSION = 1;

    /** Set when a JSON import copied the form settings onto the target. */
    public bool $settingsApplied = false;

    /**
     * Links, secrets, and records that exist only on this site. Secure send is never transferred.
     * @var string[]
     */
    private const SETTINGS_OMIT = [
        'secure_send_minutes',
        'test_token',
        'public_dashboard_token',
        'panel_id',
        'enrol_panel_id',
        'theme_id',
        'invite_email_template_id',
        'wave_email_template_id',
        'reminder_email_template_id',
        'completion_email_template_id',
        'consent_client_salt',
    ];

    /**
     * Spreadsheet columns. Import accepts any subset; extra unknown columns are ignored.
     * @var string[]
     */
    public const CSV_COLUMNS = [
        'type',
        'key',
        'label',
        'variable',
        'internal_label',
        'help',
        'required',
        'options',
        'page_key',
        'page_title',
        'branches',
        'page_otherwise',
        'validation',
        'rating_min',
        'rating_max',
        'rating_step',
        'rating_low_label',
        'rating_high_label',
        'rating_display',
        'grid_rows',
        'grid_columns',
        'grid_mobile_layout',
        'items',
        'maxdiff_set_size',
        'maxdiff_set_count',
        'exclusive_option',
        'other_specify',
        'other_specify_required',
        'max_select',
        'min_select',
        'min_select_all',
        'randomize',
        'rich_content',
        'html_content',
        'html_collect',
        'html_variable',
        'html_instructions',
        'html_required',
        'drilldown_tree',
        'image_url',
        'image_mode',
        'image_multi',
        'image_regions',
        'carry_from',
        'carry_mode',
        'prefill_profile',
        'hidden',
        'pii',
        'default_value',
        'meta_key',
        'logic_action',
        'logic_combinator',
        'logic_goto',
        'logic_formula',
        'logic_rules',
        'number_min',
        'number_max',
        // Loops, group and block randomisation, consent options (V3-46).
        'block_key',
        'randomise_enabled',
        'randomise_method',
        'randomise_show',
        'randomise_pin_first',
        'randomise_pin_last',
        'loop_enabled',
        'loop_source',
        'loop_field_key',
        'loop_label_field',
        'loop_max',
        'loop_min',
        'loop_items',
        'loop_randomise',
        'loop_show',
        'consent_must_read',
        'consent_signature',
        'consent_witness',
    ];

    public function exportJson(CustomForm $form): array
    {
        $aliases = $this->portableAliasMap($form);
        $fields = [];
        foreach ($form->fields as $field) {
            $row = $field->toExportArray();
            $row['key'] = $aliases[(string)$field->id] ?? ('f' . $field->id);
            $row['carry_from'] = $this->remapAlias((string)($row['carry_from'] ?? ''), $aliases);
            $row['logic_rules'] = $this->remapRuleFieldKeys($row['logic_rules'] ?? [], $aliases);
            if (isset($row['logic']) && is_array($row['logic'])) {
                $row['logic']['rules'] = $this->remapRuleFieldKeys($row['logic']['rules'] ?? [], $aliases);
            }
            $row['branches'] = $this->remapRuleFieldKeys($row['branches'] ?? [], $aliases);
            $row['loop_field_key'] = $this->remapAlias((string)($row['loop_field_key'] ?? ''), $aliases);
            $row['loop_label_field'] = $this->remapAlias((string)($row['loop_label_field'] ?? ''), $aliases);
            $fields[] = $row;
        }

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'kind' => $form->kind,
            'title' => $form->title,
            'fields' => $fields,
            'settings' => $this->exportSettings($form, $aliases),
        ];
    }

    public function exportJsonString(CustomForm $form): string
    {
        return json_encode($this->exportJson($form), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function exportCsv(CustomForm $form): string
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, \humhub\modules\thiscoveryForms\helpers\CsvCell::row(self::CSV_COLUMNS));
        $aliases = $this->portableAliasMap($form);
        foreach ($form->fields as $field) {
            fputcsv($fh, \humhub\modules\thiscoveryForms\helpers\CsvCell::row($this->csvRowFromField($field, $aliases)));
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        return $csv === false ? '' : $csv;
    }

    /**
     * @return string|null error message
     */
    public function importJson(CustomForm $form, string $json, bool $replace = false): ?string
    {
        $this->settingsApplied = false;
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return Yii::t('ThiscoveryFormsModule.base', 'The file is not valid JSON.');
        }

        $fields = $decoded['fields'] ?? null;
        if (!is_array($fields)) {
            return Yii::t('ThiscoveryFormsModule.base', 'JSON is missing a fields list.');
        }

        $settings = isset($decoded['settings']) && is_array($decoded['settings']) ? $decoded['settings'] : null;
        return $this->appendFieldPayloads($form, $fields, $replace, $settings);
    }

    /**
     * @return string|null error message
     */
    public function importCsv(CustomForm $form, string $csv, bool $replace = false): ?string
    {
        if (str_starts_with($csv, "\xEF\xBB\xBF")) {
            $csv = substr($csv, 3);
        }

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $csv);
        rewind($fh);

        $payloads = [];
        $header = null;
        while (($row = fgetcsv($fh)) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }
            $row = array_map(static fn($cell) => \humhub\modules\thiscoveryForms\helpers\CsvCell::restore((string)$cell), $row);
            $hasValue = false;
            foreach ($row as $cell) {
                if (trim((string)$cell) !== '') {
                    $hasValue = true;
                    break;
                }
            }
            if (!$hasValue) {
                continue;
            }

            $first = trim((string)($row[0] ?? ''));
            if (str_starts_with($first, '#')) {
                continue;
            }

            if ($header === null) {
                $header = array_map([$this, 'normalizeHeader'], $row);
                if (in_array('type', $header, true) && in_array('label', $header, true)) {
                    continue;
                }
                $header = ['type', 'label', 'help', 'required', 'options'];
            }

            $map = [];
            foreach ($header as $idx => $name) {
                if ($name === '') {
                    continue;
                }
                $map[$name] = $row[$idx] ?? '';
            }

            $type = $this->normalizeType((string)($map['type'] ?? ''));
            $label = trim((string)($map['label'] ?? ''));
            if ($type === '') {
                continue;
            }
            if (!isset(FormField::getTypeLabels()[$type])) {
                continue;
            }
            $metaKey = trim((string)($map['meta_key'] ?? ''));
            if ($type === FormField::TYPE_RESPONDENT_META && $metaKey === '') {
                $metaKey = $this->metaKeyFromAlias((string)($map['type'] ?? ''));
            }

            $payload = [
                'type' => $type,
                'key' => (string)($map['key'] ?? ''),
                'label' => $label,
                'variable' => trim((string)($map['variable'] ?? '')),
                'internal_label' => trim((string)($map['internal_label'] ?? '')),
                'help_text' => (string)($map['help'] ?? $map['help_text'] ?? ''),
                'required' => $this->cellBool($map['required'] ?? ''),
                'options' => (string)($map['options'] ?? ''),
                'page_key' => (string)($map['page_key'] ?? ''),
                'page_title' => (string)($map['page_title'] ?? ''),
                'branches' => $this->decodeJsonCell($map['branches'] ?? ''),
                'page_otherwise' => (string)($map['page_otherwise'] ?? ''),
                'validation' => $this->decodeJsonCell($map['validation'] ?? ''),
                'rating_min' => $map['rating_min'] ?? 1,
                'rating_max' => $map['rating_max'] ?? 5,
                'rating_step' => $map['rating_step'] ?? 1,
                'rating_low_label' => (string)($map['rating_low_label'] ?? ''),
                'rating_high_label' => (string)($map['rating_high_label'] ?? ''),
                'rating_display' => (string)($map['rating_display'] ?? FormField::RATING_DISPLAY_PILLS),
                'grid_rows' => (string)($map['grid_rows'] ?? ''),
                'grid_columns' => (string)($map['grid_columns'] ?? ''),
                'grid_mobile_layout' => (string)($map['grid_mobile_layout'] ?? 'scroll'),
                'items' => (string)($map['items'] ?? ''),
                'maxdiff_set_size' => $map['maxdiff_set_size'] ?? 4,
                'maxdiff_set_count' => $map['maxdiff_set_count'] ?? '',
                'exclusive_option' => (string)($map['exclusive_option'] ?? ''),
                'max_select' => $map['max_select'] ?? '',
                'min_select' => $map['min_select'] ?? '',
                'min_select_all' => $this->cellBool($map['min_select_all'] ?? ''),
                'randomize' => $this->cellBool($map['randomize'] ?? ''),
                'rich_content' => (string)($map['rich_content'] ?? ''),
                'html_content' => (string)($map['html_content'] ?? ''),
                'html_collect' => $this->cellBool($map['html_collect'] ?? ''),
                'html_variable' => (string)($map['html_variable'] ?? 'value'),
                'html_instructions' => (string)($map['html_instructions'] ?? ''),
                'html_required' => $this->cellBool($map['html_required'] ?? ''),
                'drilldown_tree' => (string)($map['drilldown_tree'] ?? ''),
                'image_url' => (string)($map['image_url'] ?? ''),
                'image_mode' => (string)($map['image_mode'] ?? 'select'),
                'image_multi' => $this->cellBool($map['image_multi'] ?? ''),
                'image_regions' => $this->decodeJsonCell($map['image_regions'] ?? ''),
                'carry_from' => (string)($map['carry_from'] ?? ''),
                'carry_mode' => (string)($map['carry_mode'] ?? FormField::CARRY_SELECTED),
                'prefill_profile' => (string)($map['prefill_profile'] ?? ''),
                'hidden' => $this->cellBool($map['hidden'] ?? ''),
                'default_value' => (string)($map['default_value'] ?? ''),
                'meta_key' => $metaKey,
                'logic_action' => (string)($map['logic_action'] ?? 'show'),
                'logic_combinator' => (string)($map['logic_combinator'] ?? 'and'),
                'logic_goto' => (string)($map['logic_goto'] ?? ''),
                'logic_formula' => (string)($map['logic_formula'] ?? ''),
                'logic_rules' => $this->decodeJsonCell($map['logic_rules'] ?? ''),
                'other_specify' => $map['other_specify'] ?? '1',
                'other_specify_required' => $map['other_specify_required'] ?? '1',
                'number_min' => $map['number_min'] ?? '',
                'number_max' => $map['number_max'] ?? '',
            ];
            if (!is_array($payload['branches'])) {
                $payload['branches'] = [];
            }
            if (!is_array($payload['logic_rules'])) {
                $payload['logic_rules'] = [];
            }
            if (array_key_exists('pii', $map)) {
                $payload['pii'] = $this->cellBool($map['pii']);
            }
            foreach (FormField::STRUCTURE_KEYS as $structureKey) {
                if (array_key_exists($structureKey, $map) && trim((string)$map[$structureKey]) !== '') {
                    $payload[$structureKey] = (string)$map[$structureKey];
                }
            }
            $payloads[] = $payload;
        }
        fclose($fh);

        if (!$payloads) {
            return Yii::t('ThiscoveryFormsModule.base', 'No questions found in the CSV file.');
        }

        return $this->appendFieldPayloads($form, $payloads, $replace);
    }

    /**
     * Bundled example file for the Share tab download.
     */
    public static function sampleFilePath(string $format): ?string
    {
        $ext = strtolower($format) === 'csv' ? 'csv' : 'json';
        $path = dirname(__DIR__) . '/resources/samples/questions-sample.' . $ext;
        return is_file($path) ? $path : null;
    }

    /**
     * @param array $payloads export-style or post-row field arrays
     * @param array<string,mixed>|null $settings form settings from a JSON export; CSV passes none
     */
    public function appendFieldPayloads(CustomForm $form, array $payloads, bool $replace = false, ?array $settings = null): ?string
    {
        $hadQuestions = $form->fields !== [];
        $existing = [];
        // A replace updates the live question that already has each variable name, instead of
        // removing it and adding another with the same name. The removed row would still hold
        // the name for this save, and the new question would be refused.
        $reuseByVariable = [];
        if (!$replace) {
            foreach ($form->fields as $field) {
                $existing[(string)$field->id] = $field->toPostRow();
                $existing[(string)$field->id]['id'] = $field->id;
            }
        } else {
            foreach ($form->fields as $field) {
                $var = strtolower(trim((string)$field->variable));
                if ($var !== '' && !isset($reuseByVariable[$var])) {
                    $reuseByVariable[$var] = (int)$field->id;
                }
            }
        }

        $next = count($existing);
        $imported = 0;
        foreach ($payloads as $payload) {
            if (!is_array($payload)) {
                continue;
            }
            if (isset($payload['type'])) {
                $payload['type'] = $this->normalizeType((string)$payload['type']);
            }
            if (($payload['type'] ?? '') === FormField::TYPE_MAP
                && !\humhub\modules\thiscoveryForms\helpers\MappingAvailability::isEnabled()) {
                continue;
            }
            $row = FormField::exportToPostRow($payload);
            if ($row === null) {
                continue;
            }
            $row = $this->sanitizeImportRow($row, $payload);
            $importKey = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($payload['key'] ?? '')) ?? '';
            if ($importKey !== '') {
                $row['import_key'] = $importKey;
            }
            $var = strtolower(trim((string)($row['variable'] ?? '')));
            if ($var !== '' && isset($reuseByVariable[$var])) {
                $row['id'] = $reuseByVariable[$var];
                unset($reuseByVariable[$var]);
            }
            $existing['imp' . $next] = $row;
            $next++;
            $imported++;
        }
        $existing = $this->renameCollidingImportVariables($existing);

        if ($imported === 0) {
            return Yii::t('ThiscoveryFormsModule.base', 'No questions found in the import file.');
        }

        if (!$form->saveFieldsFromPost($existing)) {
            // Nothing was imported (V3-37); say why.
            return $form->designRefusalMessage();
        }
        if ($settings !== null && ($replace || !$hadQuestions)) {
            $this->applyImportedSettings($form, $settings);
        }
        // An import is a save: record the revision, or "publish latest revision" would
        // publish the definition from before it (DAT-19).
        (new FormVersionService())->recordSave($form);

        return null;
    }

    /**
     * An imported question keeps its own variable when the form already uses that name.
     *
     * @param array<string,array<string,mixed>> $rows
     * @return array<string,array<string,mixed>>
     */
    private function renameCollidingImportVariables(array $rows): array
    {
        $taken = [];
        foreach ($rows as $key => $row) {
            if (!is_array($row) || str_starts_with((string)$key, 'imp')) {
                continue;
            }
            $var = strtolower(trim((string)($row['variable'] ?? '')));
            if ($var !== '') {
                $taken[$var] = true;
            }
        }
        $firstRename = [];
        foreach ($rows as $key => $row) {
            if (!is_array($row) || !str_starts_with((string)$key, 'imp')) {
                continue;
            }
            $var = trim((string)($row['variable'] ?? ''));
            if ($var === '') {
                continue;
            }
            $low = strtolower($var);
            if (!isset($taken[$low])) {
                $taken[$low] = true;
                continue;
            }
            $n = 2;
            do {
                $next = $var . '_' . $n;
                $n++;
            } while (isset($taken[strtolower($next)]));
            $rows[$key]['variable'] = $next;
            $taken[strtolower($next)] = true;
            $rows[$key] = $this->rewriteVariableTokens($rows[$key], $var, $next);
            if (!isset($firstRename[$var])) {
                $firstRename[$var] = $next;
            }
        }
        if ($firstRename === []) {
            return $rows;
        }
        foreach ($rows as $key => $row) {
            if (!is_array($row) || !str_starts_with((string)$key, 'imp')) {
                continue;
            }
            foreach ($firstRename as $old => $next) {
                $rows[$key] = $this->rewriteVariableTokens($rows[$key], $old, $next);
            }
        }
        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function rewriteVariableTokens(array $row, string $from, string $to): array
    {
        if ($from === '' || $from === $to) {
            return $row;
        }
        // Every reference form ([x], [x.row], [x[*]], {{answer:x[..]}}) in every formula and
        // piped text, not just the rule formula (V3-52).
        $map = [$from => $to];
        $rename = static fn($text) => is_string($text) ? \humhub\modules\thiscoveryForms\services\formula\FormulaRefs::renameText($text, $map) : $text;
        foreach (['logic_formula', 'formula', 'rich_content', 'html_content', 'label', 'help_text'] as $column) {
            if (isset($row[$column])) {
                $row[$column] = $rename($row[$column]);
            }
        }
        if (isset($row['branches']) && is_array($row['branches'])) {
            foreach ($row['branches'] as $i => $branch) {
                if (is_array($branch)) {
                    foreach (['formula', 'text'] as $key) {
                        if (isset($branch[$key])) {
                            $row['branches'][$i][$key] = $rename($branch[$key]);
                        }
                    }
                }
            }
        }
        if (isset($row['validation']['check']) && is_string($row['validation']['check'])) {
            $row['validation']['check'] = $rename($row['validation']['check']);
        }
        if (isset($row['actions']) && is_array($row['actions'])) {
            foreach ($row['actions'] as $i => $action) {
                foreach (['value', 'condition'] as $key) {
                    if (is_array($action) && isset($action[$key])) {
                        $row['actions'][$i][$key] = $rename($action[$key]);
                    }
                }
            }
        }
        if (isset($row['carry_from']) && (string)$row['carry_from'] === $from) {
            $row['carry_from'] = $to;
        }
        foreach (['loop_field_key', 'loop_label_field'] as $column) {
            if (isset($row[$column]) && strcasecmp((string)$row[$column], $from) === 0) {
                $row[$column] = $to;
            }
        }
        return $row;
    }

    /**
     * Clamp / reshape payloads so saveFieldsFromPost does not fail on LLM import quirks.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sanitizeImportRow(array $row, array $payload): array
    {
        $row['label'] = mb_substr(trim((string)($row['label'] ?? '')), 0, 255);
        $row['internal_label'] = mb_substr(trim((string)($row['internal_label'] ?? '')), 0, 255);
        $help = (string)($row['help_text'] ?? '');
        $type = (string)($row['type'] ?? '');

        if ($type === FormField::TYPE_RICH_TEXT) {
            $rich = trim((string)($row['rich_content'] ?? ''));
            $labelHtml = '<p>' . htmlspecialchars($row['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
            if ($help !== '' && ($rich === '' || $rich === $labelHtml || mb_strlen($help) > 500)) {
                $paras = preg_split("/\n\s*\n/u", $help) ?: [$help];
                $body = '';
                foreach ($paras as $para) {
                    $para = trim((string)$para);
                    if ($para === '') {
                        continue;
                    }
                    $body .= '<p>' . nl2br(htmlspecialchars($para, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';
                }
                if ($rich === '' || $rich === $labelHtml) {
                    $row['rich_content'] = ($row['label'] !== ''
                            ? '<p><strong>' . htmlspecialchars($row['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong></p>'
                            : '') . $body;
                } else {
                    $row['rich_content'] = $rich . $body;
                }
                $row['help_text'] = '';
                $help = '';
            }
        }

        if (($type === FormField::TYPE_GRID_SINGLE || $type === FormField::TYPE_GRID_MULTI)
            && trim((string)($row['grid_rows'] ?? '')) === ''
            && trim((string)($row['options'] ?? '')) !== '') {
            $row['grid_rows'] = (string)$row['options'];
        }
        if (($type === FormField::TYPE_GRID_SINGLE || $type === FormField::TYPE_GRID_MULTI)
            && trim((string)($row['grid_columns'] ?? '')) === '') {
            $row['grid_columns'] = "Not at all\nA little\nSomewhat\nQuite a bit\nVery much\nN/A";
        }

        if (mb_strlen($help) > 500) {
            $row['help_text'] = mb_substr($help, 0, 500);
        }

        return $row;
    }

    /**
     * @param array<string,string> $aliases field id => csv key
     * @return list<string>
     */
    private function csvRowFromField(FormField $field, array $aliases): array
    {
        $row = $field->toPostRow();
        $row['key'] = $aliases[(string)$field->id] ?? ('f' . $field->id);
        $row['carry_from'] = $this->remapAlias((string)($row['carry_from'] ?? ''), $aliases);
        $row['logic_rules'] = $this->remapRuleFieldKeys($row['logic_rules'] ?? [], $aliases);
        $row['branches'] = $this->remapRuleFieldKeys($row['branches'] ?? [], $aliases);
        $row['loop_field_key'] = $this->remapAlias((string)($row['loop_field_key'] ?? ''), $aliases);
        $row['loop_label_field'] = $this->remapAlias((string)($row['loop_label_field'] ?? ''), $aliases);
        $values = [];
        foreach (self::CSV_COLUMNS as $column) {
            $values[] = $this->csvCellValue($column, $row, $field);
        }
        return $values;
    }

    /**
     * @param array<string,string> $aliases
     * @return array<string,mixed>
     */
    private function exportSettings(CustomForm $form, array $aliases): array
    {
        $values = $form->getSettings();
        foreach (self::SETTINGS_OMIT as $key) {
            unset($values[$key]);
        }
        if (($values['enrol_panel_mode'] ?? '') === CustomForm::ENROL_PANEL_EXISTING) {
            $values['enrol_panel_mode'] = CustomForm::ENROL_PANEL_NONE;
        }
        $values = $this->portableSettings($values, $aliases);

        return [
            'description' => $form->description,
            'thank_you_content' => $form->thank_you_content,
            'already_submitted_message' => $form->already_submitted_message,
            'custom_css' => $form->custom_css,
            'answers_visibility' => $form->answers_visibility,
            'allow_multiple' => (int)$form->allow_multiple,
            'allow_anonymous' => (int)$form->allow_anonymous,
            'allow_edit' => (int)$form->allow_edit,
            'allow_resume' => (int)$form->allow_resume,
            'values' => $values,
        ];
    }

    /**
     * @param array<string,mixed> $values
     * @param array<string,string> $aliases id and id123 => portable key
     * @return array<string,mixed>
     */
    private function portableSettings(array $values, array $aliases): array
    {
        if (isset($values['integrity']) && is_array($values['integrity'])) {
            $values['integrity'] = $this->rewriteIntegrityFields($values['integrity'], $aliases, true);
        }
        if (isset($values['export']) && is_array($values['export'])) {
            $values['export'] = $this->rewriteExportColumns($values['export'], $aliases, true);
        }
        return $values;
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function applyImportedSettings(CustomForm $form, array $settings): void
    {
        unset($form->fields);
        $aliasToId = [];
        foreach ($form->fields as $field) {
            $var = trim((string)$field->variable);
            if ($var !== '') {
                $aliasToId[$var] = (int)$field->id;
            }
        }

        $values = isset($settings['values']) && is_array($settings['values']) ? $settings['values'] : [];
        foreach (self::SETTINGS_OMIT as $key) {
            unset($values[$key]);
        }
        if (($values['enrol_panel_mode'] ?? '') === CustomForm::ENROL_PANEL_EXISTING) {
            $values['enrol_panel_mode'] = CustomForm::ENROL_PANEL_NONE;
        }
        if (isset($values['integrity']) && is_array($values['integrity'])) {
            $values['integrity'] = $this->rewriteIntegrityFields($values['integrity'], $aliasToId, false);
        }
        if (isset($values['export']) && is_array($values['export'])) {
            $values['export'] = $this->rewriteExportColumns($values['export'], $aliasToId, false);
        }

        $current = $form->getSettings();
        foreach ($values as $key => $value) {
            $current[$key] = $value;
        }
        $form->settings_json = json_encode($current, JSON_UNESCAPED_UNICODE);

        foreach (['description', 'thank_you_content', 'already_submitted_message', 'answers_visibility'] as $column) {
            if (array_key_exists($column, $settings)) {
                $form->$column = $settings[$column];
            }
        }
        if (array_key_exists('custom_css', $settings)) {
            $form->custom_css = str_replace('<', '', (string)$settings['custom_css']);
        }
        foreach (['allow_multiple', 'allow_anonymous', 'allow_edit', 'allow_resume'] as $flag) {
            if (array_key_exists($flag, $settings)) {
                $form->$flag = !empty($settings[$flag]) ? 1 : 0;
            }
        }
        $form->syncSettingsAttributes();
        $form->save(false);
        $this->settingsApplied = true;
    }

    /**
     * @param array<string,mixed> $integrity
     * @param array<string,string|int> $map
     * @return array<string,mixed>
     */
    private function rewriteIntegrityFields(array $integrity, array $map, bool $toAlias): array
    {
        foreach ($integrity['consistency_rules'] ?? [] as $r => $rule) {
            if (!is_array($rule)) {
                continue;
            }
            foreach ($rule['conditions'] ?? [] as $c => $cond) {
                if (!is_array($cond) || !isset($cond['field_id'])) {
                    continue;
                }
                $token = (string)$cond['field_id'];
                if ($token === '' || !isset($map[$token])) {
                    if (!$toAlias) {
                        unset($integrity['consistency_rules'][$r]['conditions'][$c]);
                    }
                    continue;
                }
                $integrity['consistency_rules'][$r]['conditions'][$c]['field_id'] = $toAlias
                    ? (string)$map[$token]
                    : (int)$map[$token];
            }
            if (isset($integrity['consistency_rules'][$r]['conditions']) && is_array($integrity['consistency_rules'][$r]['conditions'])) {
                $integrity['consistency_rules'][$r]['conditions'] = array_values($integrity['consistency_rules'][$r]['conditions']);
            }
        }
        return $integrity;
    }

    /**
     * @param array<string,mixed> $export
     * @param array<string,string|int> $map
     * @return array<string,mixed>
     */
    private function rewriteExportColumns(array $export, array $map, bool $toAlias): array
    {
        $columns = $export['exclude_columns'] ?? [];
        if (!is_array($columns)) {
            return $export;
        }
        $clean = [];
        foreach ($columns as $key) {
            $key = trim((string)$key);
            if ($key === '') {
                continue;
            }
            if (preg_match('/^field\.([A-Za-z0-9_]+)(.*)$/', $key, $match) && isset($map[$match[1]])) {
                $key = 'field.' . $map[$match[1]] . $match[2];
            } elseif (str_starts_with($key, 'field.') && !$toAlias) {
                continue;
            }
            $clean[] = $key;
        }
        $export['exclude_columns'] = array_values(array_unique($clean));
        return $export;
    }

    /**
     * Portable keys for skip logic: variable name when unique, otherwise f{id}.
     * Also maps stored numeric ids and studio keys (id123) onto those aliases.
     *
     * @return array<string,string>
     */
    private function portableAliasMap(CustomForm $form): array
    {
        $used = [];
        $idToKey = [];
        foreach ($form->fields as $field) {
            $id = (string)$field->id;
            $var = trim((string)$field->variable);
            $alias = ($var !== '' && !isset($used[strtolower($var)])) ? $var : ('f' . $id);
            $used[strtolower($alias)] = true;
            $idToKey[$id] = $alias;
        }
        $map = [];
        foreach ($idToKey as $id => $alias) {
            $map[$id] = $alias;
            $map['id' . $id] = $alias;
        }
        return $map;
    }

    /**
     * @return array<string,string>
     */
    private function csvAliasMap(CustomForm $form): array
    {
        return $this->portableAliasMap($form);
    }

    /**
     * @param mixed $rules
     * @param array<string,string> $aliases
     * @return array
     */
    private function remapRuleFieldKeys($rules, array $aliases): array
    {
        if (!is_array($rules)) {
            return [];
        }
        $out = [];
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            if (!empty($rule['all']) && is_array($rule['all'])) {
                $rule['all'] = $this->remapRuleFieldKeys($rule['all'], $aliases);
            }
            if (!empty($rule['any']) && is_array($rule['any'])) {
                $rule['any'] = $this->remapRuleFieldKeys($rule['any'], $aliases);
            }
            if (isset($rule['fieldKey'])) {
                $rule['fieldKey'] = $this->remapAlias((string)$rule['fieldKey'], $aliases);
            }
            $out[] = $rule;
        }
        return $out;
    }

    /**
     * @param array<string,string> $aliases
     */
    private function remapAlias(string $value, array $aliases): string
    {
        if ($value === '') {
            return '';
        }
        return $aliases[$value] ?? $value;
    }

    private function csvCellValue(string $column, array $row, FormField $field): string
    {
        switch ($column) {
            case 'type':
                return (string)$field->type;
            case 'key':
                return (string)($row['key'] ?? '');
            case 'label':
                return (string)$field->label;
            case 'variable':
                return (string)($field->variable ?? $row['variable'] ?? '');
            case 'internal_label':
                return (string)($field->internal_label ?? $row['internal_label'] ?? '');
            case 'help':
                return (string)$field->help_text;
            case 'required':
                return !empty($row['required']) ? '1' : '0';
            case 'options':
                return (string)($row['options'] ?? '');
            case 'grid_mobile_layout':
                return (string)($row['grid_mobile_layout'] ?? 'scroll');
            case 'branches':
            case 'validation':
            case 'logic_rules':
            case 'image_regions':
                $value = $row[$column] ?? [];
                if ($value === '' || $value === []) {
                    return '';
                }
                if (is_string($value)) {
                    return $value;
                }
                return json_encode($value, JSON_UNESCAPED_UNICODE);
            default:
                $value = $row[$column] ?? '';
                if (is_bool($value)) {
                    return $value ? '1' : '0';
                }
                if (is_array($value)) {
                    if ($value === []) {
                        return '';
                    }
                    return json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                return (string)$value;
        }
    }

    public function normalizeHeader(string $name): string
    {
        $name = strtolower(trim($name));
        $name = str_replace([' ', '-'], '_', $name);
        $aliases = [
            'help_text' => 'help',
            'pagekey' => 'page_key',
            'pagetitle' => 'page_title',
        ];
        return $aliases[$name] ?? $name;
    }

    private function metaKeyFromAlias(string $type): string
    {
        $key = $this->normalizeTypeKey(strtolower(trim($type)));
        $aliases = [
            'ip' => 'ip',
            'ip_address' => 'ip',
            'browser' => 'browser',
            'os' => 'os',
            'operating_system' => 'os',
            'device' => 'device',
            'screen' => 'screen',
            'screen_size' => 'screen',
            'language' => 'language',
            'browser_language' => 'language',
            'timezone' => 'timezone',
            'time_zone' => 'timezone',
            'user_agent' => 'userAgent',
            'useragent' => 'userAgent',
        ];
        return $aliases[$key] ?? '';
    }

    public function normalizeType(string $type): string
    {
        $raw = strtolower(trim($type));
        if ($raw === '') {
            return '';
        }
        $key = $this->normalizeTypeKey($raw);

        if (isset(FormField::getTypeLabels()[$key])) {
            return $key;
        }

        $aliases = [
            'page' => FormField::TYPE_PAGE_BREAK,
            'pagebreak' => FormField::TYPE_PAGE_BREAK,
            'new_page' => FormField::TYPE_PAGE_BREAK,
            'question_group' => FormField::TYPE_QUESTION_GROUP,
            'group' => FormField::TYPE_QUESTION_GROUP,
            'block' => FormField::TYPE_QUESTION_GROUP,
            'group_end' => FormField::TYPE_GROUP_END,
            'endgroup' => FormField::TYPE_GROUP_END,
            'rating_scale' => FormField::TYPE_RATING,
            'scale' => FormField::TYPE_RATING,
            'thermometer' => FormField::TYPE_RATING,
            'stars' => FormField::TYPE_RATING,
            'ranking_drag_drop' => FormField::TYPE_RANKING,
            'rank' => FormField::TYPE_RANKING,
            'file_upload' => FormField::TYPE_FILE,
            'upload' => FormField::TYPE_FILE,
            'rich_text_section' => FormField::TYPE_RICH_TEXT,
            'richtext' => FormField::TYPE_RICH_TEXT,
            'instructions' => FormField::TYPE_RICH_TEXT,
            'html_custom_block' => FormField::TYPE_HTML,
            'custom_block' => FormField::TYPE_HTML,
            'custom_html' => FormField::TYPE_HTML,
            'grid' => FormField::TYPE_GRID_SINGLE,
            'matrix' => FormField::TYPE_GRID_SINGLE,
            'grid_single_choice' => FormField::TYPE_GRID_SINGLE,
            'grid_multi_choice' => FormField::TYPE_GRID_MULTI,
            'bestworst' => FormField::TYPE_BEST_WORST,
            'best_worst' => FormField::TYPE_BEST_WORST,
            'max_diff' => FormField::TYPE_MAXDIFF,
            'drill_down' => FormField::TYPE_DRILLDOWN,
            'image' => FormField::TYPE_IMAGE_AREA,
            'hotspot' => FormField::TYPE_IMAGE_AREA,
            'map' => FormField::TYPE_MAP,
            'respondent_meta' => FormField::TYPE_RESPONDENT_META,
            'metadata' => FormField::TYPE_RESPONDENT_META,
            'ip' => FormField::TYPE_RESPONDENT_META,
            'ip_address' => FormField::TYPE_RESPONDENT_META,
            'browser' => FormField::TYPE_RESPONDENT_META,
            'os' => FormField::TYPE_RESPONDENT_META,
            'operating_system' => FormField::TYPE_RESPONDENT_META,
            'device' => FormField::TYPE_RESPONDENT_META,
            'screen' => FormField::TYPE_RESPONDENT_META,
            'screen_size' => FormField::TYPE_RESPONDENT_META,
            'language' => FormField::TYPE_RESPONDENT_META,
            'timezone' => FormField::TYPE_RESPONDENT_META,
            'time_zone' => FormField::TYPE_RESPONDENT_META,
            'user_agent' => FormField::TYPE_RESPONDENT_META,
            'useragent' => FormField::TYPE_RESPONDENT_META,
            'panel_attr' => FormField::TYPE_PANEL_ATTR,
            'panel_member' => FormField::TYPE_PANEL_ATTR,
            'long_text' => FormField::TYPE_TEXTAREA,
            'text_area' => FormField::TYPE_TEXTAREA,
            'short_text' => FormField::TYPE_TEXT,
            'drop_down' => FormField::TYPE_DROPDOWN,
            'select' => FormField::TYPE_DROPDOWN,
            'checkboxes' => FormField::TYPE_CHECKBOX,
            'multi_select' => FormField::TYPE_CHECKBOX,
            'radio_buttons' => FormField::TYPE_RADIO,
            'radios' => FormField::TYPE_RADIO,
            'numeric' => FormField::TYPE_NUMBER,
        ];
        if (isset($aliases[$key])) {
            return $aliases[$key];
        }

        foreach (FormField::getTypeLabels() as $id => $label) {
            $norm = $this->normalizeTypeKey((string)$label);
            if ($norm === $key) {
                return $id;
            }
        }

        return $key;
    }

    private function normalizeTypeKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['–', '—', '&'], ['-', '-', ''], $value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;
        return trim($value, '_');
    }

    private function cellBool($value): bool
    {
        $value = strtolower(trim((string)$value));
        return in_array($value, ['1', 'true', 'yes', 'y'], true);
    }

    /**
     * @return mixed
     */
    private function decodeJsonCell($value)
    {
        if (is_array($value)) {
            return $value;
        }
        $raw = trim((string)$value);
        if ($raw === '') {
            return [];
        }
        if ($raw[0] === '[' || $raw[0] === '{') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $raw;
    }
}
