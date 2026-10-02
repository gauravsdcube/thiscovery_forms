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
        [$fh] = $this->toCsvHandle($form, $params);
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        return $csv === false ? '' : $csv;
    }

    /**
     * The export as a stream (a temporary file once over 2 MB), with its data-row count, so a
     * download is streamed instead of built as one string in memory (DAT-15).
     *
     * @return array{0: resource, 1: int}
     */
    public function toCsvHandle(CustomForm $form, array $params = [], bool $bom = false): array
    {
        $fh = fopen('php://temp/maxmemory:2097152', 'r+');
        if ($bom) {
            fwrite($fh, "\xEF\xBB\xBF");
        }
        $other = null;
        if (!empty($params['codebook'])) {
            $other = $this->codebookCsv($form);
        } elseif (!empty($params['allocation'])) {
            $other = $this->allocationCsv($form);
        } elseif (!empty($params['long'])) {
            // Through the same path, so the controllers' permission check and export log apply (V3-19).
            $other = $this->longCsv($form, $params);
        }
        if ($other !== null) {
            fwrite($fh, $other);
            $lines = preg_split('/\r\n|\r|\n/', trim($other)) ?: [];
            return [$fh, max(0, count($lines) - 1)];
        }
        $rows = $this->writeWideCsv($fh, $form, $params);
        return [$fh, $rows];
    }

    /**
     * @param resource $fh
     */
    private function writeWideCsv($fh, CustomForm $form, array $params): int
    {
        $params['forExport'] = 1;
        // Complete responses only unless the form or the request asks for in-progress too, so
        // totals match the dashboard (SCO-12).
        if (($params['status'] ?? '') === '' && !ExportSettings::get($form)['include_in_progress']
            && (string)($params['include_in_progress'] ?? '') !== '1') {
            $params['status'] = 'complete';
        }
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
        $rows = 0;

        $header = [];
        foreach ($columns as $col) {
            $header[] = $col['header'];
        }
        fputcsv($fh, \humhub\modules\thiscoveryForms\helpers\CsvCell::row($header));

        /** @var FormAnswer $answer */
        foreach ($query->each(100) as $answer) {
            $answer->populateRelation('form', $form);
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
            $rows++;
        }
        return $rows;
    }

    /**
     * Columns follow the published editions responses were filled against, newest first, so
     * labels and options are the published ones, never a draft relabel. Draft-only questions
     * appear only when some response has no edition (or nothing is published) (DAT-8).
     *
     * @return FormField[]
     */
    private function exportFields(CustomForm $form): array
    {
        $live = [];
        foreach ($form->getAllFields()->all() as $field) {
            if ($field->collectsAnswer()) {
                $live[(int)$field->id] = $field;
            }
        }
        $editionIds = array_map('intval', (new \yii\db\Query())
            ->select('edition_id')
            ->distinct()
            ->from(FormAnswer::tableName())
            ->where(['form_id' => (int)$form->id])
            ->andWhere(['not', ['edition_id' => null]])
            ->orderBy(['edition_id' => SORT_DESC])
            ->column());
        $unpinned = (new \yii\db\Query())
            ->from(FormAnswer::tableName())
            ->where(['form_id' => (int)$form->id, 'edition_id' => null])
            ->exists();

        $byId = [];
        $loaded = false;
        $versions = new FormVersionService();
        foreach ($editionIds as $editionId) {
            $editionFields = $versions->editionFields($form, $editionId);
            if ($editionFields === null) {
                continue;
            }
            $loaded = true;
            foreach ($editionFields as $field) {
                $id = (int)$field->id;
                if ($id > 0 && !isset($byId[$id]) && $field->collectsAnswer()) {
                    $byId[$id] = $field;
                }
            }
        }
        if ($unpinned || !$loaded) {
            foreach ($live as $id => $field) {
                if (!isset($byId[$id])) {
                    $byId[$id] = $field;
                }
            }
        }
        // Questions keep the live order where they still exist; removed ones keep their own.
        $fields = array_values($byId);
        usort($fields, static function (FormField $a, FormField $b) use ($live): int {
            $sa = (int)($live[(int)$a->id]->sort_order ?? $a->sort_order);
            $sb = (int)($live[(int)$b->id]->sort_order ?? $b->sort_order);
            return [$sa, (int)$a->id] <=> [$sb, (int)$b->id];
        });
        return $fields;
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
            // In the form's time zone with its offset, not the server's clock (SCO-25).
            ExportSettings::KEY_SUBMITTED_AT => $this->formTime($form, (string)$answer->created_at),
            ExportSettings::KEY_UPDATED_AT => $this->formTime($form, (string)$answer->updated_at),
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
        // Why an answer is empty (SCO-12): shown but skipped, hidden by logic, or never reached.
        $missingCodes = ExportSettings::get($form)['missing_codes'];
        $shownIds = [];
        $routeIds = [];
        if ($missingCodes) {
            $integrity = new \humhub\modules\thiscoveryForms\services\integrity\IntegrityService();
            $shownIds = $integrity->shownFieldIds($form, $map);
            $routeIds = (new FormPager())->visitedFieldIds(array_values($form->fields), $map, []);
        }
        $complete = $answer->isComplete();
        $missingFor = static function (FormField $field, $val) use ($missingCodes, $shownIds, $routeIds, $complete): ?string {
            $empty = $val === null || $val === '' || $val === [];
            if (!$missingCodes || !$empty) {
                return null;
            }
            // Questions the respondent never sees (hidden values, calculations, metadata) stay blank.
            if ($field->isHiddenFromRespondent() || in_array($field->type, [FormField::TYPE_CALCULATED, FormField::TYPE_RESPONDENT_META, FormField::TYPE_PANEL_ATTR], true)) {
                return null;
            }
            if (isset($shownIds[(int)$field->id])) {
                // A draft may simply not have got there yet.
                return $complete ? ExportSettings::MISSING_SKIPPED : ExportSettings::MISSING_NOT_REACHED;
            }
            return isset($routeIds[(int)$field->id]) ? ExportSettings::MISSING_HIDDEN : ExportSettings::MISSING_NOT_REACHED;
        };
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
            $missing = $missingFor($field, $val);
            if ($missing !== null) {
                $cells[ExportSettings::fieldColumnKey($field)] = $missing;
                if ($field->type === FormField::TYPE_CHECKBOX) {
                    foreach ($field->getChoicePairs() as $pair) {
                        $cells[ExportSettings::optionColumnKey($field, (string)$pair['code'])] = $missing;
                    }
                }
                continue;
            }
            if ($field->type === FormField::TYPE_CHECKBOX) {
                $ticked = array_map('strval', is_array($val) ? $val : ($val === '' ? [] : [(string)$val]));
                foreach ($field->getChoicePairs() as $pair) {
                    $cells[ExportSettings::optionColumnKey($field, (string)$pair['code'])] = in_array((string)$pair['code'], $ticked, true) ? '1' : '0';
                }
            }
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
        fputcsv($fh, \humhub\modules\thiscoveryForms\helpers\CsvCell::row(['variable', 'label', 'type', 'codes', 'notes']));
        // The same published definitions as the data columns (DAT-8), plus the live groups and
        // randomised blocks that describe them.
        $codebookFields = $this->exportFields($form);
        foreach ($form->getAllFields()->all() as $structural) {
            if (in_array($structural->type, [FormField::TYPE_RAND_BLOCK, FormField::TYPE_QUESTION_GROUP], true)) {
                $codebookFields[] = $structural;
            }
        }
        usort($codebookFields, static fn (FormField $a, FormField $b) => [(int)$a->sort_order, (int)$a->id] <=> [(int)$b->sort_order, (int)$b->id]);
        foreach ($codebookFields as $field) {
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
                    fputcsv($fh, \humhub\modules\thiscoveryForms\helpers\CsvCell::row([
                        $variable,
                        trim(strip_tags((string)$field->label)),
                        (string)$field->type,
                        '',
                        'Roster row. The long export has one row per entry that was shown.',
                    ]));
                    continue;
                }
                foreach ($loops->columnPaths($fieldList, $field) as $column) {
                    fputcsv($fh, \humhub\modules\thiscoveryForms\helpers\CsvCell::row([
                        $loops->exportColumn($variable, (string)$column['code']),
                        trim(strip_tags((string)$field->label)) . ' (' . $column['label'] . ')',
                        (string)$field->type,
                        implode('; ', $codes),
                        'Loop instance ' . $column['code'],
                    ]));
                }
                continue;
            }
            fputcsv($fh, \humhub\modules\thiscoveryForms\helpers\CsvCell::row([
                trim((string)$field->variable),
                trim(strip_tags((string)$field->label)),
                (string)$field->type,
                implode('; ', $codes),
                '',
            ]));
        }
        $rand = new RandomisationService();
        foreach ($rand->config($form)['arms'] as $arm) {
            fputcsv($fh, \humhub\modules\thiscoveryForms\helpers\CsvCell::row(['arm', (string)$arm['name'], 'arm', (string)$arm['code'] . '=' . (string)$arm['weight'], '']));
        }
        fputcsv($fh, \humhub\modules\thiscoveryForms\helpers\CsvCell::row(['outcome', 'Response outcome', 'meta', 'complete; screened_out; not_consented; over_quota', 'Blank means the 1.28 status column applies.']));
        foreach ((new QuotaService())->quotas((int)$form->id) as $quota) {
            $rules = json_decode((string)($quota['rules_json'] ?? ''), true);
            fputcsv($fh, \humhub\modules\thiscoveryForms\helpers\CsvCell::row([
                'quota_' . (int)$quota['id'],
                (string)$quota['name'],
                'quota',
                '',
                'target ' . (int)$quota['target'] . '; ' . (is_array($rules) ? json_encode($rules, JSON_UNESCAPED_UNICODE) : ''),
            ]));
        }
        $exportCfg = ExportSettings::get($form);
        if ($exportCfg['missing_codes']) {
            $codes = [];
            foreach (ExportSettings::missingCodeLabels() as $code => $label) {
                $codes[] = $code . '=' . $label;
            }
            fputcsv($fh, \humhub\modules\thiscoveryForms\helpers\CsvCell::row([
                '(missing values)',
                Yii::t('ThiscoveryFormsModule.base', 'Codes for an empty answer, in every question column'),
                'note',
                implode('; ', $codes),
                '',
            ]));
        }
        if ($exportCfg['multi_columns']) {
            fputcsv($fh, \humhub\modules\thiscoveryForms\helpers\CsvCell::row([
                '(multiple choice)',
                Yii::t('ThiscoveryFormsModule.base', 'Each option also has its own column: 1 ticked, 0 not ticked'),
                'note',
                '1=ticked; 0=not ticked',
                '',
            ]));
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        return $csv === false ? '' : $csv;
    }

    /**
     * The allocation log: one row per randomised response, with arm, method, stratum and
     * time (V3-47, V3-48). The summary keys it read before never existed, so it always failed.
     */
    public function allocationCsv(CustomForm $form): string
    {
        $fh = fopen('php://temp', 'r+');
        $write = static function (array $row) use ($fh): void {
            fputcsv($fh, \humhub\modules\thiscoveryForms\helpers\CsvCell::row($row));
        };
        $write(['answer_id', 'arm_code', 'arm_name', 'method', 'stratum', 'assigned_at', 'manual_override', 'status', 'outcome']);
        if (Yii::$app->db->schema->getTableSchema('{{%custom_form_arm_assignment}}') !== null) {
            $rows = (new \yii\db\Query())
                ->select(['g.answer_id', 'g.arm_code', 'g.arm_name', 'g.method', 'g.stratum_key', 'g.assigned_at', 'g.assigned_by', 'a.status', 'a.outcome'])
                ->from(['g' => '{{%custom_form_arm_assignment}}'])
                ->innerJoin(['a' => FormAnswer::tableName()], 'a.id = g.answer_id')
                ->where(['a.form_id' => (int)$form->id, 'a.is_test' => 0])
                ->orderBy(['g.assigned_at' => SORT_ASC, 'g.answer_id' => SORT_ASC]);
            foreach ($rows->each(500) as $row) {
                $write([
                    (string)$row['answer_id'],
                    (string)$row['arm_code'],
                    (string)$row['arm_name'],
                    (string)$row['method'],
                    (string)$row['stratum_key'],
                    (string)$row['assigned_at'],
                    $row['assigned_by'] ? '1' : '0',
                    (int)$row['status'] === FormAnswer::STATUS_COMPLETE ? 'complete' : 'in_progress',
                    (string)$row['outcome'],
                ]);
            }
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        return $csv === false ? '' : $csv;
    }

    /**
     * One row per shown repeat. Roster rows use this because each row has its own key.
     * The same column rules, PII scrubbing and CSV neutralisation as the wide export apply.
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
        $write = static function (array $row) use ($fh): void {
            fputcsv($fh, \humhub\modules\thiscoveryForms\helpers\CsvCell::row($row));
        };
        if ($groups === [] || !LoopService::active($form)) {
            $write(['group', 'answer_id', 'instance_key', 'instance_label']);
            rewind($fh);
            $csv = stream_get_contents($fh);
            fclose($fh);
            return $csv === false ? '' : $csv;
        }
        // A question is exported only if the wide export would include one of its columns.
        $allowed = [];
        foreach (ExportSettings::resolvedColumns($form, $this->exportFields($form), self::HEADER_VARIABLE) as $col) {
            if (!empty($col['field']) && empty($col['comment'])) {
                $allowed[(int)$col['field']->id] = true;
            }
        }
        // A roster question has no fixed wide-export column, so it would be dropped here and
        // the long export would list the row with an empty name. Include it unless it was
        // excluded or scrubbed.
        $excluded = array_fill_keys(ExportSettings::excludeColumns($form), true);
        $scrub = ExportSettings::isPiiScrub($form);
        foreach ($fields as $field) {
            if (!$field->collectsAnswer() || isset($allowed[(int)$field->id]) || !$loops->isLoopField($form, $field)) {
                continue;
            }
            if (isset($excluded['field.' . (int)$field->id]) || ($scrub && ExportSettings::fieldDropsWhenScrub($field))) {
                continue;
            }
            $allowed[(int)$field->id] = true;
        }
        $redactor = ExportSettings::isPiiScrub($form) ? new PiiRedactor() : null;
        $clean = static function (string $value) use ($redactor): string {
            return $redactor ? (string)$redactor->redact($value) : $value;
        };
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
                if ($started && $field->collectsAnswer() && isset($allowed[(int)$field->id])) {
                    $owner = $loops->groupForField($fields, $field);
                    if ($owner && (int)$owner->id === (int)$group->id) {
                        $questions[] = $field;
                    }
                }
            }
            $nameField = $loops->rosterNameField($group, $fields);
            $showNames = !$nameField || isset($allowed[(int)$nameField->id]);
            $header = ['group', 'answer_id', 'instance_key', 'instance_label'];
            foreach ($questions as $question) {
                $header[] = trim((string)$question->variable) ?: ('q' . (int)$question->id);
            }
            $write($header);
            /** @var FormAnswer $answer */
            foreach ($query->each(100) as $answer) {
                RandomisationService::$current = $answer;
                $answer->populateRelation('form', $form);
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
                        $showNames ? $clean($loops->rosterName($group, $full, $map, $fields)) : '',
                    ];
                    foreach ($questions as $question) {
                        $cells = $map[(int)$question->id] ?? null;
                        $raw = is_array($cells) ? ($cells[$full] ?? '') : '';
                        if ($question->type === FormField::TYPE_FILE && is_string($raw) && $raw !== '') {
                            $file = File::findOne(['guid' => $raw]);
                            $row[] = $clean($file ? (string)$file->file_name : $raw);
                            continue;
                        }
                        $row[] = $clean($this->formatCell($raw));
                    }
                    $write($row);
                }
            }
        }
        RandomisationService::$current = $previous;
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        return $csv === false ? '' : $csv;
    }

    /** A stored timestamp (application time zone) as ISO 8601 in the form's zone. */
    private function formTime(CustomForm $form, string $stored): string
    {
        if (trim($stored) === '') {
            return '';
        }
        try {
            $at = new \DateTimeImmutable($stored, new \DateTimeZone((string)(Yii::$app->timeZone ?: 'UTC')));
            return $at->setTimezone(new \DateTimeZone(\humhub\modules\thiscoveryForms\services\formula\FormulaRuntime::timeZone($form)))->format('c');
        } catch (\Throwable $e) {
            return $stored;
        }
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
