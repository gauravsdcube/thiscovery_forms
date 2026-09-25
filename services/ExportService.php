<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\file\models\File;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use Yii;

class ExportService
{
    public const HEADER_LABEL = 'label';
    public const HEADER_VARIABLE = 'variable';
    public const HEADER_BOTH = 'both';

    public static function headerModeLabels(): array
    {
        return [
            self::HEADER_LABEL => Yii::t('ThiscoveryFormsModule.base', 'Participant labels'),
            self::HEADER_VARIABLE => Yii::t('ThiscoveryFormsModule.base', 'Variable names'),
            self::HEADER_BOTH => Yii::t('ThiscoveryFormsModule.base', 'Variable and label'),
        ];
    }

    public function toCsv(CustomForm $form, array $params = []): string
    {
        $params['forExport'] = 1;
        [$query] = AnswerListService::query($form, $params);
        $query->with(['answerFields', 'user', 'wave', 'round', 'panelMember', 'integrityMeta']);

        $fields = array_values(array_filter($form->fields, static fn($f) => $f->collectsAnswer()));
        $headerMode = (string)($params['header_mode'] ?? self::HEADER_LABEL);
        if (!isset(self::headerModeLabels()[$headerMode])) {
            $headerMode = self::HEADER_LABEL;
        }
        $columns = ExportSettings::resolvedColumns($form, $fields, $headerMode);
        $scrub = ExportSettings::isPiiScrub($form);
        $redactor = $scrub ? new PiiRedactor() : null;
        $fh = fopen('php://temp', 'r+');

        $header = [];
        foreach ($columns as $col) {
            $header[] = $col['header'];
        }
        fputcsv($fh, $header);

        /** @var FormAnswer $answer */
        foreach ($query->each(100) as $answer) {
            $cells = $this->answerCells($form, $answer, $fields);
            $row = [];
            foreach ($columns as $col) {
                $value = $cells[$col['key']] ?? '';
                if ($redactor) {
                    $value = $redactor->redact($value);
                }
                $row[] = $value;
            }
            fputcsv($fh, $row);
        }

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return $csv === false ? '' : $csv;
    }

    /**
     * @param FormField[] $fields
     * @return array<string, string>
     */
    private function answerCells(CustomForm $form, FormAnswer $answer, array $fields): array
    {
        $status = $answer->isComplete()
            ? Yii::t('ThiscoveryFormsModule.base', 'Complete')
            : Yii::t('ThiscoveryFormsModule.base', 'In progress');
        $meta = $answer->integrityMeta;
        $flagParts = [];
        if ($meta) {
            foreach ($meta->getFlagsForViewer(false) as $flag) {
                $flagParts[] = ($flag['category'] ?? '') . ':' . ($flag['code'] ?? '') . ' ' . ($flag['message'] ?? '');
            }
        }
        $cells = [
            ExportSettings::KEY_ANSWER_ID => (string)$answer->id,
            ExportSettings::KEY_STATUS => $status,
            ExportSettings::KEY_USER => (string)$answer->getSubmitterDisplayName($form),
            ExportSettings::KEY_SUBMITTED_AT => (string)$answer->created_at,
            ExportSettings::KEY_UPDATED_AT => (string)$answer->updated_at,
            ExportSettings::KEY_QUALITY_SCORE => $meta ? (string)$meta->overall_score : '',
            ExportSettings::KEY_INTEGRITY_STATUS => $meta ? (string)$meta->getStatusLabel() : '',
            ExportSettings::KEY_ANALYSIS_STATUS => $meta ? (string)$meta->getAnalysisLabel() : '',
            ExportSettings::KEY_QUALITY_FLAGS => implode('; ', $flagParts),
        ];
        $map = $answer->getValuesMap();
        $just = $answer->getJustificationsMap();
        if ($form->usesWaves()) {
            $cells[ExportSettings::KEY_WAVE] = $answer->wave ? $answer->wave->getDisplayTitle() : '';
        }
        if ($form->isEq5d()) {
            $score = (new Eq5dService())->score($form, $map);
            $cells[ExportSettings::KEY_EQ5D_PROFILE] = (string)$score['profile'];
            $cells[ExportSettings::KEY_EQ5D_VAS] = (string)$score['vas'];
        }
        if ($form->isConsensus()) {
            $cells[ExportSettings::KEY_ROUND] = $answer->round ? $answer->round->getDisplayTitle() : '';
            $cells[ExportSettings::KEY_WEIGHT] = (string)$answer->weight;
        }
        foreach ($fields as $field) {
            $val = $map[$field->id] ?? '';
            if ($field->type === FormField::TYPE_FILE && is_string($val) && $val !== '') {
                $file = File::findOne(['guid' => $val]);
                $formatted = $file ? $file->file_name : $val;
            } elseif ($field->type === FormField::TYPE_RESPONDENT_META) {
                $formatted = (new RespondentMetaService())->formatDisplay($val);
            } else {
                $formatted = $this->formatCell($val);
            }
            $cells[ExportSettings::fieldColumnKey($field)] = $formatted;
            if ($field->supportsJustification()) {
                $cells[ExportSettings::commentColumnKey($field)] = (string)($just[$field->id] ?? '');
            }
        }
        return $cells;
    }

    public function fieldHeader(FormField $field, string $mode): string
    {
        $label = trim((string)$field->label);
        $variable = trim((string)$field->variable);
        if ($variable === '') {
            $variable = $label;
        }
        if ($mode === self::HEADER_VARIABLE) {
            return $variable !== '' ? $variable : $label;
        }
        if ($mode === self::HEADER_BOTH) {
            if ($variable !== '' && $label !== '' && strcasecmp($variable, $label) !== 0) {
                return $variable . ' — ' . $label;
            }
            return $variable !== '' ? $variable : $label;
        }
        return $label !== '' ? $label : $variable;
    }

    private function formatCell($val): string
    {
        if (!is_array($val)) {
            return (string)$val;
        }
        if (array_is_list($val)) {
            $parts = [];
            foreach ($val as $item) {
                if (is_scalar($item) || $item === null) {
                    $parts[] = (string)$item;
                }
            }
            return implode(', ', $parts);
        }
        return json_encode($val, JSON_UNESCAPED_UNICODE);
    }
}
