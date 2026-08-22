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

    /**
     * Spreadsheet columns. Import accepts any subset; extra unknown columns are ignored.
     * @var string[]
     */
    public const CSV_COLUMNS = [
        'type',
        'key',
        'label',
        'help',
        'required',
        'options',
        'page_key',
        'page_title',
        'branches',
        'rating_min',
        'rating_max',
        'rating_step',
        'rating_low_label',
        'rating_high_label',
        'rating_display',
        'grid_rows',
        'grid_columns',
        'items',
        'maxdiff_set_size',
        'maxdiff_set_count',
        'exclusive_option',
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
        'default_value',
        'meta_key',
        'logic_action',
        'logic_combinator',
        'logic_goto',
        'logic_rules',
    ];

    public function exportJson(CustomForm $form): array
    {
        $fields = [];
        foreach ($form->fields as $field) {
            $fields[] = $field->toExportArray();
        }

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'kind' => $form->kind,
            'title' => $form->title,
            'fields' => $fields,
        ];
    }

    public function exportJsonString(CustomForm $form): string
    {
        return json_encode($this->exportJson($form), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function exportCsv(CustomForm $form): string
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, self::CSV_COLUMNS);
        $aliases = $this->csvAliasMap($form);
        foreach ($form->fields as $field) {
            fputcsv($fh, $this->csvRowFromField($field, $aliases));
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
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return Yii::t('ThiscoveryFormsModule.base', 'The file is not valid JSON.');
        }

        $fields = $decoded['fields'] ?? null;
        if (!is_array($fields)) {
            return Yii::t('ThiscoveryFormsModule.base', 'JSON is missing a fields list.');
        }

        return $this->appendFieldPayloads($form, $fields, $replace);
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
                'help_text' => (string)($map['help'] ?? $map['help_text'] ?? ''),
                'required' => $this->cellBool($map['required'] ?? ''),
                'options' => (string)($map['options'] ?? ''),
                'page_key' => (string)($map['page_key'] ?? ''),
                'page_title' => (string)($map['page_title'] ?? ''),
                'branches' => $this->decodeJsonCell($map['branches'] ?? ''),
                'rating_min' => $map['rating_min'] ?? 1,
                'rating_max' => $map['rating_max'] ?? 5,
                'rating_step' => $map['rating_step'] ?? 1,
                'rating_low_label' => (string)($map['rating_low_label'] ?? ''),
                'rating_high_label' => (string)($map['rating_high_label'] ?? ''),
                'rating_display' => (string)($map['rating_display'] ?? FormField::RATING_DISPLAY_PILLS),
                'grid_rows' => (string)($map['grid_rows'] ?? ''),
                'grid_columns' => (string)($map['grid_columns'] ?? ''),
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
                'logic_rules' => $this->decodeJsonCell($map['logic_rules'] ?? ''),
            ];
            if (!is_array($payload['branches'])) {
                $payload['branches'] = [];
            }
            if (!is_array($payload['logic_rules'])) {
                $payload['logic_rules'] = [];
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
     */
    public function appendFieldPayloads(CustomForm $form, array $payloads, bool $replace = false): ?string
    {
        $existing = [];
        if (!$replace) {
            foreach ($form->fields as $field) {
                $existing[(string)$field->id] = $field->toPostRow();
                $existing[(string)$field->id]['id'] = $field->id;
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
            $row = FormField::exportToPostRow($payload);
            if ($row === null) {
                continue;
            }
            $importKey = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($payload['key'] ?? '')) ?? '';
            if ($importKey !== '') {
                $row['import_key'] = $importKey;
            }
            $existing['imp' . $next] = $row;
            $next++;
            $imported++;
        }

        if ($imported === 0) {
            return Yii::t('ThiscoveryFormsModule.base', 'No questions found in the import file.');
        }

        if (!$form->saveFieldsFromPost($existing)) {
            return Yii::t('ThiscoveryFormsModule.base', 'Could not import questions.');
        }

        return null;
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
        $values = [];
        foreach (self::CSV_COLUMNS as $column) {
            $values[] = $this->csvCellValue($column, $row, $field);
        }
        return $values;
    }

    /**
     * @return array<string,string>
     */
    private function csvAliasMap(CustomForm $form): array
    {
        $map = [];
        foreach ($form->fields as $field) {
            $map[(string)$field->id] = 'f' . $field->id;
        }
        return $map;
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
            case 'help':
                return (string)$field->help_text;
            case 'required':
                return !empty($row['required']) ? '1' : '0';
            case 'options':
                return (string)($row['options'] ?? '');
            case 'branches':
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
