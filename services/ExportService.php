<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use Yii;

class ExportService
{
    public function toCsv(CustomForm $form): string
    {
        $fields = array_values(array_filter($form->fields, static fn($f) => $f->collectsAnswer()));
        $fh = fopen('php://temp', 'r+');

        $header = [
            Yii::t('ThiscoveryFormsModule.base', 'Answer ID'),
            Yii::t('ThiscoveryFormsModule.base', 'User'),
            Yii::t('ThiscoveryFormsModule.base', 'Submitted at'),
            Yii::t('ThiscoveryFormsModule.base', 'Updated at'),
        ];
        foreach ($fields as $field) {
            $header[] = $field->label;
        }
        fputcsv($fh, $header);

        /** @var FormAnswer $answer */
        foreach ($form->getAnswers()->with(['answerFields', 'user'])->each(100) as $answer) {
            $row = [
                $answer->id,
                $answer->user ? $answer->user->displayName : ($answer->isAnonymous()
                    ? Yii::t('ThiscoveryFormsModule.base', 'Anonymous')
                    : $answer->created_by),
                $answer->created_at,
                $answer->updated_at,
            ];
            $map = $answer->getValuesMap();
            foreach ($fields as $field) {
                $val = $map[$field->id] ?? '';
                $row[] = is_array($val) ? implode(', ', $val) : (string)$val;
            }
            fputcsv($fh, $row);
        }

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return $csv === false ? '' : $csv;
    }
}
