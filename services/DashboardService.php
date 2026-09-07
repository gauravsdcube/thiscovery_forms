<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormIntegrityMeta;
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
            $totalQ = FormAnswer::find()->alias('a')->where(['a.form_id' => $formIds] + self::prefixKeys($complete, 'a.'));
            FormIntegrityMeta::scopeIncludedInAnalysis($totalQ);
            $totalAnswers = (int)$totalQ->count();

            $uniqueQ = FormAnswer::find()->alias('a')
                ->where(['a.form_id' => $formIds] + self::prefixKeys($complete, 'a.'))
                ->select('a.created_by')
                ->distinct();
            FormIntegrityMeta::scopeIncludedInAnalysis($uniqueQ);
            $uniqueRespondents = (int)$uniqueQ->count();

            $last7Q = FormAnswer::find()->alias('a')
                ->where(['a.form_id' => $formIds] + self::prefixKeys($complete, 'a.'))
                ->andWhere(['>=', 'a.created_at', date('Y-m-d H:i:s', strtotime('-7 days'))]);
            FormIntegrityMeta::scopeIncludedInAnalysis($last7Q);
            $answersLast7 = (int)$last7Q->count();

            $countsQ = (new Query())
                ->from(['a' => FormAnswer::tableName()])
                ->select(['form_id' => 'a.form_id', 'cnt' => 'COUNT(*)'])
                ->where(['a.form_id' => $formIds, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0])
                ->groupBy('a.form_id')
                ->indexBy('form_id');
            FormIntegrityMeta::scopeIncludedInAnalysis($countsQ);
            $counts = $countsQ->all();

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
        $completeQ = FormAnswer::find()->alias('a')
            ->where(['a.form_id' => $formId, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0]);
        FormIntegrityMeta::scopeIncludedInAnalysis($completeQ);
        $totalAnswers = (int)(clone $completeQ)->count();

        $uniqueQ = FormAnswer::find()->alias('a')
            ->where(['a.form_id' => $formId, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0])
            ->select('a.created_by')
            ->distinct();
        FormIntegrityMeta::scopeIncludedInAnalysis($uniqueQ);
        $uniqueRespondents = (int)$uniqueQ->count();

        $last7Q = FormAnswer::find()->alias('a')
            ->where(['a.form_id' => $formId, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0])
            ->andWhere(['>=', 'a.created_at', date('Y-m-d H:i:s', strtotime('-7 days'))]);
        FormIntegrityMeta::scopeIncludedInAnalysis($last7Q);
        $answersLast7 = (int)$last7Q->count();

        $inProgress = (int)$form->getInProgressAnswers()->count();
        $excludedFromAnalysis = (int)FormIntegrityMeta::find()->alias('m')
            ->innerJoin(['a' => FormAnswer::tableName()], 'a.id = m.answer_id')
            ->andWhere([
                'm.form_id' => $formId,
                'm.analysis_status' => FormIntegrityMeta::ANALYSIS_EXCLUDED,
                'a.status' => FormAnswer::STATUS_COMPLETE,
                'a.is_test' => 0,
            ])
            ->count();

        $fieldCount = 0;
        foreach ($form->fields as $field) {
            if ($field->collectsAnswer()) {
                $fieldCount++;
            }
        }
        $answeredFieldQ = (new Query())
            ->from(['af' => FormAnswerField::tableName()])
            ->innerJoin(['a' => FormAnswer::tableName()], 'a.id = af.answer_id')
            ->where(['a.form_id' => $formId, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0])
            ->andWhere(['and',
                ['IS NOT', 'af.value', null],
                ['<>', 'af.value', ''],
                ['<>', 'af.value', '[]'],
            ]);
        FormIntegrityMeta::scopeIncludedInAnalysis($answeredFieldQ);
        $answeredFieldRows = (int)$answeredFieldQ->count();

        $avgFieldsAnswered = ($totalAnswers > 0 && $fieldCount > 0)
            ? round($answeredFieldRows / $totalAnswers, 1)
            : 0;

        return [
            'totalAnswers' => $totalAnswers,
            'inProgress' => $inProgress,
            'uniqueRespondents' => $uniqueRespondents,
            'answersLast7' => $answersLast7,
            'excludedFromAnalysis' => $excludedFromAnalysis,
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
            $completedQ = FormAnswer::find()->alias('a')
                ->where(['a.form_id' => $form->id, 'a.wave_id' => $wave->id, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0]);
            FormIntegrityMeta::scopeIncludedInAnalysis($completedQ);
            $completed = (int)$completedQ->count();
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
            $completedQ = FormAnswer::find()->alias('a')
                ->where(['a.form_id' => $form->id, 'a.round_id' => $round->id, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0]);
            FormIntegrityMeta::scopeIncludedInAnalysis($completedQ);
            $completed = (int)$completedQ->count();
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

        $rowsQ = (new Query())
            ->from(['a' => FormAnswer::tableName()])
            ->select([
                'day' => new Expression('DATE(a.created_at)'),
                'cnt' => 'COUNT(*)',
            ])
            ->where(['a.form_id' => $formIds, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0])
            ->andWhere(['>=', 'a.created_at', date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'))])
            ->groupBy(new Expression('DATE(a.created_at)'));
        FormIntegrityMeta::scopeIncludedInAnalysis($rowsQ);
        $rows = $rowsQ->all();

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

                $values = $this->fieldRawValues($form, $field);

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

                $values = $this->fieldRawValues($form, $field);

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

            $values = $this->fieldRawValues($form, $field);

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
        $q = (new Query())
            ->from(['af' => FormAnswerField::tableName()])
            ->innerJoin(['a' => FormAnswer::tableName()], 'a.id = af.answer_id')
            ->select(['af.value'])
            ->where(['a.form_id' => $form->id, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0, 'af.field_id' => $field->id]);
        FormIntegrityMeta::scopeIncludedInAnalysis($q);
        return $q->column();
    }

    /**
     * @param array<string, mixed> $conds
     * @return array<string, mixed>
     */
    private static function prefixKeys(array $conds, string $prefix): array
    {
        $out = [];
        foreach ($conds as $key => $value) {
            $out[$prefix . $key] = $value;
        }
        return $out;
    }

    private function decodeJson($raw)
    {
        $decoded = json_decode((string)$raw, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
    }

    private function gridChart(FormField $field, CustomForm $form): ?array
    {
        $cfg = $field->getGridConfig();
        $rowMeta = [];
        foreach ($cfg['rows'] as $row) {
            if (is_array($row)) {
                $value = (string)($row['value'] ?? $row['label'] ?? '');
                $label = (string)($row['label'] ?? $value);
                $aliases = array_values(array_filter([
                    (string)($row['value'] ?? ''),
                    (string)($row['code'] ?? ''),
                    (string)($row['label'] ?? ''),
                ], static fn($k) => $k !== ''));
            } else {
                $value = (string)$row;
                $label = $value;
                $aliases = [$value];
            }
            if ($value === '') {
                continue;
            }
            $rowMeta[] = ['value' => $value, 'label' => $label, 'aliases' => $aliases];
        }
        $colMeta = [];
        foreach ($cfg['columns'] as $col) {
            if (is_array($col)) {
                $value = (string)($col['value'] ?? $col['label'] ?? '');
                $label = (string)($col['label'] ?? $value);
                $aliases = array_values(array_filter([
                    (string)($col['value'] ?? ''),
                    (string)($col['code'] ?? ''),
                    (string)($col['label'] ?? ''),
                ], static fn($k) => $k !== ''));
            } else {
                $value = (string)$col;
                $label = $value;
                $aliases = [$value];
            }
            if ($value === '') {
                continue;
            }
            $colMeta[] = ['value' => $value, 'label' => $label, 'aliases' => $aliases];
        }
        if (!$rowMeta || !$colMeta) {
            return null;
        }
        $colKeys = array_column($colMeta, 'value');
        $totals = [];
        foreach ($rowMeta as $row) {
            $totals[$row['value']] = array_fill_keys($colKeys, 0);
        }
        $n = 0;
        foreach ($this->fieldRawValues($form, $field) as $raw) {
            $decoded = $this->decodeJson($raw);
            if (!is_array($decoded)) {
                continue;
            }
            $n++;
            foreach ($rowMeta as $row) {
                $cell = null;
                foreach ($row['aliases'] as $alias) {
                    if (array_key_exists($alias, $decoded)) {
                        $cell = $decoded[$alias];
                        break;
                    }
                }
                $picked = is_array($cell) ? $cell : (($cell !== null && $cell !== '') ? [$cell] : []);
                foreach ($picked as $col) {
                    $col = (string)$col;
                    foreach ($colMeta as $cm) {
                        if (in_array($col, $cm['aliases'], true)) {
                            $totals[$row['value']][$cm['value']]++;
                            break;
                        }
                    }
                }
            }
        }
        if ($n === 0) {
            return null;
        }
        $datasets = [];
        foreach ($colMeta as $col) {
            $series = [];
            foreach ($rowMeta as $row) {
                $series[] = (int)($totals[$row['value']][$col['value']] ?? 0);
            }
            $datasets[] = ['label' => $col['label'], 'data' => $series];
        }
        return [
            'fieldId' => $field->id,
            'label' => $field->label,
            'type' => $field->type,
            'chartType' => 'ranking',
            'labels' => array_column($rowMeta, 'label'),
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
            $answeredQ = (new Query())
                ->from(['af' => FormAnswerField::tableName()])
                ->innerJoin(['a' => FormAnswer::tableName()], 'a.id = af.answer_id')
                ->where(['a.form_id' => $form->id, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0, 'af.field_id' => $field->id])
                ->andWhere(['and',
                    ['IS NOT', 'af.value', null],
                    ['<>', 'af.value', ''],
                    ['<>', 'af.value', '[]'],
                ]);
            FormIntegrityMeta::scopeIncludedInAnalysis($answeredQ);
            $answered = (int)$answeredQ->count();

            $rates[] = [
                'label' => $field->label,
                'answered' => $answered,
                'rate' => round(($answered / $totalAnswers) * 100),
            ];
        }

        return $rates;
    }
}
