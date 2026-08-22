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
        return $round->save(false, ['status', 'opens_at', 'closes_at', 'updated_at']);
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

        foreach ($form->fields as $field) {
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
                ])
                ->all();
            if (!$rows) {
                continue;
            }

            $counts = [];
            $weightTotal = 0.0;
            $justifications = [];
            foreach ($rows as $row) {
                $val = (string)$row['value'];
                $w = (float)($row['weight'] ?: 1);
                if ($val === '') {
                    continue;
                }
                $counts[$val] = ($counts[$val] ?? 0) + $w;
                $weightTotal += $w;
                $just = trim((string)($row['justification'] ?? ''));
                if ($just !== '' && count($justifications) < 40) {
                    $justifications[] = $just;
                }
            }
            if ($weightTotal <= 0) {
                continue;
            }

            $parts[] = '<h4>' . htmlspecialchars($field->label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h4><ul>';
            arsort($counts);
            foreach ($counts as $label => $w) {
                $pct = round(($w / $weightTotal) * 100);
                $parts[] = '<li>' . htmlspecialchars((string)$label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . ' — ' . $pct . '%</li>';
            }
            $parts[] = '</ul>';

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
     * @return int[]
     */
    public function computeFrozenFieldIds(CustomForm $form, FormRound $round): array
    {
        $threshold = $form->getConsensusThreshold();
        $frozen = [];
        foreach ($form->fields as $field) {
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
                ])
                ->all();
            $counts = [];
            $total = 0.0;
            foreach ($rows as $row) {
                $val = (string)$row['value'];
                if ($val === '') {
                    continue;
                }
                $w = (float)($row['weight'] ?: 1);
                $counts[$val] = ($counts[$val] ?? 0) + $w;
                $total += $w;
            }
            if ($total <= 0) {
                continue;
            }
            $max = max($counts);
            if (($max / $total) * 100 >= $threshold) {
                $frozen[] = (int)$field->id;
            }
        }
        return $frozen;
    }
}
