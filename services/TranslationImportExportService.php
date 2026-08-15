<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormFieldI18n;
use humhub\modules\thiscoveryForms\models\FormI18n;
use Yii;

/**
 * Export source strings for translation and import overlays in one or more languages.
 */
class TranslationImportExportService
{
    public const FORMAT = 'thiscovery-forms-translations';
    public const VERSION = 1;

    private const RESERVED_HEADERS = [
        'key', 'type', 'part', 'source', 'field_id', 'id', 'context', 'notes', 'comment',
    ];

    private TranslationService $translations;

    public function __construct(?TranslationService $translations = null)
    {
        $this->translations = $translations ?: new TranslationService();
    }

    /**
     * Target language columns for the export spreadsheet.
     *
     * @return string[]
     */
    public function exportLanguages(CustomForm $form): array
    {
        $source = $form->getSourceLanguage();
        $targets = array_values(array_filter($form->getEnabledLanguages(), static fn($l) => $l !== $source));
        if ($targets) {
            return $targets;
        }
        $all = [];
        foreach (array_keys(TranslationService::languageLabels()) as $code) {
            if ($code !== $source) {
                $all[] = $code;
            }
        }
        return $all;
    }

    /**
     * @return array{format: string, version: int, source_language: string, languages: string[], strings: array}
     */
    public function exportJson(CustomForm $form): array
    {
        $langs = $this->exportLanguages($form);
        $existing = $this->existingByLanguage($form, $langs);
        $strings = [];
        foreach ($this->collectUnits($form) as $unit) {
            $translations = [];
            foreach ($langs as $lang) {
                $translations[$lang] = (string)($existing[$lang][$unit['key']] ?? '');
            }
            $strings[] = [
                'key' => $unit['key'],
                'type' => $unit['type'],
                'part' => $unit['part'],
                'source' => $unit['source'],
                'translations' => $translations,
            ];
        }

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'source_language' => $form->getSourceLanguage(),
            'languages' => $langs,
            'strings' => $strings,
        ];
    }

    public function exportJsonString(CustomForm $form): string
    {
        return json_encode($this->exportJson($form), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function exportCsv(CustomForm $form): string
    {
        $langs = $this->exportLanguages($form);
        $existing = $this->existingByLanguage($form, $langs);
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, array_merge(['key', 'type', 'part', 'source'], $langs));
        foreach ($this->collectUnits($form) as $unit) {
            $row = [$unit['key'], $unit['type'], $unit['part'], $unit['source']];
            foreach ($langs as $lang) {
                $row[] = (string)($existing[$lang][$unit['key']] ?? '');
            }
            fputcsv($fh, $row);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        return $csv === false ? '' : $csv;
    }

    /**
     * @return string|null error message
     */
    public function importJson(CustomForm $form, string $json): ?string
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return Yii::t('ThiscoveryFormsModule.base', 'The file is not valid JSON.');
        }

        $fields = $decoded['fields'] ?? null;
        $strings = $decoded['strings'] ?? null;
        if (($decoded['format'] ?? '') === QuestionImportExportService::FORMAT || (is_array($fields) && !is_array($strings))) {
            return Yii::t('ThiscoveryFormsModule.base', 'This is a questions file. Use Import questions on the Share tab, or export a translation file from the Translations tab.');
        }

        $rows = [];
        if (is_array($strings)) {
            foreach ($decoded['strings'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $key = trim((string)($item['key'] ?? ''));
                $translations = $item['translations'] ?? [];
                if ($key === '' || !is_array($translations)) {
                    continue;
                }
                $rows[] = ['key' => $key, 'translations' => $translations];
            }
        }

        if (!$rows) {
            return Yii::t('ThiscoveryFormsModule.base', 'JSON is missing a strings list.');
        }

        return $this->importRows($form, $rows);
    }

    /**
     * @return string|null error message
     */
    public function importCsv(CustomForm $form, string $csv): ?string
    {
        if (str_starts_with($csv, "\xEF\xBB\xBF")) {
            $csv = substr($csv, 3);
        }

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $csv);
        rewind($fh);

        $header = null;
        $langCols = [];
        $rows = [];
        while (($row = fgetcsv($fh)) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }
            if ($header === null) {
                $header = array_map(static fn($v) => trim((string)$v), $row);
                foreach ($header as $idx => $name) {
                    if ($name === '' || in_array(strtolower($name), self::RESERVED_HEADERS, true)) {
                        continue;
                    }
                    $lang = TranslationService::normalizeLanguage($name);
                    if ($lang !== null) {
                        $langCols[$idx] = $lang;
                    }
                }
                $headerLower = array_map('strtolower', $header);
                if (in_array('type', $headerLower, true) && in_array('label', $headerLower, true) && !in_array('key', $headerLower, true)) {
                    fclose($fh);
                    return Yii::t('ThiscoveryFormsModule.base', 'This looks like a questions CSV. Use Import questions on the Share tab, or export a translation file from the Translations tab.');
                }
                continue;
            }

            $map = [];
            foreach ($header as $idx => $name) {
                $map[strtolower($name)] = $row[$idx] ?? '';
            }
            $key = trim((string)($map['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $translations = [];
            foreach ($langCols as $idx => $lang) {
                $translations[$lang] = (string)($row[$idx] ?? '');
            }
            $rows[] = ['key' => $key, 'translations' => $translations];
        }
        fclose($fh);

        if ($header === null) {
            return Yii::t('ThiscoveryFormsModule.base', 'The CSV file is empty.');
        }
        if (!$langCols) {
            return Yii::t('ThiscoveryFormsModule.base', 'Add at least one language column (for example cy, fr, de) besides key and source.');
        }
        if (!$rows) {
            return Yii::t('ThiscoveryFormsModule.base', 'No translation rows found in the CSV file.');
        }

        return $this->importRows($form, $rows);
    }

    /**
     * @param array<int, array{key: string, translations: array<string, string>}> $rows
     * @return string|null error message
     */
    public function importRows(CustomForm $form, array $rows): ?string
    {
        $source = $form->getSourceLanguage();
        $fieldIds = [];
        foreach ($form->fields as $field) {
            $fieldIds[(int)$field->id] = $field;
        }

        $formByLang = [];
        $fieldByLang = [];
        $importedLangs = [];
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $key = (string)($row['key'] ?? '');
            $parsed = $this->parseKey($key);
            if ($parsed === null) {
                $skipped++;
                continue;
            }
            if ($parsed['kind'] === 'field' && !isset($fieldIds[$parsed['field_id']])) {
                $skipped++;
                continue;
            }

            $translations = $row['translations'] ?? [];
            if (!is_array($translations)) {
                continue;
            }
            foreach ($translations as $langCode => $value) {
                $lang = TranslationService::normalizeLanguage((string)$langCode);
                if ($lang === null || $lang === $source) {
                    continue;
                }
                $value = trim((string)$value);
                if ($value === '') {
                    continue;
                }
                $importedLangs[$lang] = true;
                $updated++;
                if ($parsed['kind'] === 'form') {
                    if (!isset($formByLang[$lang])) {
                        $formByLang[$lang] = $this->formRowToData($form, $lang);
                    }
                    $formByLang[$lang][$parsed['part']] = $value;
                    continue;
                }

                $fid = $parsed['field_id'];
                if (!isset($fieldByLang[$lang][$fid])) {
                    $fieldByLang[$lang][$fid] = $this->fieldExistingData($fieldIds[$fid], $lang);
                }
                $this->applyFieldPart($fieldByLang[$lang][$fid], $parsed, $value, $fieldIds[$fid]);
            }
        }

        if (!$importedLangs) {
            return Yii::t('ThiscoveryFormsModule.base', 'No translated text was found. Fill the language columns and try again.');
        }

        foreach (array_keys($importedLangs) as $lang) {
            if (isset($formByLang[$lang])) {
                $this->translations->saveFormStrings($form, $lang, $formByLang[$lang]);
            }
            foreach ($fieldByLang[$lang] ?? [] as $fid => $data) {
                if (isset($fieldIds[$fid])) {
                    $this->translations->saveFieldStrings($fieldIds[$fid], $lang, $data);
                }
            }
        }

        $enabled = $form->getEnabledLanguages();
        foreach (array_keys($importedLangs) as $lang) {
            if (!in_array($lang, $enabled, true)) {
                $enabled[] = $lang;
            }
        }
        $form->enabled_languages = $enabled;
        $form->save(false);

        $names = [];
        $labels = TranslationService::languageLabels();
        foreach (array_keys($importedLangs) as $lang) {
            $names[] = $labels[$lang] ?? $lang;
        }

        Yii::$app->session->setFlash('success', Yii::t(
            'ThiscoveryFormsModule.base',
            'Imported translations for {languages} ({count} strings).',
            [
                'languages' => implode(', ', $names),
                'count' => $updated,
            ]
        ));
        if ($skipped > 0) {
            Yii::$app->session->setFlash('info', Yii::t(
                'ThiscoveryFormsModule.base',
                '{n} rows were skipped because those questions no longer exist.',
                ['n' => $skipped]
            ));
        }

        return null;
    }

    /**
     * @return array<int, array{key: string, type: string, part: string, source: string, field_id: ?int, index: ?int}>
     */
    public function collectUnits(CustomForm $form): array
    {
        $units = [];
        $this->pushUnit($units, 'form.title', 'form', 'title', (string)$form->title, null);
        $this->pushUnit($units, 'form.description', 'form', 'description', (string)$form->description, null, false);
        $this->pushUnit($units, 'form.thank_you_content', 'form', 'thank_you_content', (string)$form->thank_you_content, null, false);

        foreach ($form->fields as $field) {
            $id = (int)$field->id;
            $type = (string)$field->type;
            $this->pushUnit($units, "field.$id.label", $type, 'label', (string)$field->label, $id);
            $this->pushUnit($units, "field.$id.help_text", $type, 'help_text', (string)$field->help_text, $id, false);

            foreach ($field->getOptions() as $i => $opt) {
                $this->pushUnit($units, "field.$id.option.$i", $type, 'option', (string)$opt, $id, true, (int)$i);
            }

            if ($field->type === FormField::TYPE_PAGE_BREAK) {
                $this->pushUnit($units, "field.$id.page_title", $type, 'page_title', (string)$field->getPageBreakConfig()['title'], $id, false);
            }
            if ($field->type === FormField::TYPE_RICH_TEXT) {
                $this->pushUnit($units, "field.$id.rich_content", $type, 'rich_content', $field->getRichTextContent(), $id, false);
            }
            if ($field->type === FormField::TYPE_HTML) {
                $html = $field->getHtmlConfig();
                $this->pushUnit($units, "field.$id.html_content", $type, 'html_content', (string)$html['html'], $id, false);
                $this->pushUnit($units, "field.$id.html_instructions", $type, 'html_instructions', (string)$html['instructions'], $id, false);
            }
            if ($field->type === FormField::TYPE_RATING) {
                $scale = $field->getRatingScale();
                $this->pushUnit($units, "field.$id.rating_low_label", $type, 'rating_low_label', (string)$scale['lowLabel'], $id, false);
                $this->pushUnit($units, "field.$id.rating_high_label", $type, 'rating_high_label', (string)$scale['highLabel'], $id, false);
            }
            if ($field->type === FormField::TYPE_GRID_SINGLE || $field->type === FormField::TYPE_GRID_MULTI) {
                $grid = $field->getGridConfig();
                foreach ($grid['rows'] as $i => $row) {
                    $this->pushUnit($units, "field.$id.grid_row.$i", $type, 'grid_row', (string)$row, $id, true, (int)$i);
                }
                foreach ($grid['columns'] as $i => $col) {
                    $this->pushUnit($units, "field.$id.grid_column.$i", $type, 'grid_column', (string)$col, $id, true, (int)$i);
                }
            }
            if ($field->type === FormField::TYPE_BEST_WORST || $field->type === FormField::TYPE_MAXDIFF) {
                foreach ($field->getItemsConfig()['items'] as $i => $item) {
                    $this->pushUnit($units, "field.$id.item.$i", $type, 'item', (string)$item, $id, true, (int)$i);
                }
            }
        }

        return $units;
    }

    /**
     * @param array<int, array> $units
     */
    private function pushUnit(array &$units, string $key, string $type, string $part, string $source, ?int $fieldId, bool $allowEmpty = true, ?int $index = null): void
    {
        if (!$allowEmpty && trim($source) === '') {
            return;
        }
        $units[] = [
            'key' => $key,
            'type' => $type,
            'part' => $part,
            'source' => $source,
            'field_id' => $fieldId,
            'index' => $index,
        ];
    }

    /**
     * @param string[] $langs
     * @return array<string, array<string, string>>
     */
    private function existingByLanguage(CustomForm $form, array $langs): array
    {
        $map = [];
        foreach ($langs as $lang) {
            $map[$lang] = [];
        }
        if (!$langs) {
            return $map;
        }

        foreach (FormI18n::find()->where(['form_id' => $form->id, 'language' => $langs])->all() as $row) {
            /** @var FormI18n $row */
            $map[$row->language]['form.title'] = (string)$row->title;
            $map[$row->language]['form.description'] = (string)$row->description;
            $map[$row->language]['form.thank_you_content'] = (string)$row->thank_you_content;
        }

        $ids = [];
        foreach ($form->fields as $field) {
            $ids[] = (int)$field->id;
        }
        if (!$ids) {
            return $map;
        }

        foreach (FormFieldI18n::find()->where(['field_id' => $ids, 'language' => $langs])->all() as $row) {
            /** @var FormFieldI18n $row */
            $id = (int)$row->field_id;
            $lang = $row->language;
            $map[$lang]["field.$id.label"] = (string)$row->label;
            $map[$lang]["field.$id.help_text"] = (string)$row->help_text;
            $overlay = $row->getOptionsOverlay();
            foreach ($overlay['options'] ?? [] as $i => $opt) {
                $map[$lang]["field.$id.option.$i"] = (string)$opt;
            }
            if (!empty($overlay['page_title'])) {
                $map[$lang]["field.$id.page_title"] = (string)$overlay['page_title'];
            }
            if (!empty($overlay['rich_content'])) {
                $map[$lang]["field.$id.rich_content"] = (string)$overlay['rich_content'];
            }
            if (!empty($overlay['html_content'])) {
                $map[$lang]["field.$id.html_content"] = (string)$overlay['html_content'];
            }
            if (!empty($overlay['html_instructions'])) {
                $map[$lang]["field.$id.html_instructions"] = (string)$overlay['html_instructions'];
            }
            if (!empty($overlay['lowLabel'])) {
                $map[$lang]["field.$id.rating_low_label"] = (string)$overlay['lowLabel'];
            }
            if (!empty($overlay['highLabel'])) {
                $map[$lang]["field.$id.rating_high_label"] = (string)$overlay['highLabel'];
            }
            foreach ($overlay['rows'] ?? [] as $i => $val) {
                $map[$lang]["field.$id.grid_row.$i"] = (string)$val;
            }
            foreach ($overlay['columns'] ?? [] as $i => $val) {
                $map[$lang]["field.$id.grid_column.$i"] = (string)$val;
            }
            foreach ($overlay['items'] ?? [] as $i => $val) {
                $map[$lang]["field.$id.item.$i"] = (string)$val;
            }
        }

        return $map;
    }

    /**
     * @return array{kind: string, part: string, field_id?: int, index?: int}|null
     */
    private function parseKey(string $key): ?array
    {
        $key = trim($key);
        if (preg_match('/^form\.(title|description|thank_you_content|thank_you)$/', $key, $m)) {
            $part = $m[1] === 'thank_you' ? 'thank_you_content' : $m[1];
            return ['kind' => 'form', 'part' => $part];
        }
        if (preg_match('/^field\.(\d+)\.(label|help_text|help|page_title|rich_content|html_content|html_instructions|rating_low_label|rating_high_label)$/', $key, $m)) {
            $part = $m[2] === 'help' ? 'help_text' : $m[2];
            return ['kind' => 'field', 'field_id' => (int)$m[1], 'part' => $part];
        }
        if (preg_match('/^field\.(\d+)\.(option|grid_row|grid_column|item)\.(\d+)$/', $key, $m)) {
            return [
                'kind' => 'field',
                'field_id' => (int)$m[1],
                'part' => $m[2],
                'index' => (int)$m[3],
            ];
        }
        return null;
    }

    /**
     * @return array{title: string, description: string, thank_you_content: string}
     */
    private function formRowToData(CustomForm $form, string $lang): array
    {
        $row = FormI18n::findOne(['form_id' => $form->id, 'language' => $lang]);
        return [
            'title' => $row ? (string)$row->title : '',
            'description' => $row ? (string)$row->description : '',
            'thank_you_content' => $row ? (string)$row->thank_you_content : '',
        ];
    }

    private function fieldExistingData(FormField $field, string $lang): array
    {
        $row = FormFieldI18n::findOne(['field_id' => $field->id, 'language' => $lang]);
        if ($row) {
            return $this->translations->fieldRowToData($row);
        }
        return [
            'label' => '',
            'help_text' => '',
            'options' => '',
            'page_title' => '',
            'rich_content' => '',
            'html_content' => '',
            'html_instructions' => '',
            'rating_low_label' => '',
            'rating_high_label' => '',
            'rows' => [],
            'columns' => [],
            'items' => [],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array{kind: string, part: string, field_id?: int, index?: int} $parsed
     */
    private function applyFieldPart(array &$data, array $parsed, string $value, FormField $field): void
    {
        $part = $parsed['part'];
        $index = $parsed['index'] ?? null;

        if (in_array($part, ['label', 'help_text', 'page_title', 'rich_content', 'html_content', 'html_instructions', 'rating_low_label', 'rating_high_label'], true)) {
            $data[$part] = $value;
            return;
        }

        if ($part === 'option' && $index !== null) {
            $lines = preg_split('/\r\n|\r|\n/', (string)$data['options']) ?: [];
            $source = $field->getOptions();
            $merged = [];
            foreach ($source as $i => $src) {
                $existing = trim((string)($lines[$i] ?? ''));
                $merged[$i] = $existing !== '' ? $existing : (string)$src;
            }
            $merged[$index] = $value;
            ksort($merged);
            $data['options'] = implode("\n", $merged);
            return;
        }

        $listKey = ['grid_row' => 'rows', 'grid_column' => 'columns', 'item' => 'items'][$part] ?? null;
        if ($listKey && $index !== null) {
            $source = [];
            if ($listKey === 'rows' || $listKey === 'columns') {
                $grid = $field->getGridConfig();
                $source = $grid[$listKey] ?? [];
            } elseif ($listKey === 'items') {
                $source = $field->getItemsConfig()['items'] ?? [];
            }
            $current = is_array($data[$listKey] ?? null) ? $data[$listKey] : [];
            $merged = [];
            foreach ($source as $i => $src) {
                $existing = trim((string)($current[$i] ?? ''));
                $merged[$i] = $existing !== '' ? $existing : (string)$src;
            }
            $merged[$index] = $value;
            ksort($merged);
            $data[$listKey] = array_values($merged);
        }
    }
}
