<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryForms\models\FormField;
use yii\db\Expression;
use yii\db\Query;

class DashboardService
{
    public const STRUCTURED_TYPES = [
        FormField::TYPE_DROPDOWN,
        FormField::TYPE_RADIO,
        FormField::TYPE_CHECKBOX,
        FormField::TYPE_RATING,
        FormField::TYPE_RANKING,
    ];

    /**
     * Overview metrics for a list of forms.
     * @param CustomForm[] $forms
     */
    public function getOverview(array $forms): array
    {
        $formIds = array_map(static fn(CustomForm $f) => (int)$f->id, $forms);
        $totalForms = count($formIds);
        $openForms = 0;
        foreach ($forms as $form) {
            if ($form->isOpen()) {
                $openForms++;
            }
        }

        $totalAnswers = 0;
        $uniqueRespondents = 0;
        $answersLast7 = 0;
        $perForm = [];

        if ($formIds) {
            $complete = ['status' => FormAnswer::STATUS_COMPLETE];
            $totalAnswers = (int)FormAnswer::find()->where(['form_id' => $formIds] + $complete)->count();
            $uniqueRespondents = (int)FormAnswer::find()
                ->where(['form_id' => $formIds] + $complete)
                ->select('created_by')
                ->distinct()
                ->count();
            $answersLast7 = (int)FormAnswer::find()
                ->where(['form_id' => $formIds] + $complete)
                ->andWhere(['>=', 'created_at', date('Y-m-d H:i:s', strtotime('-7 days'))])
                ->count();

            $counts = (new Query())
                ->from(FormAnswer::tableName())
                ->select(['form_id', 'cnt' => 'COUNT(*)'])
                ->where(['form_id' => $formIds, 'status' => FormAnswer::STATUS_COMPLETE])
                ->groupBy('form_id')
                ->indexBy('form_id')
                ->all();

            foreach ($forms as $form) {
                $perForm[] = [
                    'id' => $form->id,
                    'title' => $form->title,
                    'status' => $form->status,
                    'answers' => (int)($counts[$form->id]['cnt'] ?? 0),
                    'url' => Url::toView($form),
                    'dashboardUrl' => Url::toDashboard($form),
                ];
            }

            usort($perForm, static fn($a, $b) => $b['answers'] <=> $a['answers']);
        }

        return [
            'totalForms' => $totalForms,
            'openForms' => $openForms,
            'totalAnswers' => $totalAnswers,
            'uniqueRespondents' => $uniqueRespondents,
            'answersLast7' => $answersLast7,
            'perForm' => $perForm,
            'timeline' => $formIds ? $this->getTimeline($formIds, 14) : ['labels' => [], 'data' => []],
        ];
    }

    public function getFormDashboard(CustomForm $form): array
    {
        $formId = (int)$form->id;
        $totalAnswers = (int)$form->getAnswers()->count();
        $uniqueRespondents = (int)FormAnswer::find()
            ->where(['form_id' => $formId, 'status' => FormAnswer::STATUS_COMPLETE])
            ->select('created_by')
            ->distinct()
            ->count();
        $answersLast7 = (int)FormAnswer::find()
            ->where(['form_id' => $formId, 'status' => FormAnswer::STATUS_COMPLETE])
            ->andWhere(['>=', 'created_at', date('Y-m-d H:i:s', strtotime('-7 days'))])
            ->count();

        $fieldCount = 0;
        foreach ($form->fields as $field) {
            if ($field->collectsAnswer()) {
                $fieldCount++;
            }
        }
        $answeredFieldRows = (int)(new Query())
            ->from(['af' => FormAnswerField::tableName()])
            ->innerJoin(['a' => FormAnswer::tableName()], 'a.id = af.answer_id')
            ->where(['a.form_id' => $formId, 'a.status' => FormAnswer::STATUS_COMPLETE])
            ->andWhere(['and',
                ['IS NOT', 'af.value', null],
                ['<>', 'af.value', ''],
                ['<>', 'af.value', '[]'],
            ])
            ->count();

        $avgFieldsAnswered = ($totalAnswers > 0 && $fieldCount > 0)
            ? round($answeredFieldRows / $totalAnswers, 1)
            : 0;

        return [
            'totalAnswers' => $totalAnswers,
            'uniqueRespondents' => $uniqueRespondents,
            'answersLast7' => $answersLast7,
            'fieldCount' => $fieldCount,
            'avgFieldsAnswered' => $avgFieldsAnswered,
            'completionRate' => ($fieldCount > 0 && $totalAnswers > 0)
                ? round(($avgFieldsAnswered / $fieldCount) * 100)
                : 0,
            'timeline' => $this->getTimeline([$formId], 14),
            'structured' => $this->getStructuredBreakdowns($form),
            'fieldResponseRates' => $this->getFieldResponseRates($form, $totalAnswers),
        ];
    }

    /**
     * @param int[] $formIds
     */
    public function getTimeline(array $formIds, int $days = 14): array
    {
        $labels = [];
        $map = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = $day;
            $map[$day] = 0;
        }

        if (!$formIds) {
            return ['labels' => $labels, 'data' => array_values($map)];
        }

        $rows = (new Query())
            ->from(FormAnswer::tableName())
            ->select([
                'day' => new Expression('DATE(created_at)'),
                'cnt' => 'COUNT(*)',
            ])
            ->where(['form_id' => $formIds, 'status' => FormAnswer::STATUS_COMPLETE])
            ->andWhere(['>=', 'created_at', date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'))])
            ->groupBy(new Expression('DATE(created_at)'))
            ->all();

        foreach ($rows as $row) {
            $day = $row['day'];
            if (isset($map[$day])) {
                $map[$day] = (int)$row['cnt'];
            }
        }

        return [
            'labels' => $labels,
            'data' => array_values($map),
        ];
    }

    public function getStructuredBreakdowns(CustomForm $form): array
    {
        $charts = [];
        foreach ($form->fields as $field) {
            if (!in_array($field->type, self::STRUCTURED_TYPES, true)) {
                continue;
            }

            if ($field->type === FormField::TYPE_RATING) {
                $scale = $field->getRatingScale();
                $min = (int)$scale['min'];
                $max = (int)$scale['max'];
                $step = max(1, (int)$scale['step']);
                $labels = [];
                $counts = [];
                for ($value = $min; $value <= $max; $value += $step) {
                    $labels[] = (string)$value;
                    $counts[(string)$value] = 0;
                }

                $values = (new Query())
                    ->from(['af' => FormAnswerField::tableName()])
                    ->innerJoin(['a' => FormAnswer::tableName()], 'a.id = af.answer_id')
                    ->select(['af.value'])
                    ->where(['a.form_id' => $form->id, 'a.status' => FormAnswer::STATUS_COMPLETE, 'af.field_id' => $field->id])
                    ->column();

                foreach ($values as $raw) {
                    $item = (string)$raw;
                    if ($item === '' || !array_key_exists($item, $counts)) {
                        continue;
                    }
                    $counts[$item]++;
                }

                $data = array_values($counts);
                $total = array_sum($data);
                if ($total === 0) {
                    continue;
                }

                $charts[] = [
                    'fieldId' => $field->id,
                    'label' => $field->label,
                    'type' => $field->type,
                    'chartType' => 'bar',
                    'labels' => $labels,
                    'data' => $data,
                    'total' => $total,
                ];
                continue;
            }

            if ($field->type === FormField::TYPE_RANKING) {
                $options = $field->getOptions();
                $positionTotals = array_fill(0, count($options), array_fill_keys($options, 0));

                $values = (new Query())
                    ->from(['af' => FormAnswerField::tableName()])
                    ->innerJoin(['a' => FormAnswer::tableName()], 'a.id = af.answer_id')
                    ->select(['af.value'])
                    ->where(['a.form_id' => $form->id, 'a.status' => FormAnswer::STATUS_COMPLETE, 'af.field_id' => $field->id])
                    ->column();

                foreach ($values as $raw) {
                    $decoded = json_decode((string)$raw, true);
                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                        continue;
                    }
                    foreach ($decoded as $position => $item) {
                        $item = (string)$item;
                        if (!isset($positionTotals[$position][$item])) {
                            continue;
                        }
                        $positionTotals[$position][$item]++;
                    }
                }

                $datasets = [];
                foreach ($options as $option) {
                    $series = [];
                    foreach ($positionTotals as $positionCounts) {
                        $series[] = (int)($positionCounts[$option] ?? 0);
                    }
                    $datasets[] = [
                        'label' => $option,
                        'data' => $series,
                    ];
                }

                $positionLabels = [];
                for ($i = 0; $i < count($options); $i++) {
                    $positionLabels[] = '#' . ($i + 1);
                }

                $total = count($values);
                if ($total === 0) {
                    continue;
                }

                $charts[] = [
                    'fieldId' => $field->id,
                    'label' => $field->label,
                    'type' => $field->type,
                    'chartType' => 'ranking',
                    'labels' => $positionLabels,
                    'datasets' => $datasets,
                    'total' => $total,
                ];
                continue;
            }

            $options = $field->getOptions();
            $counts = array_fill_keys($options, 0);
            $other = 0;

            $values = (new Query())
                ->from(['af' => FormAnswerField::tableName()])
                ->innerJoin(['a' => FormAnswer::tableName()], 'a.id = af.answer_id')
                ->select(['af.value'])
                ->where(['a.form_id' => $form->id, 'a.status' => FormAnswer::STATUS_COMPLETE, 'af.field_id' => $field->id])
                ->column();

            foreach ($values as $raw) {
                $decoded = json_decode((string)$raw, true);
                $items = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                    ? $decoded
                    : [(string)$raw];

                foreach ($items as $item) {
                    $item = (string)$item;
                    if ($item === '') {
                        continue;
                    }
                    if (array_key_exists($item, $counts)) {
                        $counts[$item]++;
                    } else {
                        $other++;
                    }
                }
            }

            $labels = array_keys($counts);
            $data = array_values($counts);
            if ($other > 0) {
                $labels[] = 'Other';
                $data[] = $other;
            }

            $total = array_sum($data);
            if ($total === 0) {
                continue;
            }

            $charts[] = [
                'fieldId' => $field->id,
                'label' => $field->label,
                'type' => $field->type,
                'chartType' => $field->type === FormField::TYPE_CHECKBOX ? 'bar' : 'pie',
                'labels' => $labels,
                'data' => $data,
                'total' => $total,
            ];
        }

        return $charts;
    }

    public function getFieldResponseRates(CustomForm $form, int $totalAnswers): array
    {
        if ($totalAnswers < 1) {
            return [];
        }

        $rates = [];
        foreach ($form->fields as $field) {
            if (!$field->collectsAnswer()) {
                continue;
            }
            $answered = (int)(new Query())
                ->from(['af' => FormAnswerField::tableName()])
                ->innerJoin(['a' => FormAnswer::tableName()], 'a.id = af.answer_id')
                ->where(['a.form_id' => $form->id, 'a.status' => FormAnswer::STATUS_COMPLETE, 'af.field_id' => $field->id])
                ->andWhere(['and',
                    ['IS NOT', 'af.value', null],
                    ['<>', 'af.value', ''],
                    ['<>', 'af.value', '[]'],
                ])
                ->count();

            $rates[] = [
                'label' => $field->label,
                'answered' => $answered,
                'rate' => round(($answered / $totalAnswers) * 100),
            ];
        }

        return $rates;
    }
}
