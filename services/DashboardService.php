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
                ->select(new Expression("COALESCE(CONCAT('u', a.created_by), CONCAT('m', a.panel_member_id), CONCAT('r', a.id))"))
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

    /**
     * @param bool $public true for the share-link dashboard: loop breakdowns are left out
     *                     entirely, because repeat labels and answers can identify people (V3-8).
     */
    public function getFormDashboard(CustomForm $form, bool $public = false): array
    {
        $formId = (int)$form->id;
        $completeQ = FormAnswer::find()->alias('a')
            ->where(['a.form_id' => $formId, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0])
            ->andWhere(['a.outcome' => ['', FormAnswer::OUTCOME_COMPLETE]]);
        FormIntegrityMeta::scopeIncludedInAnalysis($completeQ);
        $totalAnswers = (int)(clone $completeQ)->count();

        $uniqueQ = FormAnswer::find()->alias('a')
            ->where(['a.form_id' => $formId, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0])
            ->andWhere(['a.outcome' => ['', FormAnswer::OUTCOME_COMPLETE]])
            // A person is an account or a panel member; a guest response counts once each, rather
            // than every guest counting as a single respondent (SCO-18).
            ->select(new Expression("COALESCE(CONCAT('u', a.created_by), CONCAT('m', a.panel_member_id), CONCAT('r', a.id))"))
            ->distinct();
        FormIntegrityMeta::scopeIncludedInAnalysis($uniqueQ);
        $uniqueRespondents = (int)$uniqueQ->count();

        $last7Q = FormAnswer::find()->alias('a')
            ->where(['a.form_id' => $formId, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0])
            ->andWhere(['a.outcome' => ['', FormAnswer::OUTCOME_COMPLETE]])
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

        $fieldIds = [];
        foreach ($form->getAllFields()->all() as $field) {
            if ($field->collectsAnswer()) {
                $fieldIds[] = (int)$field->id;
            }
        }
        $fieldCount = count($fieldIds);
        $answeredFieldQ = (new Query())
            ->from(['af' => FormAnswerField::tableName()])
            ->innerJoin(['a' => FormAnswer::tableName()], 'a.id = af.answer_id')
            ->where(['a.form_id' => $formId, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0])
            ->andWhere(['a.outcome' => ['', FormAnswer::OUTCOME_COMPLETE]])
            ->andWhere(['af.field_id' => $fieldIds ?: [0]])
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
            'showUniqueRespondents' => !($form->hidesIdentityFromManagers() && \humhub\modules\thiscoveryForms\Module::identityEnforced()),
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
            'arms' => (new RandomisationService())->allocationSummary($form),
            'quotas' => (new QuotaService())->summary($form),
            'loops' => $public ? [] : $this->loopBreakdown($form),
            'numeric' => $public ? [] : $this->numericSummaries($form),
        ];
    }

    public function getWaveStats(CustomForm $form): array
    {
        $panelId = (int)$form->getSetting('panel_id', 0);
        $memberCount = $panelId
            ? (int)FormPanelMember::find()->where(['panel_id' => $panelId, 'status' => FormPanelMember::STATUS_ACTIVE])->count()
            : 0;
        $out = [];
        $prevMembers = null;
        foreach ((new WaveService())->listWaves($form) as $wave) {
            $completedQ = FormAnswer::find()->alias('a')
                ->where(['a.form_id' => $form->id, 'a.wave_id' => $wave->id, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0]);
            FormIntegrityMeta::scopeIncludedInAnalysis($completedQ);
            $completed = (int)(clone $completedQ)->count();
            $members = array_values(array_filter(array_map('intval', (clone $completedQ)->select('a.panel_member_id')->column())));
            // SCO-17: the rate's base is the members invited to that wave (from the send log),
            // not today's active members; drop-off follows the same people from wave to wave.
            $invited = (int)(new \yii\db\Query())->from('{{%form_email_send}}')
                ->where(['form_id' => (int)$form->id, 'wave_id' => (int)$wave->id, 'kind' => ['invite', 'wave']])
                ->select('member_id')->distinct()->count();
            $base = $invited > 0 ? $invited : $memberCount;
            $dropOff = null;
            if ($prevMembers !== null && $prevMembers !== []) {
                $stayed = count(array_intersect($prevMembers, $members));
                $dropOff = (int)round((1 - $stayed / count($prevMembers)) * 100);
            }
            $out[] = [
                'title' => $wave->getDisplayTitle(),
                'status' => $wave->status,
                'completed' => $completed,
                'memberCount' => $base,
                'rate' => $base > 0 ? (int)round(($completed / $base) * 100) : 0,
                'dropOff' => $dropOff,
            ];
            $prevMembers = $members;
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
        foreach ($form->getAllFields()->all() as $field) {
            if (!in_array($field->type, self::STRUCTURED_TYPES, true)) {
                continue;
            }

            if ($field->type === FormField::TYPE_RATING) {
                $scale = $field->getRatingScale();
                $min = (float)$scale['min'];
                $max = (float)$scale['max'];
                // A step of 0.5 stays 0.5, and an answer like 7.5 is counted, not dropped (SCO-19).
                $step = (float)$scale['step'] > 0 ? (float)$scale['step'] : 1.0;
                $fmt = static fn(float $v): string => rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');
                $labels = [];
                $counts = [];
                for ($i = 0, $value = $min; $value <= $max + 1e-9 && $i < 1000; $i++, $value = $min + $i * $step) {
                    $labels[] = $fmt($value);
                    $counts[$fmt($value)] = 0;
                }
                $offScale = 0;

                $values = $this->fieldRawValues($form, $field);

                foreach ($values as $raw) {
                    if (!is_numeric($raw)) {
                        continue;
                    }
                    $item = $fmt((float)$raw);
                    if (array_key_exists($item, $counts)) {
                        $counts[$item]++;
                    } else {
                        $offScale++;
                    }
                }
                if ($offScale > 0) {
                    $labels[] = Yii::t('ThiscoveryFormsModule.base', 'Other values');
                    $counts['__other'] = $offScale;
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
            $respondents = 0;

            foreach ($values as $raw) {
                $decoded = json_decode((string)$raw, true);
                $items = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                    ? $decoded
                    : [(string)$raw];
                if (array_filter(array_map('strval', $items), 'strlen') !== []) {
                    $respondents++;
                }

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

            if (array_sum($data) === 0) {
                continue;
            }

            // The base is people who answered, not ticks: 10 people ticking 3 options each is
            // "10 responses" and about 33% per option, not 30 and 10% (SCO-10).
            $charts[] = [
                'fieldId' => $field->id,
                'label' => $field->label,
                'type' => $field->type,
                'chartType' => $field->type === FormField::TYPE_CHECKBOX ? 'bar' : 'pie',
                'labels' => $labels,
                'data' => $data,
                'total' => $respondents,
                'selections' => array_sum($data),
                'multi' => $field->type === FormField::TYPE_CHECKBOX,
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
            $setItems = $field->getItemsConfig()['sets'] ?? [];
            foreach ($sets as $index => $pair) {
                if (!is_array($pair)) {
                    continue;
                }
                if (!isset($pair['items']) && isset($setItems[$index]) && is_array($setItems[$index])) {
                    $pair['items'] = $setItems[$index];
                }
                $pairs[] = $pair;
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
            $data[] = $row['score'];
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
        foreach ($form->getAllFields()->all() as $field) {
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
            $answered = (new LoopService())->isLoopField($form, $field)
                ? (int)$answeredQ->count('DISTINCT a.id')
                : (int)$answeredQ->count();

            $rates[] = [
                'label' => $field->label,
                'answered' => $answered,
                'rate' => round(($answered / $totalAnswers) * 100),
            ];
        }

        return $rates;
    }

    /**
     * n, mean, median, quartiles, minimum and maximum for each number question (SCO-20).
     *
     * @return array<int, array<string,mixed>>
     */
    private function numericSummaries(CustomForm $form): array
    {
        $out = [];
        foreach ($form->fields as $field) {
            if ($field->type !== FormField::TYPE_NUMBER || $field->isContainsPii()) {
                continue;
            }
            $nums = [];
            foreach ($this->fieldRawValues($form, $field) as $raw) {
                if (is_numeric($raw)) {
                    $nums[] = (float)$raw;
                }
            }
            if ($nums === []) {
                continue;
            }
            sort($nums);
            $out[] = ['label' => (string)$field->label, 'n' => count($nums)] + self::numericStats($nums);
        }
        return $out;
    }

    /**
     * @param float[] $sorted ascending
     * @return array{mean:float,median:float,p25:float,p75:float,min:float,max:float}
     */
    public static function numericStats(array $sorted): array
    {
        $q = static function (float $p) use ($sorted): float {
            $pos = $p * (count($sorted) - 1);
            $lo = (int)floor($pos);
            $hi = (int)ceil($pos);
            return $sorted[$lo] + ($sorted[$hi] - $sorted[$lo]) * ($pos - $lo);
        };
        return [
            'mean' => round(array_sum($sorted) / count($sorted), 2),
            'median' => round($q(0.5), 2),
            'p25' => round($q(0.25), 2),
            'p75' => round($q(0.75), 2),
            'min' => $sorted[0],
            'max' => $sorted[count($sorted) - 1],
        ];
    }

    /**
     * All repeats added together, plus the same counts for each instance label.
     *
     * @return array{instances:array<int,array{code:string,label:string}>,questions:array<int,array{label:string,counts:array<string,int>,values:array<string,array<string,int>>}>}
     */
    private function loopBreakdown(CustomForm $form): array
    {
        if (!LoopService::active($form)) {
            return [];
        }
        $loops = new LoopService();
        $fields = array_values($form->fields);
        $questions = [];
        $labels = [];
        foreach ($fields as $field) {
            if (!$loops->isLoopField($form, $field)) {
                continue;
            }
            // Only structured answers are aggregated: free text and personal-data questions
            // (for example a roster name) are never shown on a dashboard (V3-8).
            if (!in_array($field->type, self::STRUCTURED_TYPES, true) || $field->isContainsPii()) {
                continue;
            }
            foreach ($loops->columnPaths($fields, $field) as $column) {
                $labels[(string)$column['code']] = (string)$column['label'];
            }
            $questions[(int)$field->id] = [
                'label' => trim(strip_tags((string)$field->label)),
                'counts' => [],
                'values' => [],
            ];
        }
        if ($questions === []) {
            return [];
        }
        // Only repeats still shown to that respondent count: a deselected option's answers stay
        // in the table but are not part of the response (V3-45).
        $answers = FormAnswer::find()->alias('a')
            ->where(['a.form_id' => (int)$form->id, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0])
            ->andWhere(['a.outcome' => ['', FormAnswer::OUTCOME_COMPLETE]])
            ->with('answerFields');
        FormIntegrityMeta::scopeIncludedInAnalysis($answers);
        $fieldsById = [];
        foreach ($fields as $field) {
            $fieldsById[(int)$field->id] = $field;
        }
        $previous = RandomisationService::$current;
        foreach ($answers->each(100) as $answer) {
            $answer->populateRelation('form', $form);
            RandomisationService::$current = $answer;
            $map = $answer->getValuesMap();
            foreach (array_keys($questions) as $id) {
                $cells = is_array($map[$id] ?? null) ? $map[$id] : [];
                if ($cells === []) {
                    continue;
                }
                foreach ($loops->shownPaths($fields, $fieldsById[$id], $map) as $path) {
                    $code = (string)$path['code'];
                    $raw = $cells[$code] ?? null;
                    if ($raw === null || $raw === '' || $raw === []) {
                        continue;
                    }
                    $display = is_array($raw) ? implode(', ', array_map('strval', array_filter($raw, 'is_scalar'))) : trim((string)$raw);
                    if ($display === '') {
                        continue;
                    }
                    if (strlen($display) > 80) {
                        $display = substr($display, 0, 77) . '...';
                    }
                    if (!isset($labels[$code])) {
                        $labels[$code] = (string)($path['label'] ?? $code);
                    }
                    $questions[$id]['counts']['*'] = ($questions[$id]['counts']['*'] ?? 0) + 1;
                    $questions[$id]['counts'][$code] = ($questions[$id]['counts'][$code] ?? 0) + 1;
                    $questions[$id]['values']['*'][$display] = ($questions[$id]['values']['*'][$display] ?? 0) + 1;
                    $questions[$id]['values'][$code][$display] = ($questions[$id]['values'][$code][$display] ?? 0) + 1;
                }
            }
        }
        RandomisationService::$current = $previous;
        $instances = [];
        foreach ($labels as $code => $label) {
            $instances[] = ['code' => $code, 'label' => $label];
        }
        return [
            'instances' => $instances,
            'questions' => array_values($questions),
        ];
    }
}
