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
        fputcsv($fh, ['type', 'label', 'help', 'required', 'options']);
        foreach ($form->fields as $field) {
            fputcsv($fh, [
                $field->type,
                $field->label,
                (string)$field->help_text,
                $field->required ? '1' : '0',
                $field->getOptionsAsText(),
            ]);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        return $csv === false ? '' : $csv;
    }

    /**
     * Append imported fields onto the form.
     * @return string|null error message
     */
    public function importJson(CustomForm $form, string $json): ?string
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return Yii::t('ThiscoveryFormsModule.base', 'The file is not valid JSON.');
        }

        $fields = $decoded['fields'] ?? null;
        if (!is_array($fields)) {
            return Yii::t('ThiscoveryFormsModule.base', 'JSON is missing a fields list.');
        }

        return $this->appendFieldPayloads($form, $fields);
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

            if ($header === null) {
                $header = array_map(static fn($v) => strtolower(trim((string)$v)), $row);
                if (in_array('type', $header, true) && in_array('label', $header, true)) {
                    continue;
                }
                $header = ['type', 'label', 'help', 'required', 'options'];
            }

            $map = [];
            foreach ($header as $idx => $name) {
                $map[$name] = $row[$idx] ?? '';
            }
            $type = trim((string)($map['type'] ?? ''));
            $label = trim((string)($map['label'] ?? ''));
            if ($type === '' || $label === '') {
                continue;
            }
            $payloads[] = [
                'type' => $type,
                'label' => $label,
                'help_text' => (string)($map['help'] ?? $map['help_text'] ?? ''),
                'required' => !empty($map['required']) && $map['required'] !== '0',
                'options' => (string)($map['options'] ?? ''),
            ];
        }
        fclose($fh);

        if (!$payloads) {
            return Yii::t('ThiscoveryFormsModule.base', 'No questions found in the CSV file.');
        }

        return $this->appendFieldPayloads($form, $payloads);
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
    public function appendFieldPayloads(CustomForm $form, array $payloads): ?string
    {
        $existing = [];
        foreach ($form->fields as $field) {
            $existing[(string)$field->id] = $field->toPostRow();
            $existing[(string)$field->id]['id'] = $field->id;
        }

        $next = count($existing);
        foreach ($payloads as $payload) {
            if (!is_array($payload)) {
                continue;
            }
            $row = FormField::exportToPostRow($payload);
            if ($row === null) {
                continue;
            }
            $existing['imp' . $next] = $row;
            $next++;
        }

        if (!$form->saveFieldsFromPost($existing)) {
            return Yii::t('ThiscoveryFormsModule.base', 'Could not import questions.');
        }

        return null;
    }
}
