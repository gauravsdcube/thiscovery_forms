<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormRound;
use Yii;
use yii\db\Query;

class RoundService
{
    public function ensureSetup(CustomForm $form): void
    {
        if (!$form->id || !$form->isConsensus() || $form->isTemplate()) {
            return;
        }
        if (!FormRound::find()->where(['form_id' => $form->id])->exists()) {
            $this->createRound($form, Yii::t('ThiscoveryFormsModule.base', 'Round 1'));
        }
    }

    public function createRound(CustomForm $form, ?string $title = null): FormRound
    {
        $max = (int)FormRound::find()->where(['form_id' => $form->id])->max('round_number');
        $round = new FormRound();
        $round->form_id = $form->id;
        $round->round_number = $max + 1;
        $round->title = $title ?: Yii::t('ThiscoveryFormsModule.base', 'Round {n}', ['n' => $round->round_number]);
        $round->status = FormRound::STATUS_DRAFT;
        $round->save(false);
        return $round;
    }

    public function getCurrentOpen(CustomForm $form): ?FormRound
    {
        $rounds = FormRound::find()
            ->where(['form_id' => $form->id, 'status' => FormRound::STATUS_OPEN])
            ->orderBy(['round_number' => SORT_DESC])
            ->all();
        foreach ($rounds as $round) {
            if ($round->isOpenNow()) {
                return $round;
            }
        }
        return null;
    }

    /**
     * @return FormRound[]
     */
    public function listRounds(CustomForm $form): array
    {
        return FormRound::find()
            ->where(['form_id' => $form->id])
            ->orderBy(['round_number' => SORT_ASC])
            ->all();
    }

    public function previousRound(FormRound $round): ?FormRound
    {
        return FormRound::find()
            ->where(['form_id' => $round->form_id])
            ->andWhere(['<', 'round_number', $round->round_number])
            ->orderBy(['round_number' => SORT_DESC])
            ->one();
    }

    public function setStatus(FormRound $round, string $status): bool
    {
        if (!isset(FormRound::getStatusLabels()[$status])) {
            return false;
        }
        if ($status === FormRound::STATUS_OPEN) {
            FormRound::updateAll(
                ['status' => FormRound::STATUS_CLOSED, 'updated_at' => date('Y-m-d H:i:s')],
                [
                    'and',
                    ['form_id' => $round->form_id],
                    ['status' => FormRound::STATUS_OPEN],
                    ['<>', 'id', $round->id],
                ]
            );
            if (!$round->opens_at) {
                $round->opens_at = date('Y-m-d H:i:s');
            }
        }
        if ($status === FormRound::STATUS_CLOSED && !$round->closes_at) {
            $round->closes_at = date('Y-m-d H:i:s');
        }
        $round->status = $status;
        $ok = $round->save(false, ['status', 'opens_at', 'closes_at', 'updated_at']);
        if ($ok && $status === FormRound::STATUS_CLOSED && $round->form) {
            $this->frozenFor($round->form, $round);
        }
        return $ok;
    }

    /**
     * Items frozen after a round. Worked out when the round closes, whether or not its summary
     * is ever published, and stored; a round closed without one gets it here on first use
     * (SCO-9). Publishing the summary recomputes it.
     *
     * @return int[]
     */
    public function frozenFor(CustomForm $form, FormRound $round): array
    {
        if (!$form->freezesOnConsensus()) {
            return [];
        }
        if ($round->frozen_field_ids_json !== null && $round->frozen_field_ids_json !== '') {
            return $round->getFrozenFieldIds();
        }
        if ((string)$round->status !== FormRound::STATUS_CLOSED) {
            return [];
        }
        $ids = $this->computeFrozenFieldIds($form, $round);
        $round->frozen_field_ids_json = json_encode($ids);
        $round->save(false, ['frozen_field_ids_json', 'updated_at']);
        return $ids;
    }

    public function applyDelphiPreset(CustomForm $form, int $roundCount = 3): void
    {
        $form->require_justification = 1;
        $form->freeze_on_consensus = 1;
        $form->identity_mode = CustomForm::IDENTITY_MANAGERS_ONLY;
        $form->consensus_threshold = $form->consensus_threshold ?: 70;
        $form->save(false);

        $existing = (int)FormRound::find()->where(['form_id' => $form->id])->count();
        for ($i = $existing; $i < max(2, min(8, $roundCount)); $i++) {
            $this->createRound($form);
        }
    }

    public function publishSummary(CustomForm $form, FormRound $round, ?string $html = null): bool
    {
        if ($html === null || trim($html) === '') {
            $html = $this->buildSummaryHtml($form, $round);
        }
        $round->summary_html = (new HtmlSanitizer())->sanitize($html);
        $round->published_at = date('Y-m-d H:i:s');
        if ($form->freezesOnConsensus()) {
            $round->setFrozenFieldIds($this->computeFrozenFieldIds($form, $round));
        }
        return $round->save(false, ['summary_html', 'published_at', 'frozen_field_ids_json', 'updated_at']);
    }

    public function buildSummaryHtml(CustomForm $form, FormRound $round): string
    {
        $parts = [
            '<h3>' . htmlspecialchars(Yii::t('ThiscoveryFormsModule.base', 'Summary of {round}', [
                'round' => $round->getDisplayTitle(),
            ]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h3>',
        ];

        foreach ($form->getAllFields()->all() as $field) {
            if (!in_array($field->type, [FormField::TYPE_RADIO, FormField::TYPE_DROPDOWN, FormField::TYPE_RATING], true)) {
                continue;
            }
            $rows = (new Query())
                ->from(['af' => FormAnswerField::tableName()])
                ->innerJoin(['a' => FormAnswer::tableName()], 'a.id = af.answer_id')
                ->select(['af.value', 'weight' => 'a.weight', 'justification' => 'af.justification'])
                ->where([
                    'a.form_id' => $form->id,
                    'a.round_id' => $round->id,
                    'a.status' => FormAnswer::STATUS_COMPLETE,
                    'a.is_test' => 0,
                    'af.field_id' => $field->id,
                ]);
            // Integrity-excluded, screened-out and over-quota answers do not count (SCO-7, V3-35).
            \humhub\modules\thiscoveryForms\models\FormIntegrityMeta::scopeIncludedInAnalysis($rows);
            $rows = $rows->all();
            if (!$rows) {
                continue;
            }

            $counts = [];
            $weightTotal = 0.0;
            $justifications = [];
            $justTotal = 0;
            foreach ($rows as $row) {
                $val = (string)$row['value'];
                $w = ($row['weight'] === null || $row['weight'] === '') ? 1.0 : (float)$row['weight'];
                if ($val === '') {
                    continue;
                }
                $counts[$val] = ($counts[$val] ?? 0) + $w;
                $weightTotal += $w;
                $just = trim((string)($row['justification'] ?? ''));
                if ($just !== '') {
                    $justTotal++;
                    if (count($justifications) < 40) {
                        $justifications[] = $just;
                    }
                }
            }
            if ($weightTotal <= 0) {
                continue;
            }

            $parts[] = '<h4>' . htmlspecialchars($field->label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h4><ul>';
            arsort($counts);
            // Labels, not codes, and percentages that add up to 100 (largest remainder) (SCO-8).
            $labels = [];
            foreach ($field->getChoicePairs() as $pair) {
                $labels[(string)$pair['code']] = (string)$pair['label'];
            }
            foreach (self::percentages($counts, $weightTotal) as $code => $pct) {
                $label = $labels[(string)$code] ?? (string)$code;
                $parts[] = '<li>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . ' — ' . $pct . '%</li>';
            }
            $parts[] = '</ul>';
            // Where the item stands, and its spread on a numeric scale (SCO-5).
            $consensus = $this->itemConsensus($form, $field, $rows);
            $line = [];
            if ($consensus['status'] === self::CONSENSUS_IN) {
                $line[] = Yii::t('ThiscoveryFormsModule.base', 'Consensus reached (agree).');
            } elseif ($consensus['status'] === self::CONSENSUS_OUT) {
                $line[] = Yii::t('ThiscoveryFormsModule.base', 'Consensus reached (disagree).');
            } else {
                $line[] = Yii::t('ThiscoveryFormsModule.base', 'No consensus yet.');
            }
            if ($consensus['median'] !== null) {
                $line[] = Yii::t('ThiscoveryFormsModule.base', 'Median {m}, interquartile range {iqr}.', [
                    'm' => rtrim(rtrim(number_format((float)$consensus['median'], 2, '.', ''), '0'), '.'),
                    'iqr' => rtrim(rtrim(number_format((float)$consensus['iqr'], 2, '.', ''), '0'), '.'),
                ]);
            }
            $parts[] = '<p>' . htmlspecialchars(implode(' ', $line), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';

            if ($justifications) {
                $parts[] = '<p><strong>' . htmlspecialchars(
                    Yii::t('ThiscoveryFormsModule.base', 'Comments (anonymised)'),
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) . '</strong></p><ul>';
                foreach ($justifications as $just) {
                    $parts[] = '<li>' . htmlspecialchars($just, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
                }
                $parts[] = '</ul>';
                if ($justTotal > count($justifications)) {
                    $parts[] = '<p class="text-muted">' . htmlspecialchars(Yii::t('ThiscoveryFormsModule.base', 'Showing {shown} of {total} comments.', [
                        'shown' => count($justifications),
                        'total' => $justTotal,
                    ]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
                }
            }
        }

        if (count($parts) === 1) {
            $parts[] = '<p>' . htmlspecialchars(
                Yii::t('ThiscoveryFormsModule.base', 'No responses to summarise yet.'),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) . '</p>';
        }

        return implode('', $parts);
    }

    /**
     * Whole percentages that sum to exactly 100 (largest remainder method), in the order given.
     *
     * @param array<string,float> $counts
     * @return array<string,int>
     */
    public static function percentages(array $counts, float $total): array
    {
        if ($total <= 0 || $counts === []) {
            return [];
        }
        $floors = [];
        $remainders = [];
        foreach ($counts as $key => $w) {
            $exact = $w / $total * 100;
            $floors[$key] = (int)floor($exact);
            $remainders[$key] = $exact - floor($exact);
        }
        $left = 100 - array_sum($floors);
        arsort($remainders);
        foreach (array_keys($remainders) as $key) {
            if ($left <= 0) {
                break;
            }
            $floors[$key]++;
            $left--;
        }
        $out = [];
        foreach (array_keys($counts) as $key) {
            $out[$key] = $floors[$key];
        }
        return $out;
    }

    /**
     * @return int[]
     */
    public function computeFrozenFieldIds(CustomForm $form, FormRound $round): array
    {
        $frozen = [];
        foreach ($form->getAllFields()->all() as $field) {
            if (!in_array($field->type, [FormField::TYPE_RADIO, FormField::TYPE_DROPDOWN, FormField::TYPE_RATING], true)) {
                continue;
            }
            $rows = (new Query())
                ->from(['af' => FormAnswerField::tableName()])
                ->innerJoin(['a' => FormAnswer::tableName()], 'a.id = af.answer_id')
                ->select(['af.value', 'weight' => 'a.weight'])
                ->where([
                    'a.form_id' => $form->id,
                    'a.round_id' => $round->id,
                    'a.status' => FormAnswer::STATUS_COMPLETE,
                    'a.is_test' => 0,
                    'af.field_id' => $field->id,
                ]);
            // Integrity-excluded, screened-out and over-quota answers do not count (SCO-7, V3-35).
            \humhub\modules\thiscoveryForms\models\FormIntegrityMeta::scopeIncludedInAnalysis($rows);
            $rows = $rows->all();
            $result = $this->itemConsensus($form, $field, $rows);
            // Only agreement freezes an item; consensus out is reported in the summary, so a
            // majority disagreeing is never mistaken for agreement (SCO-5).
            if ($result['status'] === self::CONSENSUS_IN) {
                $frozen[] = (int)$field->id;
            }
        }
        return $frozen;
    }

    public const CONSENSUS_IN = 'in';
    public const CONSENSUS_OUT = 'out';
    public const CONSENSUS_NONE = 'none';

    /**
     * One item's consensus (SCO-5). The question's own bands and excluded codes, or the
     * form's. With an agree band: consensus in when the agree share reaches the threshold
     * (and the disagree share does not), consensus out when the disagree share does. An
     * optional IQR limit must also hold for consensus in. With no bands, the most common
     * answer reaching the threshold counts as in. "Unable to score" codes are left out of
     * the denominator. Median and IQR are given for numeric scales.
     *
     * @param array<int,array{value:mixed,weight:mixed}> $rows
     * @return array{status:string,agree:?float,disagree:?float,top:?float,median:?float,iqr:?float,n:float}
     */
    public function itemConsensus(CustomForm $form, FormField $field, array $rows): array
    {
        $threshold = $form->getConsensusThreshold();
        $bands = $field->getConsensusOverride() ?? array_merge($form->getConsensusBands(), ['iqr_max' => null]);
        if ($bands['agree_from'] === null && $bands['disagree_from'] === null) {
            // A question that only sets excluded codes or an IQR limit keeps the form's bands.
            $formBands = $form->getConsensusBands();
            foreach (['agree_from', 'agree_to', 'disagree_from', 'disagree_to'] as $key) {
                $bands[$key] = $formBands[$key];
            }
            if (!$bands['exclude']) {
                $bands['exclude'] = $formBands['exclude'];
            }
        }
        $excluded = array_fill_keys(array_map('strval', $bands['exclude']), true);
        $counts = [];
        $numeric = [];
        $total = 0.0;
        foreach ($rows as $row) {
            $val = (string)($row['value'] ?? '');
            if ($val === '' || isset($excluded[$val])) {
                continue;
            }
            $w = (($row['weight'] ?? null) === null || $row['weight'] === '') ? 1.0 : (float)$row['weight'];
            $counts[$val] = ($counts[$val] ?? 0) + $w;
            $total += $w;
            if (is_numeric($val)) {
                $numeric[] = [(float)$val, $w];
            }
        }
        $out = ['status' => self::CONSENSUS_NONE, 'agree' => null, 'disagree' => null, 'top' => null, 'median' => null, 'iqr' => null, 'n' => $total];
        if ($total <= 0) {
            return $out;
        }
        if ($numeric && count($numeric) === count(array_filter($rows, static fn ($r) => (string)($r['value'] ?? '') !== '' && !isset($excluded[(string)$r['value']])))) {
            $out['median'] = self::weightedQuantile($numeric, 0.5);
            $out['iqr'] = round(self::weightedQuantile($numeric, 0.75) - self::weightedQuantile($numeric, 0.25), 4);
        }
        $out['top'] = round(max($counts) / $total * 100, 2);
        if ($bands['agree_from'] !== null && $bands['agree_to'] !== null) {
            $agree = 0.0;
            $disagree = 0.0;
            foreach ($counts as $val => $weight) {
                if ($this->inConsensusBand((string)$val, $bands['agree_from'], $bands['agree_to'])) {
                    $agree += $weight;
                }
                if ($bands['disagree_from'] !== null && $bands['disagree_to'] !== null
                    && $this->inConsensusBand((string)$val, $bands['disagree_from'], $bands['disagree_to'])) {
                    $disagree += $weight;
                }
            }
            $out['agree'] = round($agree / $total * 100, 2);
            $out['disagree'] = round($disagree / $total * 100, 2);
            $iqrOk = $bands['iqr_max'] === null || ($out['iqr'] !== null && $out['iqr'] <= $bands['iqr_max']);
            if ($out['agree'] >= $threshold && $out['disagree'] < $threshold && $iqrOk) {
                $out['status'] = self::CONSENSUS_IN;
            } elseif ($out['disagree'] >= $threshold) {
                $out['status'] = self::CONSENSUS_OUT;
            }
            return $out;
        }
        $iqrOk = $bands['iqr_max'] === null || ($out['iqr'] !== null && $out['iqr'] <= $bands['iqr_max']);
        if ($out['top'] >= $threshold && $iqrOk) {
            $out['status'] = self::CONSENSUS_IN;
        }
        return $out;
    }

    /**
     * Weighted quantile: the smallest value whose cumulative weight reaches q of the total.
     *
     * @param array<int,array{0:float,1:float}> $pairs value, weight
     */
    public static function weightedQuantile(array $pairs, float $q): float
    {
        usort($pairs, static fn ($a, $b) => $a[0] <=> $b[0]);
        $total = array_sum(array_column($pairs, 1));
        if ($total <= 0) {
            return 0.0;
        }
        $target = $q * $total;
        $cum = 0.0;
        foreach ($pairs as $i => [$value, $weight]) {
            $next = $cum + $weight;
            if ($target <= $next) {
                return (float)$value;
            }
            $cum = $next;
        }
        return (float)end($pairs)[0];
    }

    private function inConsensusBand(string $value, string $from, string $to): bool
    {
        if (is_numeric($value) && is_numeric($from) && is_numeric($to)) {
            $n = (float)$value;
            $a = (float)$from;
            $b = (float)$to;
            if ($a > $b) {
                [$a, $b] = [$b, $a];
            }
            return $n >= $a && $n <= $b;
        }
        return $value === $from || $value === $to;
    }
}
