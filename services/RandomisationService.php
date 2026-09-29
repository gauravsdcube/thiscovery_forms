<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\Module;
use Yii;
use yii\db\Query;

/**
 * Server-side presentation order and arm assignment.
 * With the module flag or the form flag off, callers should not reach the draw.
 */
class RandomisationService
{
    public static ?FormAnswer $current = null;

    /** @var array<int, array<int, true>> */
    private static array $hiddenCache = [];

    public function __construct(private ?RandomisationEngine $engine = null)
    {
        $this->engine = $engine ?: new RandomisationEngine();
    }

    public static function hides(FormField $field): bool
    {
        $answer = self::$current;
        if (!$answer || !(int)$answer->id) {
            return false;
        }
        $id = (int)$answer->id;
        if (!array_key_exists($id, self::$hiddenCache)) {
            self::$hiddenCache[$id] = (new self())->hiddenFieldIds($answer);
        }
        return isset(self::$hiddenCache[$id][(int)$field->id]);
    }

    /**
     * Draw any order that has not been stored yet, so resume and submit see the same route.
     */
    public function materialise(FormAnswer $answer): void
    {
        if (!$answer->form || !self::active($answer->form)) {
            return;
        }
        unset(self::$hiddenCache[(int)$answer->id]);
        $this->ensure($answer);
        $built = (new FormPager())->buildPages($answer->form->fields);
        $built = $this->applyPageOrder($built, $answer);
        foreach ($built['pages'] as $page) {
            $this->orderPageItems($page['items'] ?? [], $answer);
        }
        foreach ($answer->form->fields as $field) {
            if ($field instanceof FormField && $field->isRandomizeOptions()) {
                $this->optionOrder($field, $answer);
            }
        }
        unset(self::$hiddenCache[(int)$answer->id]);
    }

    public static function active(CustomForm $form): bool
    {
        return Module::randomisationEnabled() && self::formEnabled($form);
    }

    public static function formEnabled(CustomForm $form): bool
    {
        $value = (string)$form->getSetting('randomisation_enabled', '0');
        return in_array($value, ['1', 'true', 'on'], true);
    }

    /**
     * @return array{assign:string,assign_page:string,method:string,block_size:int,strata:array,arms:array,screen_out_message:string}
     */
    public function config(CustomForm $form): array
    {
        $raw = $form->getSetting('randomisation', []);
        if (!is_array($raw)) {
            $raw = [];
        }
        $arms = [];
        foreach ($raw['arms'] ?? [] as $arm) {
            if (!is_array($arm)) {
                continue;
            }
            $code = trim((string)($arm['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $arms[] = [
                'code' => $code,
                'name' => trim((string)($arm['name'] ?? $code)) ?: $code,
                'weight' => max(1, (int)($arm['weight'] ?? 1)),
            ];
        }
        $strata = [];
        foreach ($raw['strata'] ?? [] as $factor) {
            if (!is_array($factor)) {
                continue;
            }
            $source = (string)($factor['source'] ?? '');
            $key = trim((string)($factor['key'] ?? ''));
            if (!in_array($source, ['field', 'panel'], true) || $key === '') {
                continue;
            }
            $strata[] = ['source' => $source, 'key' => $key];
        }
        $method = (string)($raw['method'] ?? 'simple');
        if (!in_array($method, ['simple', 'block', 'least_filled', 'stratified'], true)) {
            $method = 'simple';
        }
        $assign = (string)($raw['assign'] ?? 'start');
        return [
            'assign' => $assign === 'after_page' ? 'after_page' : 'start',
            'assign_page' => trim((string)($raw['assign_page'] ?? '')),
            'method' => $method,
            'block_size' => max(2, (int)($raw['block_size'] ?? 4)),
            'strata' => $strata,
            'arms' => $arms,
            'screen_out_message' => trim((string)$form->getSetting('screen_out_message', '')),
        ];
    }

    /**
     * @param array<string,mixed> $posted
     */
    public function saveConfig(CustomForm $form, array $posted): void
    {
        $enabled = in_array((string)($posted['enabled'] ?? '0'), ['1', 'true', 'on'], true) ? '1' : '0';
        $form->setSetting('randomisation_enabled', $enabled);
        $arms = [];
        foreach (preg_split('/\r\n|\r|\n/', (string)($posted['arms'] ?? '')) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $code = $parts[0] ?? '';
            if ($code === '') {
                continue;
            }
            $arms[] = [
                'code' => $code,
                'name' => ($parts[1] ?? '') !== '' ? $parts[1] : $code,
                'weight' => max(1, (int)($parts[2] ?? 1)),
            ];
        }
        $strata = [];
        foreach (preg_split('/\r\n|\r|\n/', (string)($posted['strata'] ?? '')) as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$source, $key] = array_map('trim', explode(':', $line, 2));
            if (in_array($source, ['field', 'panel'], true) && $key !== '') {
                $strata[] = ['source' => $source, 'key' => $key];
            }
        }
        $method = (string)($posted['method'] ?? 'simple');
        $assign = (string)($posted['assign'] ?? 'start');
        $form->setSetting('randomisation', [
            'assign' => $assign === 'after_page' ? 'after_page' : 'start',
            'assign_page' => trim((string)($posted['assign_page'] ?? '')),
            'method' => in_array($method, ['simple', 'block', 'least_filled', 'stratified'], true) ? $method : 'simple',
            'block_size' => max(2, (int)($posted['block_size'] ?? 4)),
            'strata' => $strata,
            'arms' => $arms,
        ]);
        $form->setSetting('screen_out_message', trim((string)($posted['screen_out_message'] ?? '')));
    }

    public function armsText(CustomForm $form): string
    {
        $lines = [];
        foreach ($this->config($form)['arms'] as $arm) {
            $lines[] = $arm['code'] . '|' . $arm['name'] . '|' . $arm['weight'];
        }
        return implode("\n", $lines);
    }

    public function strataText(CustomForm $form): string
    {
        $lines = [];
        foreach ($this->config($form)['strata'] as $factor) {
            $lines[] = $factor['source'] . ':' . $factor['key'];
        }
        return implode("\n", $lines);
    }

    /**
     * @return string[] human-readable save errors
     */
    public function authoringErrors(CustomForm $form): array
    {
        if (!self::formEnabled($form)) {
            return [];
        }
        $errors = [];
        $cfg = $this->config($form);
        $codes = [];
        foreach ($cfg['arms'] as $arm) {
            if (isset($codes[$arm['code']])) {
                $errors[] = Yii::t('ThiscoveryFormsModule.base', 'Arm code “{code}” is used twice.', ['code' => $arm['code']]);
            }
            $codes[$arm['code']] = true;
        }
        $variables = [];
        foreach ($form->getFields()->all() as $field) {
            $variable = trim((string)$field->variable);
            if ($variable !== '') {
                $variables[$variable] = true;
            }
        }
        foreach ($cfg['strata'] as $factor) {
            if ($factor['source'] === 'panel' && $form->hidesIdentityFromManagers()) {
                $errors[] = Yii::t('ThiscoveryFormsModule.base', 'A fully anonymous form cannot stratify on a panel attribute.');
            }
            if ($factor['source'] === 'field' && !isset($variables[$factor['key']])) {
                $errors[] = Yii::t('ThiscoveryFormsModule.base', 'Stratification question “{key}” is not on this form.', ['key' => $factor['key']]);
            }
        }
        if ($cfg['assign'] === 'after_page' && $cfg['assign_page'] === '') {
            $errors[] = Yii::t('ThiscoveryFormsModule.base', 'Choose the page that assigns the arm.');
        }
        foreach ($this->blocks($form->getFields()->all()) as $block) {
            if (!empty($block['open'])) {
                $errors[] = Yii::t('ThiscoveryFormsModule.base', 'Randomisation block “{key}” has no end.', ['key' => $block['key']]);
                continue;
            }
            $allowed = array_fill_keys($block['pages'], true);
            foreach ($block['fields'] as $field) {
                $logic = $field->getLogic();
                $action = (string)($logic['action'] ?? '');
                $target = trim((string)($logic['gotoPageKey'] ?? ''));
                if ($action === LogicEngine::ACTION_GOTO_PAGE && $target !== '' && !isset($allowed[$target])) {
                    $label = trim((string)$field->label) ?: $block['key'];
                    $errors[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” goes to “{target}”, which is outside block “{key}”.', [
                        'label' => $label,
                        'target' => $target,
                        'key' => $block['key'],
                    ]);
                }
            }
        }
        return $errors;
    }

    /**
     * @param FormField[] $fields
     * @return array<int, array{key:string,method:string,pages:string[],fields:FormField[],open:bool}>
     */
    public function blocks(array $fields): array
    {
        $blocks = [];
        $open = null;
        $pageKey = 'start';
        foreach ($fields as $field) {
            if (!$field instanceof FormField) {
                continue;
            }
            if ($field->type === FormField::TYPE_RAND_BLOCK) {
                $cfg = $field->getRandomiseConfig();
                $open = [
                    'key' => $cfg['blockKey'] !== '' ? $cfg['blockKey'] : ('block' . (count($blocks) + 1)),
                    'method' => $cfg['method'],
                    'pages' => [$pageKey],
                    'fields' => [$field],
                    'open' => true,
                ];
                continue;
            }
            if ($field->type === FormField::TYPE_RAND_BLOCK_END) {
                if ($open) {
                    $open['fields'][] = $field;
                    $open['open'] = false;
                    $blocks[] = $open;
                    $open = null;
                }
                continue;
            }
            if ($field->type === FormField::TYPE_PAGE_BREAK) {
                $pageKey = $field->getPageBreakConfig()['pageKey'] ?: $pageKey;
                if ($open) {
                    $open['pages'][] = $pageKey;
                    $open['fields'][] = $field;
                }
                continue;
            }
            if ($open) {
                $open['fields'][] = $field;
            }
        }
        if ($open) {
            $blocks[] = $open;
        }
        return $blocks;
    }

    public function ensure(FormAnswer $answer, bool $previewReroll = false): void
    {
        $form = $answer->form;
        if (!$form || !self::active($form)) {
            return;
        }
        $row = (new Query())->from('{{%custom_form_presentation}}')->where(['answer_id' => (int)$answer->id])->one();
        if ($previewReroll && $answer->isTest() && $row) {
            Yii::$app->db->createCommand()->update('{{%custom_form_presentation}}', [
                'seed' => bin2hex(random_bytes(16)),
                'orders_json' => null,
            ], ['answer_id' => (int)$answer->id])->execute();
            return;
        }
        if ($row) {
            return;
        }
        Yii::$app->db->createCommand()->insert('{{%custom_form_presentation}}', [
            'answer_id' => (int)$answer->id,
            'seed' => bin2hex(random_bytes(16)),
            'orders_json' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ])->execute();
    }

    /**
     * @return array{options:array,questions:array,pages:array,shown:array}
     */
    public function orders(FormAnswer $answer): array
    {
        if (Yii::$app->db->schema->getTableSchema('{{%custom_form_presentation}}', true) === null) {
            return ['options' => [], 'questions' => [], 'pages' => [], 'shown' => []];
        }
        $row = (new Query())->from('{{%custom_form_presentation}}')->where(['answer_id' => (int)$answer->id])->one();
        $decoded = $row ? json_decode((string)($row['orders_json'] ?? ''), true) : null;
        $decoded = is_array($decoded) ? $decoded : [];
        return [
            'options' => is_array($decoded['options'] ?? null) ? $decoded['options'] : [],
            'questions' => is_array($decoded['questions'] ?? null) ? $decoded['questions'] : [],
            'pages' => is_array($decoded['pages'] ?? null) ? $decoded['pages'] : [],
            'shown' => is_array($decoded['shown'] ?? null) ? $decoded['shown'] : [],
        ];
    }

    /**
     * @return string[] option codes in the order to render
     */
    public function optionOrder(FormField $field, FormAnswer $answer): array
    {
        $form = $answer->form ?: $field->form;
        if (!$form || !$field->isRandomizeOptions()) {
            return [];
        }
        $key = trim((string)$field->variable) ?: ('q' . (int)$field->id);
        if (self::active($form)) {
            $this->ensure($answer);
        }
        $orders = $this->orders($answer);
        if (isset($orders['options'][$key]) && is_array($orders['options'][$key])) {
            return array_map('strval', $orders['options'][$key]);
        }
        if (!self::active($form)) {
            return [];
        }
        $codes = [];
        foreach ($field->getChoicePairs() as $pair) {
            $codes[] = (string)$pair['code'];
        }
        $cfg = $field->getRandomiseConfig();
        $exclusive = $field->shufflePinnedLabels();
        foreach ($field->getChoicePairs() as $pair) {
            if (FormField::isOtherOption($pair['code']) || FormField::isOtherOption($pair['label']) || in_array((string)$pair['code'], $exclusive, true)) {
                $cfg['pinLast'][] = (string)$pair['code'];
            }
        }
        $seed = $this->seedFor($answer, 'options:' . $key);
        $offset = $cfg['method'] === 'rotate' ? $this->nextRotateOffset((int)$form->id, 'options:' . $key, count($codes)) : 0;
        $presented = $this->engine->present($codes, $cfg, $seed, $offset);
        $this->storeScope($answer, 'options', $key, $presented['order']);
        return $presented['order'];
    }

    /**
     * @param array{pages:array,pageKeyIndex:array} $built
     * @return array{pages:array,pageKeyIndex:array}
     */
    public function applyPageOrder(array $built, FormAnswer $answer): array
    {
        $form = $answer->form;
        if (!$form || !self::active($form)) {
            return $built;
        }
        $this->ensure($answer);
        $orders = $this->orders($answer);
        $changed = false;
        foreach ($this->blocks($form->fields) as $block) {
            if (!empty($block['open']) || count($block['pages']) < 2) {
                continue;
            }
            $key = $block['key'];
            if (!isset($orders['pages'][$key])) {
                $seed = $this->seedFor($answer, 'pages:' . $key);
                $offset = $block['method'] === 'rotate' ? $this->nextRotateOffset((int)$form->id, 'pages:' . $key, count($block['pages'])) : 0;
                $presented = $this->engine->present($block['pages'], ['method' => $block['method']], $seed, $offset);
                $this->storeScope($answer, 'pages', $key, $presented['order']);
                $orders['pages'][$key] = $presented['order'];
                $changed = true;
            }
        }
        if ($changed) {
            $orders = $this->orders($answer);
        }
        return (new FormPager())->applyBlockOrder($built, $orders['pages']);
    }

    /**
     * @param FormField[] $items
     * @return FormField[]
     */
    public function orderPageItems(array $items, FormAnswer $answer): array
    {
        $form = $answer->form;
        if (!$form || !self::active($form)) {
            return $items;
        }
        $items = array_values($items);
        $count = count($items);
        for ($i = 0; $i < $count; $i++) {
            $field = $items[$i];
            if (!$field instanceof FormField || $field->type !== FormField::TYPE_QUESTION_GROUP) {
                continue;
            }
            $cfg = $field->getRandomiseConfig();
            if (empty($cfg['enabled'])) {
                continue;
            }
            $end = null;
            $depth = 0;
            for ($j = $i; $j < $count; $j++) {
                $type = $items[$j]->type ?? '';
                if ($type === FormField::TYPE_QUESTION_GROUP) {
                    $depth++;
                } elseif ($type === FormField::TYPE_GROUP_END && $depth > 0) {
                    $depth--;
                    if ($depth === 0) {
                        $end = $j;
                        break;
                    }
                }
            }
            if ($end === null || $end <= $i + 1) {
                continue;
            }
            $children = array_slice($items, $i + 1, $end - $i - 1);
            $ids = [];
            foreach ($children as $child) {
                if ($child->collectsAnswer()) {
                    $ids[] = (string)(int)$child->id;
                }
            }
            if (count($ids) < 2) {
                continue;
            }
            $scope = trim((string)$field->variable) ?: ('group' . (int)$field->id);
            $orders = $this->orders($answer);
            if (!isset($orders['questions'][$scope])) {
                $seed = $this->seedFor($answer, 'questions:' . $scope);
                $offset = $cfg['method'] === 'rotate' ? $this->nextRotateOffset((int)$form->id, 'questions:' . $scope, count($ids)) : 0;
                $presented = $this->engine->present($ids, $cfg, $seed, $offset);
                $this->storeScope($answer, 'questions', $scope, $presented['order']);
                $this->storeScope($answer, 'shown', $scope, $presented['shown']);
                $orders['questions'][$scope] = $presented['order'];
            }
            $rank = array_flip(array_map('strval', $orders['questions'][$scope]));
            $head = [];
            $body = [];
            foreach ($children as $child) {
                $id = (string)(int)$child->id;
                if (isset($rank[$id])) {
                    $body[] = $child;
                } else {
                    $head[] = $child;
                }
            }
            usort($body, static function (FormField $a, FormField $b) use ($rank) {
                return ($rank[(string)(int)$a->id] ?? 0) <=> ($rank[(string)(int)$b->id] ?? 0);
            });
            array_splice($items, $i + 1, $end - $i - 1, array_merge($head, $body));
        }
        return $items;
    }

    /**
     * Questions in a "show N" group that this response was not shown.
     *
     * @return array<int, true>
     */
    public function hiddenFieldIds(FormAnswer $answer): array
    {
        $form = $answer->form;
        if (!$form || !self::active($form)) {
            return [];
        }
        $orders = $this->orders($answer);
        $hidden = [];
        $fields = $form->fields;
        $count = count($fields);
        for ($i = 0; $i < $count; $i++) {
            $field = $fields[$i];
            if ($field->type !== FormField::TYPE_QUESTION_GROUP) {
                continue;
            }
            $scope = trim((string)$field->variable) ?: ('group' . (int)$field->id);
            if (!isset($orders['shown'][$scope]) || !is_array($orders['shown'][$scope])) {
                continue;
            }
            $shown = array_fill_keys(array_map('strval', $orders['shown'][$scope]), true);
            $depth = 0;
            for ($j = $i; $j < $count; $j++) {
                $type = $fields[$j]->type;
                if ($type === FormField::TYPE_QUESTION_GROUP) {
                    $depth++;
                } elseif ($type === FormField::TYPE_GROUP_END && $depth > 0) {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                } elseif ($depth === 1 && $fields[$j]->collectsAnswer()) {
                    $id = (string)(int)$fields[$j]->id;
                    if (!isset($shown[$id])) {
                        $hidden[(int)$fields[$j]->id] = true;
                    }
                }
            }
        }
        return $hidden;
    }

    public function assignment(FormAnswer $answer): ?array
    {
        if (Yii::$app->db->schema->getTableSchema('{{%custom_form_arm_assignment}}', true) === null) {
            return null;
        }
        $row = (new Query())->from('{{%custom_form_arm_assignment}}')->where(['answer_id' => (int)$answer->id])->one();
        return $row ?: null;
    }

    /**
     * Assign once, inside a transaction that locks the allocation row.
     */
    public function assignIfDue(FormAnswer $answer, array $values, ?int $postedPage, bool $completing): ?array
    {
        $form = $answer->form;
        if (!$form || !self::active($form) || $answer->isTest()) {
            return $this->assignment($answer);
        }
        $existing = $this->assignment($answer);
        if ($existing) {
            return $existing;
        }
        $cfg = $this->config($form);
        if (!$cfg['arms']) {
            return null;
        }
        if ($cfg['assign'] === 'after_page') {
            $built = (new FormPager())->buildPages($form->fields);
            $built = $this->applyPageOrder($built, $answer);
            $need = $built['pageKeyIndex'][$cfg['assign_page']] ?? null;
            if ($need === null) {
                return null;
            }
            $postedPage = $postedPage ?? 0;
            $reached = $completing ? $postedPage >= (int)$need : $postedPage > (int)$need;
            if (!$reached) {
                return null;
            }
        }
        if ($form->hidesIdentityFromManagers()) {
            foreach ($cfg['strata'] as $factor) {
                if ($factor['source'] === 'panel') {
                    return null;
                }
            }
        }
        $stratum = $this->stratumKey($form, $cfg, $values, $answer);
        if ($stratum === null) {
            return null;
        }
        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        try {
            $again = (new Query())->from('{{%custom_form_arm_assignment}}')->where(['answer_id' => (int)$answer->id])->one($db);
            if ($again) {
                $tx->commit();
                return $again;
            }
            $quotaChoice = (new QuotaService())->chooseArm($form, $answer, $values, $cfg['arms'], $this->seedFor($answer, 'arm-quota'));
            if ($quotaChoice === false) {
                $tx->rollBack();
                return null;
            }
            $code = is_string($quotaChoice) && $quotaChoice !== ''
                ? $quotaChoice
                : $this->drawArm($form, $cfg, $stratum, $answer);
            if ($code === '') {
                $tx->rollBack();
                return null;
            }
            $name = $code;
            foreach ($cfg['arms'] as $arm) {
                if ($arm['code'] === $code) {
                    $name = $arm['name'];
                }
            }
            $db->createCommand()->insert('{{%custom_form_arm_assignment}}', [
                'answer_id' => (int)$answer->id,
                'arm_code' => $code,
                'arm_name' => $name,
                'method' => $cfg['method'],
                'stratum_key' => $stratum,
                'assigned_at' => gmdate('Y-m-d H:i:s'),
                'assigned_by' => null,
            ])->execute();
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        return $this->assignment($answer);
    }

    public function override(FormAnswer $answer, string $toCode, string $reason, ?int $actorId): bool
    {
        $current = $this->assignment($answer);
        $form = $answer->form;
        if (!$current || !$form || trim($reason) === '') {
            return false;
        }
        $toCode = trim($toCode);
        $known = false;
        $name = $toCode;
        foreach ($this->config($form)['arms'] as $arm) {
            if ($arm['code'] === $toCode) {
                $known = true;
                $name = $arm['name'];
            }
        }
        if (!$known || $toCode === (string)$current['arm_code']) {
            return false;
        }
        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        try {
            $db->createCommand()->insert('{{%custom_form_arm_override}}', [
                'answer_id' => (int)$answer->id,
                'from_code' => (string)$current['arm_code'],
                'to_code' => $toCode,
                'reason' => trim($reason),
                'actor_id' => $actorId,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ])->execute();
            $db->createCommand()->update('{{%custom_form_arm_assignment}}', [
                'arm_code' => $toCode,
                'arm_name' => $name,
            ], ['answer_id' => (int)$answer->id])->execute();
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            return false;
        }
        return true;
    }

    /**
     * @return array<int, array{code:string,name:string,assigned:int,completed:int}>
     */
    public function allocationSummary(CustomForm $form): array
    {
        if (Yii::$app->db->schema->getTableSchema('{{%custom_form_arm_assignment}}', true) === null) {
            return [];
        }
        $cfg = $this->config($form);
        $rows = [];
        foreach ($cfg['arms'] as $arm) {
            $assigned = (int)(new Query())
                ->from(['g' => '{{%custom_form_arm_assignment}}'])
                ->innerJoin(['a' => '{{%custom_form_answer}}'], 'a.id = g.answer_id')
                ->where(['a.form_id' => (int)$form->id, 'g.arm_code' => $arm['code'], 'a.is_test' => 0])
                ->count();
            $completed = (int)(new Query())
                ->from(['g' => '{{%custom_form_arm_assignment}}'])
                ->innerJoin(['a' => '{{%custom_form_answer}}'], 'a.id = g.answer_id')
                ->where(['a.form_id' => (int)$form->id, 'g.arm_code' => $arm['code'], 'a.is_test' => 0, 'a.status' => FormAnswer::STATUS_COMPLETE])
                ->andWhere(['or', ['a.outcome' => ''], ['a.outcome' => FormAnswer::OUTCOME_COMPLETE]])
                ->count();
            $rows[] = [
                'code' => $arm['code'],
                'name' => $arm['name'],
                'assigned' => $assigned,
                'completed' => $completed,
            ];
        }
        return $rows;
    }

    private function seedFor(FormAnswer $answer, string $scope): int
    {
        $row = (new Query())->from('{{%custom_form_presentation}}')->where(['answer_id' => (int)$answer->id])->one();
        $hex = (string)($row['seed'] ?? '');
        if ($hex === '') {
            $hex = '0';
        }
        return $this->engine->seedInt($hex, $scope);
    }

    private function nextRotateOffset(int $formId, string $scope, int $length): int
    {
        if ($length < 2) {
            return 0;
        }
        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        try {
            $row = (new Query())
                ->from('{{%custom_form_rotate_seq}}')
                ->where(['form_id' => $formId, 'scope_key' => $scope])
                ->one($db);
            if (!$row) {
                $db->createCommand()->insert('{{%custom_form_rotate_seq}}', [
                    'form_id' => $formId,
                    'scope_key' => $scope,
                    'next_offset' => 1 % $length,
                ])->execute();
                $tx->commit();
                return 0;
            }
            $db->createCommand('SELECT next_offset FROM {{%custom_form_rotate_seq}} WHERE form_id = :f AND scope_key = :s FOR UPDATE', [
                ':f' => $formId,
                ':s' => $scope,
            ])->queryScalar();
            $current = (int)$row['next_offset'] % $length;
            $db->createCommand()->update('{{%custom_form_rotate_seq}}', [
                'next_offset' => ($current + 1) % $length,
            ], ['form_id' => $formId, 'scope_key' => $scope])->execute();
            $tx->commit();
            return $current;
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    /**
     * @param string[] $order
     */
    private function storeScope(FormAnswer $answer, string $bucket, string $key, array $order): void
    {
        $orders = $this->orders($answer);
        $orders[$bucket][$key] = array_values($order);
        Yii::$app->db->createCommand()->update('{{%custom_form_presentation}}', [
            'orders_json' => json_encode([
                'options' => $orders['options'],
                'questions' => $orders['questions'],
                'pages' => $orders['pages'],
                'shown' => $orders['shown'],
            ], JSON_UNESCAPED_UNICODE),
        ], ['answer_id' => (int)$answer->id])->execute();
    }

    /**
     * @param array $cfg
     * @param array $values
     */
    private function stratumKey(CustomForm $form, array $cfg, array $values, FormAnswer $answer): ?string
    {
        if ($cfg['method'] !== 'stratified') {
            return '';
        }
        $parts = [];
        foreach ($cfg['strata'] as $factor) {
            if ($factor['source'] === 'field') {
                $raw = null;
                foreach ($form->fields as $field) {
                    if (trim((string)$field->variable) === $factor['key']) {
                        $raw = $values[(int)$field->id] ?? $values[$factor['key']] ?? null;
                        break;
                    }
                }
                if ($raw === null || $raw === '' || $raw === []) {
                    return null;
                }
                $parts[] = is_array($raw) ? implode(',', array_map('strval', $raw)) : strtolower(trim((string)$raw));
            } else {
                $member = $answer->panelMember;
                $demo = $member ? $member->getDemographics() : [];
                $raw = $demo[$factor['key']] ?? null;
                if ($raw === null || $raw === '') {
                    return null;
                }
                $parts[] = strtolower(trim((string)$raw));
            }
        }
        return implode('|', $parts);
    }

    /**
     * @param array $cfg
     */
    private function drawArm(CustomForm $form, array $cfg, string $stratum, FormAnswer $answer): string
    {
        $seed = $this->seedFor($answer, 'arm:' . $stratum);
        $method = $cfg['method'] === 'stratified' ? 'block' : $cfg['method'];
        if ($method === 'simple') {
            return $this->engine->weightedPick($cfg['arms'], $seed);
        }
        if ($method === 'least_filled') {
            $counts = [];
            $rows = (new Query())
                ->from(['g' => '{{%custom_form_arm_assignment}}'])
                ->innerJoin(['a' => '{{%custom_form_answer}}'], 'a.id = g.answer_id')
                ->select(['g.arm_code', 'n' => 'COUNT(*)'])
                ->where(['a.form_id' => (int)$form->id, 'a.is_test' => 0, 'g.stratum_key' => $stratum])
                ->groupBy('g.arm_code')
                ->all();
            foreach ($rows as $row) {
                $counts[(string)$row['arm_code']] = (int)$row['n'];
            }
            $codes = array_map(static fn($arm) => $arm['code'], $cfg['arms']);
            return $this->engine->leastFilled($counts, $codes, $seed);
        }
        $db = Yii::$app->db;
        $row = (new Query())->from('{{%custom_form_arm_allocation}}')->where([
            'form_id' => (int)$form->id,
            'stratum_key' => $stratum,
        ])->one($db);
        if (!$row) {
            $block = $this->engine->block($cfg['arms'], (int)$cfg['block_size'], $seed);
            $db->createCommand()->insert('{{%custom_form_arm_allocation}}', [
                'form_id' => (int)$form->id,
                'stratum_key' => $stratum,
                'next_index' => 1,
                'block_json' => json_encode($block, JSON_UNESCAPED_UNICODE),
            ])->execute();
            return (string)($block[0] ?? '');
        }
        $db->createCommand(
            'SELECT next_index FROM {{%custom_form_arm_allocation}} WHERE form_id = :f AND stratum_key = :s FOR UPDATE',
            [':f' => (int)$form->id, ':s' => $stratum]
        )->queryScalar();
        $block = json_decode((string)$row['block_json'], true);
        if (!is_array($block) || !$block) {
            $block = $this->engine->block($cfg['arms'], (int)$cfg['block_size'], $seed);
        }
        $index = (int)$row['next_index'];
        if ($index >= count($block)) {
            $block = $this->engine->block($cfg['arms'], (int)$cfg['block_size'], $seed + $index);
            $index = 0;
        }
        $code = (string)$block[$index];
        $db->createCommand()->update('{{%custom_form_arm_allocation}}', [
            'next_index' => $index + 1,
            'block_json' => json_encode(array_values($block), JSON_UNESCAPED_UNICODE),
        ], ['form_id' => (int)$form->id, 'stratum_key' => $stratum])->execute();
        return $code;
    }
}
