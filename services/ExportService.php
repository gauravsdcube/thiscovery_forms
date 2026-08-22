<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\file\models\File;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use Yii;

class ExportService
{
    public function toCsv(CustomForm $form): string
    {
        $fields = array_values(array_filter($form->fields, static fn($f) => $f->collectsAnswer()));
        $fh = fopen('php://temp', 'r+');

        $header = [
            Yii::t('ThiscoveryFormsModule.base', 'Answer ID'),
            Yii::t('ThiscoveryFormsModule.base', 'Status'),
            Yii::t('ThiscoveryFormsModule.base', 'User'),
            Yii::t('ThiscoveryFormsModule.base', 'Submitted at'),
            Yii::t('ThiscoveryFormsModule.base', 'Updated at'),
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
            $header[] = $field->label;
            if ($field->supportsJustification()) {
                $header[] = $field->label . ' — ' . Yii::t('ThiscoveryFormsModule.base', 'Comment');
            }
        }
        fputcsv($fh, $header);

        /** @var FormAnswer $answer */
        foreach ($form->getExportableAnswers()->with(['answerFields', 'user', 'wave', 'round', 'panelMember'])->each(100) as $answer) {
            $status = $answer->isComplete()
                ? Yii::t('ThiscoveryFormsModule.base', 'Complete')
                : Yii::t('ThiscoveryFormsModule.base', 'In progress');
            $row = [
                $answer->id,
                $status,
                $answer->getSubmitterDisplayName($form),
                $answer->created_at,
                $answer->updated_at,
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

    private function formatCell($val): string
    {
        if (!is_array($val)) {
            return (string)$val;
        }
        if (array_is_list($val)) {
            return implode(', ', array_map('strval', $val));
        }
        return json_encode($val, JSON_UNESCAPED_UNICODE);
    }
}
