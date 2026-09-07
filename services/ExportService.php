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
        $fh = fopen('php://temp', 'r+');

        $header = [
            Yii::t('ThiscoveryFormsModule.base', 'Answer ID'),
            Yii::t('ThiscoveryFormsModule.base', 'Status'),
            Yii::t('ThiscoveryFormsModule.base', 'User'),
            Yii::t('ThiscoveryFormsModule.base', 'Submitted at'),
            Yii::t('ThiscoveryFormsModule.base', 'Updated at'),
            Yii::t('ThiscoveryFormsModule.base', 'Quality score'),
            Yii::t('ThiscoveryFormsModule.base', 'Integrity status'),
            Yii::t('ThiscoveryFormsModule.base', 'Analysis status'),
            Yii::t('ThiscoveryFormsModule.base', 'Quality flags'),
        ];
        if ($form->usesWaves()) {
            $header[] = Yii::t('ThiscoveryFormsModule.base', 'Wave');
        }
        if ($form->isEq5d()) {
            $header[] = Yii::t('ThiscoveryFormsModule.base', 'Health profile');
            $header[] = Yii::t('ThiscoveryFormsModule.base', 'VAS (blank = 999)');
        }
        if ($form->isConsensus()) {
            $header[] = Yii::t('ThiscoveryFormsModule.base', 'Round');
            $header[] = Yii::t('ThiscoveryFormsModule.base', 'Weight');
        }
        foreach ($fields as $field) {
            $header[] = $this->fieldHeader($field, $headerMode);
            if ($field->supportsJustification()) {
                $header[] = $this->fieldHeader($field, $headerMode) . ' — ' . Yii::t('ThiscoveryFormsModule.base', 'Comment');
            }
        }
        fputcsv($fh, $header);

        /** @var FormAnswer $answer */
        foreach ($query->each(100) as $answer) {
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
            $row = [
                $answer->id,
                $status,
                $answer->getSubmitterDisplayName($form),
                $answer->created_at,
                $answer->updated_at,
                $meta ? $meta->overall_score : '',
                $meta ? $meta->getStatusLabel() : '',
                $meta ? $meta->getAnalysisLabel() : '',
                implode('; ', $flagParts),
            ];
            $map = $answer->getValuesMap();
            $just = $answer->getJustificationsMap();
            if ($form->usesWaves()) {
                $row[] = $answer->wave ? $answer->wave->getDisplayTitle() : '';
            }
            if ($form->isEq5d()) {
                $score = (new Eq5dService())->score($form, $map);
                $row[] = $score['profile'];
                $row[] = $score['vas'];
            }
            if ($form->isConsensus()) {
                $row[] = $answer->round ? $answer->round->getDisplayTitle() : '';
                $row[] = $answer->weight;
            }
            foreach ($fields as $field) {
                $val = $map[$field->id] ?? '';
                if ($field->type === FormField::TYPE_FILE && is_string($val) && $val !== '') {
                    $file = File::findOne(['guid' => $val]);
                    $row[] = $file ? $file->file_name : $val;
                } elseif ($field->type === FormField::TYPE_RESPONDENT_META) {
                    $row[] = (new RespondentMetaService())->formatDisplay($val);
                } else {
                    $row[] = $this->formatCell($val);
                }
                if ($field->supportsJustification()) {
                    $row[] = (string)($just[$field->id] ?? '');
                }
            }
            fputcsv($fh, $row);
        }

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return $csv === false ? '' : $csv;
    }

    private function fieldHeader(FormField $field, string $mode): string
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
