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
        if (!empty($params['codebook'])) {
            return $this->codebookCsv($form);
        }
        if (!empty($params['allocation'])) {
            return $this->allocationCsv($form);
        }
        $params['forExport'] = 1;
        [$query] = AnswerListService::query($form, $params);
        $query->with(['answerFields', 'user', 'wave', 'round', 'panelMember', 'integrityMeta']);

        $fields = $this->exportFields($form);
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
        fputcsv($fh, \humhub\modules\thiscoveryForms\helpers\CsvCell::row($header));

        /** @var FormAnswer $answer */
        foreach ($query->each(100) as $answer) {
            $cells = $this->answerCells($form, $answer, $fields, !empty($params['include_hidden_instances']));
            $row = [];
            foreach ($columns as $col) {
                $value = $cells[$col['key']] ?? '';
                if ($redactor) {
                    $value = $redactor->redact($value);
                }
                $row[] = $value;
            }
            fputcsv($fh, \humhub\modules\thiscoveryForms\helpers\CsvCell::row($row));
        }

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return $csv === false ? '' : $csv;
    }

    /**
     * Columns follow the editions responses were filled against, plus the live
     * definition for any response that has no edition.
     *
     * @return FormField[]
     */
    private function exportFields(CustomForm $form): array
    {
        $byId = [];
        foreach ($form->getAllFields()->all() as $field) {
            if ($field->collectsAnswer()) {
                $byId[(int)$field->id] = $field;
            }
        }
        $editionIds = (new \yii\db\Query())
            ->select('edition_id')
            ->distinct()
            ->from(FormAnswer::tableName())
            ->where(['form_id' => (int)$form->id])
            ->andWhere(['not', ['edition_id' => null]])
            ->column();
        $versions = new FormVersionService();
        foreach ($editionIds as $editionId) {
            $editionFields = $versions->editionFields($form, (int)$editionId);
            if ($editionFields === null) {
                continue;
            }
            foreach ($editionFields as $field) {
                $id = (int)$field->id;
                if ($id > 0 && !isset($byId[$id]) && $field->collectsAnswer()) {
                    $byId[$id] = $field;
                }
            }
        }
        return array_values($byId);
    }

    /**
     * @param FormField[] $fields
     * @return array<string, string>
     */
    private function answerCells(CustomForm $form, FormAnswer $answer, array $fields, bool $includeHidden = false): array
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
            ExportSettings::KEY_EDITION_ID => $answer->edition_id ? (string)$answer->edition_id : '',
            ExportSettings::KEY_STATUS => $status,
            ExportSettings::KEY_OUTCOME => (string)$answer->outcome,
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
        $rand = new RandomisationService();
        $assigned = $rand->assignment($answer);
        $cells[ExportSettings::KEY_ARM_CODE] = (string)($assigned['arm_code'] ?? '');
        $cells[ExportSettings::KEY_ARM_NAME] = (string)($assigned['arm_name'] ?? '');
        $cells[ExportSettings::KEY_ARM_METHOD] = (string)($assigned['method'] ?? '');
        $cells[ExportSettings::KEY_ARM_ASSIGNED_AT] = (string)($assigned['assigned_at'] ?? '');
        $cells[ExportSettings::KEY_ARM_STRATUM] = (string)($assigned['stratum_key'] ?? '');
        if (QuotaService::tablesReady()) {
            $quota = new QuotaService();
            $cells['quota_ids'] = implode('|', $quota->acceptedIds($answer));
            $cells['quota_marker'] = (string)($answer->quota_marker ?? '');
        }
        $cells['consent_version'] = '';
        $cells['consent_hash'] = '';
        $cells['consent_signed_at'] = '';
        $consent = new ConsentService();
        if (ConsentService::formEnabled($form) && $consent->tablesReady()) {
            $linked = $answer->id
                ? (new \yii\db\Query())->from('{{%custom_form_consent_record}}')->where(['answer_id' => (int)$answer->id])->orderBy(['id' => SORT_DESC])->one()
                : null;
            $anonymousConsent = $form->hidesIdentityFromManagers() && \humhub\modules\thiscoveryForms\Module::identityEnforced();
            if ($linked && !$anonymousConsent) {
                $cells['consent_version'] = (string)$answer->consent_version;
                $cells['consent_hash'] = (string)($linked['content_hash'] ?? '');
                $cells['consent_signed_at'] = (string)($linked['signed_at'] ?? '');
                $items = json_decode((string)($linked['items_json'] ?? ''), true);
                if (is_array($items)) {
                    foreach ($items as $code => $value) {
                        $cells['consent.' . $code] = (string)$value;
                    }
                }
            }
        }
        $orders = $rand->orders($answer);
        foreach (['options', 'questions', 'pages', 'shown'] as $bucket) {
            foreach ($orders[$bucket] as $key => $list) {
                $suffix = $bucket === 'shown' ? 'shown' : 'order';
                if ($bucket === 'questions' && $suffix === 'order') {
                    $cells['rand.' . $key . '.order'] = implode('|', array_map('strval', $list));
                } elseif ($bucket === 'shown') {
                    $cells['rand.' . $key . '.shown'] = implode('|', array_map('strval', $list));
                } elseif ($bucket !== 'questions') {
                    $cells['rand.' . $key . '.order'] = implode('|', array_map('strval', $list));
                }
            }
        }
        $loops = new LoopService();
        foreach ($fields as $field) {
            if ($loops->isLoopField($form, $field)) {
                $fieldList = array_values($form->fields);
                $shown = $loops->shownPaths($fieldList, $field, $map);
                $shownCodes = [];
                foreach ($shown as $instance) {
                    $shownCodes[$instance['code']] = true;
                }
                $variable = trim((string)$field->variable) ?: ('q' . (int)$field->id);
                $instanceCells = is_array($map[$field->id] ?? null) ? $map[$field->id] : [];
                foreach ($loops->columnPaths($fieldList, $field) as $column) {
                    $code = (string)$column['code'];
                    $raw = $instanceCells[$code] ?? '';
                    if (!$includeHidden && !isset($shownCodes[$code])) {
                        $raw = '';
                    }
                    $cells[$loops->exportColumn($variable, $code)] = $this->formatCell($raw);
                }
                continue;
            }
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
            $header = $variable !== '' ? $variable : $label;
        } elseif ($mode === self::HEADER_BOTH) {
            if ($variable !== '' && $label !== '' && strcasecmp($variable, $label) !== 0) {
                $header = $variable . ' — ' . $label;
            } else {
                $header = $variable !== '' ? $variable : $label;
            }
        } else {
            $header = $label !== '' ? $label : $variable;
        }
        if ($field->isRemoved()) {
            $header .= ' (' . Yii::t('ThiscoveryFormsModule.base', 'removed') . ')';
        }
        return $header;
    }

    public function codebookCsv(CustomForm $form): string
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, ['variable', 'label', 'type', 'codes', 'notes']);
        foreach ($form->getAllFields()->all() as $field) {
            if (!$field->collectsAnswer() && !in_array($field->type, [FormField::TYPE_RAND_BLOCK, FormField::TYPE_QUESTION_GROUP], true)) {
                continue;
            }
            $codes = [];
            foreach ($field->getChoicePairs() as $pair) {
                $codes[] = $pair['code'] . '=' . $pair['label'];
            }
            $loops = new LoopService();
            if ($loops->isLoopField($form, $field)) {
                $variable = trim((string)$field->variable) ?: ('q' . (int)$field->id);
                $fieldList = array_values($form->fields);
                $group = $loops->groupForField($fieldList, $field);
                $groupCfg = $group ? $loops->config($group) : null;
                if ($groupCfg && $groupCfg['source'] === 'roster') {
                    fputcsv($fh, [
                        $variable,
                        trim(strip_tags((string)$field->label)),
                        (string)$field->type,
                        '',
                        'Roster row. The long export has one row per entry that was shown.',
                    ]);
                    continue;
                }
                foreach ($loops->columnPaths($fieldList, $field) as $column) {
                    fputcsv($fh, [
                        $loops->exportColumn($variable, (string)$column['code']),
                        trim(strip_tags((string)$field->label)) . ' (' . $column['label'] . ')',
                        (string)$field->type,
                        implode('; ', $codes),
                        'Loop instance ' . $column['code'],
                    ]);
                }
                continue;
            }
            fputcsv($fh, [
                trim((string)$field->variable),
                trim(strip_tags((string)$field->label)),
                (string)$field->type,
                implode('; ', $codes),
                '',
            ]);
        }
        $rand = new RandomisationService();
        foreach ($rand->config($form)['arms'] as $arm) {
            fputcsv($fh, ['arm', (string)$arm['name'], 'arm', (string)$arm['code'] . '=' . (string)$arm['weight'], '']);
        }
        fputcsv($fh, ['outcome', 'Response outcome', 'meta', 'complete; screened_out; not_consented; over_quota', 'Blank means the 1.28 status column applies.']);
        foreach ((new QuotaService())->quotas((int)$form->id) as $quota) {
            $rules = json_decode((string)($quota['rules_json'] ?? ''), true);
            fputcsv($fh, [
                'quota_' . (int)$quota['id'],
                (string)$quota['name'],
                'quota',
                '',
                'target ' . (int)$quota['target'] . '; ' . (is_array($rules) ? json_encode($rules, JSON_UNESCAPED_UNICODE) : ''),
            ]);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        return $csv === false ? '' : $csv;
    }

    public function allocationCsv(CustomForm $form): string
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, ['arm_code', 'arm_name', 'stratum', 'assigned', 'completed']);
        foreach ((new RandomisationService())->allocationSummary($form) as $row) {
            fputcsv($fh, [
                $row['arm_code'],
                $row['arm_name'],
                $row['stratum_key'],
                $row['assigned'],
                $row['completed'],
            ]);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        return $csv === false ? '' : $csv;
    }

    /**
     * One row per shown repeat. Roster rows use this because each row has its own key.
     */
    public function longCsv(CustomForm $form, array $params = []): string
    {
        $loops = new LoopService();
        $fields = array_values($form->fields);
        $groups = [];
        foreach ($fields as $field) {
            if ($field->type === FormField::TYPE_QUESTION_GROUP && $loops->config($field)) {
                $groups[] = $field;
            }
        }
        $fh = fopen('php://temp', 'r+');
        if ($groups === [] || !LoopService::active($form)) {
            fputcsv($fh, ['group', 'answer_id', 'instance_key', 'instance_label']);
            rewind($fh);
            $csv = stream_get_contents($fh);
            fclose($fh);
            return $csv === false ? '' : $csv;
        }
        $includeHidden = !empty($params['include_hidden_instances']);
        $params['forExport'] = 1;
        $previous = RandomisationService::$current;
        foreach ($groups as $group) {
            [$query] = AnswerListService::query($form, $params);
            $query->with(['answerFields']);
            $questions = [];
            $depth = 0;
            $started = false;
            $startDepth = 0;
            foreach ($fields as $field) {
                if ($field->type === FormField::TYPE_QUESTION_GROUP) {
                    $depth++;
                    if ((int)$field->id === (int)$group->id) {
                        $started = true;
                        $startDepth = $depth;
                    }
                    continue;
                }
                if ($field->type === FormField::TYPE_GROUP_END && $depth > 0) {
                    if ($started && $depth === $startDepth) {
                        break;
                    }
                    $depth--;
                    continue;
                }
                if ($started && $field->collectsAnswer() && $loops->groupForField($fields, $field) && (int)$loops->groupForField($fields, $field)->id === (int)$group->id) {
                    $questions[] = $field;
                }
            }
            $header = ['group', 'answer_id', 'instance_key', 'instance_label'];
            foreach ($questions as $question) {
                $header[] = trim((string)$question->variable) ?: ('q' . (int)$question->id);
            }
            fputcsv($fh, $header);
            /** @var FormAnswer $answer */
            foreach ($query->each(100) as $answer) {
                RandomisationService::$current = $answer;
                $map = $answer->getValuesMap();
                $keys = $loops->rosterInstanceKeys($answer, $group, false);
                if ($keys === [] && ($loops->config($group)['source'] ?? '') !== 'roster' && $questions) {
                    foreach ($loops->shownPaths($fields, $questions[0], $map) as $path) {
                        $keys[] = (string)$path['code'];
                    }
                }
                if ($includeHidden && ($loops->config($group)['source'] ?? '') === 'roster') {
                    $keys = array_merge($keys, $loops->rosterInstanceKeys($answer, $group, true));
                }
                foreach ($keys as $full) {
                    $row = [
                        $loops->groupKey($group),
                        (string)$answer->id,
                        $full,
                        $loops->rosterName($group, $full, $map, $fields),
                    ];
                    foreach ($questions as $question) {
                        $cells = $map[(int)$question->id] ?? null;
                        $raw = is_array($cells) ? ($cells[$full] ?? '') : '';
                        $row[] = $this->formatCell($raw);
                    }
                    fputcsv($fh, $row);
                }
            }
        }
        RandomisationService::$current = $previous;
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
