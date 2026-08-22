<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
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
        FormField::TYPE_GRID_SINGLE,
        FormField::TYPE_GRID_MULTI,
        FormField::TYPE_BEST_WORST,
        FormField::TYPE_MAXDIFF,
        FormField::TYPE_DRILLDOWN,
        FormField::TYPE_IMAGE_AREA,
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
            $complete = ['status' => FormAnswer::STATUS_COMPLETE, 'is_test' => 0];
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
                ->where(['form_id' => $formIds, 'status' => FormAnswer::STATUS_COMPLETE, 'is_test' => 0])
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
            ->where(['form_id' => $formId, 'status' => FormAnswer::STATUS_COMPLETE, 'is_test' => 0])
            ->select('created_by')
            ->distinct()
            ->count();
        $answersLast7 = (int)FormAnswer::find()
            ->where(['form_id' => $formId, 'status' => FormAnswer::STATUS_COMPLETE, 'is_test' => 0])
            ->andWhere(['>=', 'created_at', date('Y-m-d H:i:s', strtotime('-7 days'))])
            ->count();
        $inProgress = (int)$form->getInProgressAnswers()->count();

        $fieldCount = 0;
        foreach ($form->fields as $field) {
            if ($field->collectsAnswer()) {
                $fieldCount++;
            }
        }
        $answeredFieldRows = (int)(new Query())
            ->from(['af' => FormAnswerField::tableName()])
            ->innerJoin(['a' => FormAnswer::tableName()], 'a.id = af.answer_id')
            ->where(['a.form_id' => $formId, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0])
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
            'inProgress' => $inProgress,
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
            'waves' => $form->usesWaves() ? $this->getWaveStats($form) : [],
            'rounds' => $form->isConsensus() ? $this->getRoundStats($form) : [],
        ];
    }

    public function getWaveStats(CustomForm $form): array
    {
        $panelId = (int)$form->getSetting('panel_id', 0);
        $memberCount = $panelId
            ? (int)FormPanelMember::find()->where(['panel_id' => $panelId, 'status' => FormPanelMember::STATUS_ACTIVE])->count()
            : 0;
        $out = [];
        $prevCompleted = null;
        foreach ((new WaveService())->listWaves($form) as $wave) {
            $completed = (int)FormAnswer::find()
                ->where(['form_id' => $form->id, 'wave_id' => $wave->id, 'status' => FormAnswer::STATUS_COMPLETE, 'is_test' => 0])
                ->count();
            $dropOff = ($prevCompleted !== null && $prevCompleted > 0)
                ? round((1 - ($completed / $prevCompleted)) * 100)
                : null;
            $out[] = [
                'title' => $wave->getDisplayTitle(),
                'status' => $wave->status,
                'completed' => $completed,
                'memberCount' => $memberCount,
                'rate' => $memberCount > 0 ? (int)round(($completed / $memberCount) * 100) : 0,
                'dropOff' => $dropOff,
            ];
            $prevCompleted = $completed;
        }
        return $out;
    }

    public function getRoundStats(CustomForm $form): array
    {
        $out = [];
        foreach ($form->rounds as $round) {
            $completed = (int)FormAnswer::find()
                ->where(['form_id' => $form->id, 'round_id' => $round->id, 'status' => FormAnswer::STATUS_COMPLETE, 'is_test' => 0])
                ->count();
            $out[] = [
                'title' => $round->getDisplayTitle(),
                'status' => $round->status,
                'completed' => $completed,
                'published' => $round->hasPublishedSummary(),
                'frozen' => count($round->getFrozenFieldIds()),
            ];
        }
        return $out;
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
            ->where(['form_id' => $formIds, 'status' => FormAnswer::STATUS_COMPLETE, 'is_test' => 0])
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
                    ->where(['a.form_id' => $form->id, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0, 'af.field_id' => $field->id])
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
                    ->where(['a.form_id' => $form->id, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0, 'af.field_id' => $field->id])
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
                        'label' => $field->optionLabel((string)$option),
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

            if (in_array($field->type, [FormField::TYPE_GRID_SINGLE, FormField::TYPE_GRID_MULTI], true)) {
                $chart = $this->gridChart($field, $form);
                if ($chart) {
                    $charts[] = $chart;
                }
                continue;
            }
            if ($field->type === FormField::TYPE_BEST_WORST) {
                $chart = $this->bestWorstChart($field, $form);
                if ($chart) {
                    $charts[] = $chart;
                }
                continue;
            }
            if ($field->type === FormField::TYPE_MAXDIFF) {
                $chart = $this->maxDiffChart($field, $form);
                if ($chart) {
                    $charts[] = $chart;
                }
                continue;
            }
            if ($field->type === FormField::TYPE_IMAGE_AREA) {
                $chart = $this->imageAreaChart($field, $form);
                if ($chart) {
                    $charts[] = $chart;
                }
                continue;
            }
            if ($field->type === FormField::TYPE_DRILLDOWN) {
                $chart = $this->pathChart($field, $form);
                if ($chart) {
                    $charts[] = $chart;
                }
                continue;
            }

            $options = $field->getOptions();
            $counts = array_fill_keys($options, 0);
            $other = 0;

            $values = (new Query())
                ->from(['af' => FormAnswerField::tableName()])
                ->innerJoin(['a' => FormAnswer::tableName()], 'a.id = af.answer_id')
                ->select(['af.value'])
                ->where(['a.form_id' => $form->id, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0, 'af.field_id' => $field->id])
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
                        continue;
                    }
                    $matchedOther = false;
                    foreach ($options as $opt) {
                        if ($field->choiceMatchesExpected($item, (string)$opt)) {
                            $counts[(string)$opt]++;
                            $matchedOther = true;
                            break;
                        }
                    }
                    if (!$matchedOther) {
                        $other++;
                    }
                }
            }

            $labels = array_map(static fn($code) => $field->optionLabel((string)$code), array_keys($counts));
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

    public function getPollResults(CustomForm $form): array
    {
        $charts = $this->getStructuredBreakdowns($form);
        $chart = $charts[0] ?? null;
        $options = [];
        $total = 0;
        $label = '';
        if ($chart) {
            $label = (string)($chart['label'] ?? '');
            $labels = $chart['labels'] ?? [];
            $data = $chart['data'] ?? [];
            $total = (int)($chart['total'] ?? array_sum($data));
            foreach ($labels as $i => $optLabel) {
                $options[] = [
                    'label' => (string)$optLabel,
                    'count' => (int)($data[$i] ?? 0),
                ];
            }
        } else {
            $question = $form->getPollQuestion();
            if ($question) {
                $label = $question->label;
                foreach ($question->getChoicePairs() as $pair) {
                    $options[] = ['label' => $pair['label'], 'count' => 0];
                }
            }
        }

        return [
            'label' => $label,
            'options' => $options,
            'total' => $total,
        ];
    }

    private function fieldRawValues(CustomForm $form, FormField $field): array
    {
        return (new Query())
            ->from(['af' => FormAnswerField::tableName()])
            ->innerJoin(['a' => FormAnswer::tableName()], 'a.id = af.answer_id')
            ->select(['af.value'])
            ->where(['a.form_id' => $form->id, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0, 'af.field_id' => $field->id])
            ->column();
    }

    private function decodeJson($raw)
    {
        $decoded = json_decode((string)$raw, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
    }

    private function gridChart(FormField $field, CustomForm $form): ?array
    {
        $cfg = $field->getGridConfig();
        $rows = $cfg['rows'];
        $cols = $cfg['columns'];
        if (!$rows || !$cols) {
            return null;
        }
        $totals = [];
        foreach ($rows as $row) {
            $totals[$row] = array_fill_keys($cols, 0);
        }
        $n = 0;
        foreach ($this->fieldRawValues($form, $field) as $raw) {
            $decoded = $this->decodeJson($raw);
            if (!is_array($decoded)) {
                continue;
            }
            $n++;
            foreach ($rows as $row) {
                $cell = $decoded[$row] ?? null;
                $picked = is_array($cell) ? $cell : (($cell !== null && $cell !== '') ? [$cell] : []);
                foreach ($picked as $col) {
                    if (isset($totals[$row][(string)$col])) {
                        $totals[$row][(string)$col]++;
                    }
                }
            }
        }
        if ($n === 0) {
            return null;
        }
        $datasets = [];
        foreach ($cols as $col) {
            $series = [];
            foreach ($rows as $row) {
                $series[] = (int)($totals[$row][$col] ?? 0);
            }
            $datasets[] = ['label' => $col, 'data' => $series];
        }
        return [
            'fieldId' => $field->id,
            'label' => $field->label,
            'type' => $field->type,
            'chartType' => 'ranking',
            'labels' => $rows,
            'datasets' => $datasets,
            'total' => $n,
        ];
    }

    private function bestWorstChart(FormField $field, CustomForm $form): ?array
    {
        $items = $field->getItemsConfig()['items'];
        if (!$items) {
            return null;
        }
        $best = array_fill_keys($items, 0);
        $worst = array_fill_keys($items, 0);
        $n = 0;
        foreach ($this->fieldRawValues($form, $field) as $raw) {
            $decoded = $this->decodeJson($raw);
            if (!is_array($decoded)) {
                continue;
            }
            $n++;
            $b = (string)($decoded['best'] ?? '');
            $w = (string)($decoded['worst'] ?? '');
            if (isset($best[$b])) {
                $best[$b]++;
            }
            if (isset($worst[$w])) {
                $worst[$w]++;
            }
        }
        if ($n === 0) {
            return null;
        }
        return [
            'fieldId' => $field->id,
            'label' => $field->label,
            'type' => $field->type,
            'chartType' => 'ranking',
            'labels' => $items,
            'datasets' => [
                ['label' => 'Best', 'data' => array_values($best)],
                ['label' => 'Worst', 'data' => array_values($worst)],
            ],
            'total' => $n,
        ];
    }

    private function maxDiffChart(FormField $field, CustomForm $form): ?array
    {
        $items = $field->getItemsConfig()['items'];
        if (!$items) {
            return null;
        }
        $pairs = [];
        $n = 0;
        foreach ($this->fieldRawValues($form, $field) as $raw) {
            $decoded = $this->decodeJson($raw);
            if (!is_array($decoded)) {
                continue;
            }
            $n++;
            $sets = $decoded['sets'] ?? $decoded;
            if (!is_array($sets)) {
                continue;
            }
            foreach ($sets as $pair) {
                if (is_array($pair)) {
                    $pairs[] = $pair;
                }
            }
        }
        if ($n === 0) {
            return null;
        }
        $scores = (new MaxDiffDesigner())->scores($items, $pairs);
        $labels = [];
        $data = [];
        foreach ($scores as $item => $row) {
            $labels[] = $item;
            $data[] = (int)$row['score'];
        }
        return [
            'fieldId' => $field->id,
            'label' => $field->label,
            'type' => $field->type,
            'chartType' => 'bar',
            'labels' => $labels,
            'data' => $data,
            'total' => $n,
        ];
    }

    private function imageAreaChart(FormField $field, CustomForm $form): ?array
    {
        $cfg = $field->getImageAreaConfig();
        $labels = [];
        $counts = [];
        foreach ($cfg['regions'] as $region) {
            $label = (string)($region['label'] ?? $region['id'] ?? '');
            $labels[] = $label;
            $counts[$label] = 0;
        }
        $n = 0;
        foreach ($this->fieldRawValues($form, $field) as $raw) {
            $decoded = $this->decodeJson($raw);
            if (!is_array($decoded)) {
                continue;
            }
            $n++;
            $picked = $decoded['regions'] ?? $decoded;
            if (!is_array($picked)) {
                continue;
            }
            $byId = [];
            foreach ($cfg['regions'] as $region) {
                $byId[(string)($region['id'] ?? '')] = (string)($region['label'] ?? '');
            }
            foreach ($picked as $id) {
                $label = $byId[(string)$id] ?? (string)$id;
                if (isset($counts[$label])) {
                    $counts[$label]++;
                }
            }
        }
        if ($n === 0) {
            return null;
        }
        return [
            'fieldId' => $field->id,
            'label' => $field->label,
            'type' => $field->type,
            'chartType' => 'bar',
            'labels' => $labels,
            'data' => array_values($counts),
            'total' => $n,
        ];
    }

    private function pathChart(FormField $field, CustomForm $form): ?array
    {
        $counts = [];
        $n = 0;
        foreach ($this->fieldRawValues($form, $field) as $raw) {
            $decoded = $this->decodeJson($raw);
            $path = is_array($decoded) ? $decoded : [(string)$raw];
            $path = array_values(array_filter(array_map('strval', $path), 'strlen'));
            if (!$path) {
                continue;
            }
            $n++;
            $key = implode(' › ', $path);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        if ($n === 0) {
            return null;
        }
        return [
            'fieldId' => $field->id,
            'label' => $field->label,
            'type' => $field->type,
            'chartType' => 'bar',
            'labels' => array_keys($counts),
            'data' => array_values($counts),
            'total' => $n,
        ];
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
                ->where(['a.form_id' => $form->id, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0, 'af.field_id' => $field->id])
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
