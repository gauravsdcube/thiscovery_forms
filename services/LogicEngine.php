<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\FormField;

/**
 * Compound show/hide/skip/goto rules used by fill JS and SubmitForm.
 */
class LogicEngine
{
    public const ACTION_SHOW = 'show';
    public const ACTION_HIDE = 'hide';
    /** Read as ACTION_HIDE; no longer offered (LOG-10). */
    public const ACTION_SKIP = 'skip';
    public const ACTION_SKIP_PAGE = 'skip_page';
    public const ACTION_GOTO_PAGE = 'goto_page';
    public const ACTION_GOTO_END = 'goto_end';
    public const ACTION_SCREEN_OUT = 'screen_out';

    public static function actionLabels(): array
    {
        return [
            self::ACTION_SHOW => \Yii::t('ThiscoveryFormsModule.base', 'Show this question if'),
            self::ACTION_HIDE => \Yii::t('ThiscoveryFormsModule.base', 'Hide this question if'),
            self::ACTION_SKIP_PAGE => \Yii::t('ThiscoveryFormsModule.base', 'Skip this page if'),
            self::ACTION_GOTO_PAGE => \Yii::t('ThiscoveryFormsModule.base', 'Go to page if'),
            self::ACTION_GOTO_END => \Yii::t('ThiscoveryFormsModule.base', 'Go to end if'),
            self::ACTION_SCREEN_OUT => \Yii::t('ThiscoveryFormsModule.base', 'End as screened out if'),
        ];
    }

    public static function actionLabelsForType(string $type): array
    {
        if ($type === FormField::TYPE_QUESTION_GROUP) {
            return [
                self::ACTION_SHOW => \Yii::t('ThiscoveryFormsModule.base', 'Show this group if'),
                self::ACTION_HIDE => \Yii::t('ThiscoveryFormsModule.base', 'Hide this group if'),
            ];
        }
        return self::actionLabels();
    }

    public static function defaultLogic(): array
    {
        return [
            'action' => self::ACTION_SHOW,
            'combinator' => 'and',
            'gotoPageKey' => '',
            'text' => '',
            'rules' => [],
            'when' => null,
        ];
    }

    public static function normalize(?array $logic): array
    {
        $base = self::defaultLogic();
        if (!is_array($logic)) {
            return $base;
        }
        $action = (string)($logic['action'] ?? $base['action']);
        // "Skip this question" always behaved exactly like "Hide", so it is offered no more
        // and a stored skip is read as hide (LOG-10).
        if ($action === self::ACTION_SKIP) {
            $action = self::ACTION_HIDE;
        }
        if (!isset(self::actionLabels()[$action])) {
            $action = self::ACTION_SHOW;
        }
        $combinator = strtolower((string)($logic['combinator'] ?? 'and'));
        if (!in_array($combinator, ['and', 'or'], true)) {
            $combinator = 'and';
        }
        $rules = [];
        foreach (($logic['rules'] ?? []) as $rule) {
            $normalized = self::normalizeRule($rule);
            if ($normalized !== null) {
                $rules[] = $normalized;
            }
        }

        return [
            'v' => 1,
            'action' => $action,
            'combinator' => $combinator,
            'gotoPageKey' => trim((string)($logic['gotoPageKey'] ?? $logic['goto'] ?? '')),
            'text' => trim((string)($logic['text'] ?? $logic['formula'] ?? '')),
            'rules' => $rules,
            'when' => isset($logic['when']) && is_array($logic['when']) ? $logic['when'] : null,
        ];
    }

    /**
     * Shown only when an old rule cannot be converted, for example an unknown loop aggregate.
     * A normal field, operator, and value rule is converted by upgrade() and is not an error.
     */
    public static function legacyMessage(): string
    {
        return \Yii::t(
            'ThiscoveryFormsModule.base',
            'This rule uses the old field, operator, and value format and could not be turned into a formula. Loop checks must use any, all, count, or sum.'
        );
    }

    public static function containsLegacy($node): bool
    {
        if (!is_array($node)) {
            return false;
        }
        $isTree = isset($node['op']) || (isset($node['when']) && is_array($node['when']));
        if (!$isTree && (isset($node['fieldKey']) || isset($node['all']) || isset($node['any']))) {
            return true;
        }
        if (!$isTree && isset($node['operator'])) {
            return true;
        }
        foreach ($node as $value) {
            if (is_array($value) && self::containsLegacy($value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * A rule saved as field, operator, and value (including and/or groups) becomes a formula.
     * Null means it cannot be converted, for example an unknown loop aggregate.
     *
     * @param array<string,mixed> $logic
     * @param FormField[] $fields
     * @return array<string,mixed>|null
     */
    public static function upgrade(array $logic, array $fields = []): ?array
    {
        if (isset($logic['when']) && is_array($logic['when']) && !self::containsLegacy($logic['when'])) {
            $clean = self::normalize($logic);
            $clean['rules'] = [];
            return $clean;
        }
        if (!self::containsLegacy($logic)) {
            return self::normalize($logic);
        }
        $rules = $logic['rules'] ?? null;
        if (!is_array($rules) || $rules === []) {
            if (isset($logic['fieldKey']) || isset($logic['all']) || isset($logic['any']) || isset($logic['operator'])) {
                $rules = [$logic];
            } else {
                return null;
            }
        }
        $combinator = strtolower((string)($logic['combinator'] ?? 'and')) === 'or' ? 'any' : 'all';
        $tree = \humhub\modules\thiscoveryForms\services\formula\RuleBuilder::fromSimple([$combinator => $rules]);
        if ($tree === null) {
            return null;
        }
        $tree = self::readableTree($tree, self::fieldIndex($fields));
        try {
            $text = self::printFormula($tree);
            $parsed = (new \humhub\modules\thiscoveryForms\services\formula\Parser())->parse($text);
        } catch (\Throwable $e) {
            return null;
        }
        $out = self::defaultLogic();
        $action = (string)($logic['action'] ?? self::ACTION_SHOW);
        if ($action === self::ACTION_SKIP) {
            $action = self::ACTION_HIDE;
        }
        $out['action'] = isset(self::actionLabels()[$action]) ? $action : self::ACTION_SHOW;
        $out['gotoPageKey'] = trim((string)($logic['gotoPageKey'] ?? $logic['goto'] ?? ''));
        $out['text'] = $text;
        $out['when'] = $parsed;
        $out['v'] = 1;
        return $out;
    }

    /**
     * @param array<string,mixed> $logic
     * @param FormField[] $fields
     */
    public static function formulaTextFromLegacy(array $logic, array $fields = []): ?string
    {
        $upgraded = self::upgrade($logic, $fields);
        if (!is_array($upgraded)) {
            return null;
        }
        $text = trim((string)($upgraded['text'] ?? ''));
        return $text === '' ? null : $text;
    }

    /**
     * Formula text posted by the studio or an import file.
     * A formula already written is kept. A field, operator, and value rule, including the
     * old condition columns, is converted. Null means the row has no rule.
     *
     * @param array<string,mixed> $row
     * @param FormField[] $fields
     * @throws \InvalidArgumentException when a rule is present and cannot be converted
     */
    public static function postedFormula(array $row, array $fields = []): ?string
    {
        $formula = trim((string)($row['logic_formula'] ?? ''));
        if ($formula !== '') {
            return $formula;
        }
        $rules = is_array($row['logic_rules'] ?? null) ? $row['logic_rules'] : [];
        $kept = json_decode((string)($row['logic_keep'] ?? ''), true);
        $condition = trim((string)($row['condition_field'] ?? ''));
        if ($rules !== []) {
            $converted = self::formulaTextFromLegacy([
                'action' => (string)($row['logic_action'] ?? self::ACTION_SHOW),
                'combinator' => (string)($row['logic_combinator'] ?? 'and'),
                'gotoPageKey' => (string)($row['logic_goto'] ?? ''),
                'rules' => $rules,
            ], $fields);
            if ($converted !== null) {
                return $converted;
            }
        }
        if ($condition !== '') {
            $converted = self::formulaTextFromLegacy([
                'rules' => [[
                    'fieldKey' => $condition,
                    'operator' => (string)($row['condition_operator'] ?? 'equals'),
                    'value' => (string)($row['condition_value'] ?? ''),
                ]],
            ], $fields);
            if ($converted !== null) {
                return $converted;
            }
        }
        if (is_array($kept) && self::containsLegacy($kept)) {
            $converted = self::formulaTextFromLegacy($kept, $fields);
            if ($converted !== null) {
                return $converted;
            }
        }
        $unconverted = ($rules !== [] && self::containsLegacy($rules))
            || $condition !== ''
            || (is_array($kept) && self::containsLegacy($kept));
        if ($unconverted) {
            throw new \InvalidArgumentException(self::legacyMessage());
        }
        return null;
    }

    /**
     * @param FormField[] $fields
     * @return array<int,FormField>
     */
    private static function fieldIndex(array $fields): array
    {
        $byId = [];
        foreach ($fields as $field) {
            if ($field instanceof FormField && (int)$field->id > 0) {
                $byId[(int)$field->id] = $field;
            }
        }
        return $byId;
    }

    /**
     * @param array<string,mixed> $node
     * @param array<int,FormField> $byId
     * @return array<string,mixed>
     */
    private static function readableTree(array $node, array $byId): array
    {
        if (($node['op'] ?? '') === 'ref' && ($node['ref'] ?? '') === 'field') {
            $name = (string)($node['name'] ?? '');
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                $id = 0;
                if (ctype_digit($name)) {
                    $id = (int)$name;
                } elseif (str_starts_with($name, 'id') && ctype_digit(substr($name, 2))) {
                    $id = (int)substr($name, 2);
                }
                $variable = $id > 0 && isset($byId[$id]) ? trim((string)$byId[$id]->variable) : '';
                if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $variable)) {
                    $node['name'] = $variable;
                } elseif ($id > 0) {
                    $node['name'] = 'id' . $id;
                }
            }
        }
        if (isset($node['args']) && is_array($node['args'])) {
            foreach ($node['args'] as $i => $arg) {
                if (is_array($arg)) {
                    $node['args'][$i] = self::readableTree($arg, $byId);
                }
            }
        }
        return $node;
    }

    /** @param array<string,mixed> $node */
    private static function printFormula(array $node, ?string $parentOp = null): string
    {
        $op = (string)($node['op'] ?? '');
        $args = is_array($node['args'] ?? null) ? $node['args'] : [];
        if ($op === 'and' || $op === 'or') {
            $parts = [];
            foreach ($args as $arg) {
                if (!is_array($arg)) {
                    throw new \InvalidArgumentException('A converted rule is incomplete.');
                }
                $parts[] = self::printFormula($arg, $op);
            }
            $text = implode($op === 'and' ? ' and ' : ' or ', $parts);
            if ($parentOp !== null && $parentOp !== $op) {
                return '(' . $text . ')';
            }
            return $text;
        }
        $symbols = ['eq' => '=', 'ne' => '!=', 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<='];
        if (isset($symbols[$op]) && count($args) === 2 && is_array($args[0]) && is_array($args[1])) {
            return self::printValue($args[0]) . ' ' . $symbols[$op] . ' ' . self::printValue($args[1]);
        }
        $calls = ['contains_text', 'is_answered', 'any_eq', 'all_eq', 'between', 'count_answered', 'sum'];
        if (in_array($op, $calls, true)) {
            $inner = [];
            foreach ($args as $arg) {
                if (!is_array($arg)) {
                    throw new \InvalidArgumentException('A converted rule is incomplete.');
                }
                $inner[] = self::printValue($arg);
            }
            return $op . '(' . implode(', ', $inner) . ')';
        }
        throw new \InvalidArgumentException('A converted rule could not be written as a formula.');
    }

    /** @param array<string,mixed> $node */
    private static function printValue(array $node): string
    {
        if (($node['op'] ?? '') === 'lit') {
            $lit = (string)($node['lit'] ?? '');
            if ($lit === 'number') {
                return (string)($node['v'] ?? '0');
            }
            if ($lit === 'bool') {
                return !empty($node['v']) ? 'true' : 'false';
            }
            if ($lit === 'empty') {
                return 'empty';
            }
            $text = (string)($node['v'] ?? '');
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $text) . '"';
        }
        if (($node['op'] ?? '') === 'ref') {
            return self::printRef($node);
        }
        return self::printFormula($node);
    }

    /** @param array<string,mixed> $node */
    private static function printRef(array $node): string
    {
        $kind = (string)($node['ref'] ?? '');
        if ($kind === 'arm') {
            return '[arm]';
        }
        $name = (string)($node['name'] ?? '');
        if ($kind === 'panel') {
            return '[panel:' . $name . ']';
        }
        if ($kind === 'var') {
            return '[var:' . $name . ']';
        }
        if ($kind === 'meta') {
            return '[meta:' . $name . ']';
        }
        if ($kind === 'url') {
            return '[url:' . $name . ']';
        }
        $body = $name;
        if (!empty($node['all'])) {
            $body .= '[*]';
        } elseif (isset($node['instance'])) {
            $body .= '["' . str_replace('"', '\\"', (string)$node['instance']) . '"]';
        } elseif (isset($node['row'])) {
            $body .= '.' . (string)$node['row'];
        }
        return '[' . $body . ']';
    }

    public static function fromFormula(string $text, string $action = self::ACTION_SHOW, string $goto = ''): array
    {
        $text = trim($text);
        $logic = self::defaultLogic();
        $action = $action === self::ACTION_SKIP ? self::ACTION_HIDE : $action;
        $logic['action'] = isset(self::actionLabels()[$action]) ? $action : self::ACTION_SHOW;
        $logic['gotoPageKey'] = $goto;
        $logic['text'] = $text;
        if ($text === '') {
            return $logic;
        }
        $logic['when'] = (new \humhub\modules\thiscoveryForms\services\formula\Parser())->parse($text);
        return self::normalize($logic);
    }

    /**
     * @param mixed $rule
     * @return array<string,mixed>|null
     */
    public static function normalizeRule($rule): ?array
    {
        if (!is_array($rule)) {
            return null;
        }
        if (isset($rule['compound']) && (is_array($rule['compound']) || is_string($rule['compound']))) {
            $decoded = is_array($rule['compound']) ? $rule['compound'] : json_decode((string)$rule['compound'], true);
            return is_array($decoded) ? self::normalizeRule($decoded) : null;
        }
        if (!empty($rule['all']) && is_array($rule['all'])) {
            $inner = [];
            foreach ($rule['all'] as $sub) {
                $normalized = self::normalizeRule($sub);
                if ($normalized !== null) {
                    $inner[] = $normalized;
                }
            }
            return $inner ? ['all' => $inner] : null;
        }
        if (!empty($rule['any']) && is_array($rule['any'])) {
            $inner = [];
            foreach ($rule['any'] as $sub) {
                $normalized = self::normalizeRule($sub);
                if ($normalized !== null) {
                    $inner[] = $normalized;
                }
            }
            return $inner ? ['any' => $inner] : null;
        }
        if (isset($rule['op']) || (isset($rule['when']) && is_array($rule['when']))) {
            return $rule;
        }
        $fieldKey = trim((string)($rule['fieldKey'] ?? ''));
        if ($fieldKey === '') {
            return null;
        }
        $operator = (string)($rule['operator'] ?? FormField::OP_EQUALS);
        if (!isset(FormField::getOperatorLabels()[$operator])) {
            $operator = FormField::OP_EQUALS;
        }
        $leaf = [
            'fieldKey' => $fieldKey,
            'operator' => $operator,
            'value' => self::normalizeRuleValue($rule['value'] ?? ''),
        ];
        $source = (string)($rule['source'] ?? '');
        if ($source === 'panel' || $source === 'arm') {
            $leaf['source'] = $source;
        }
        $aggregate = (string)($rule['aggregate'] ?? '');
        if (in_array($aggregate, ['any', 'all', 'count', 'sum'], true)) {
            $leaf['aggregate'] = $aggregate;
        }
        return $leaf;
    }

    /**
     * First fieldKey/operator/value rule inside nested all/any groups.
     *
     * @param list<array<string,mixed>> $rules
     * @return array{fieldKey?:string,operator?:string,value?:string}|null
     */
    public static function firstLeafRule(array $rules): ?array
    {
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            if (!empty($rule['all']) && is_array($rule['all'])) {
                $found = self::firstLeafRule($rule['all']);
                if ($found) {
                    return $found;
                }
                continue;
            }
            if (!empty($rule['any']) && is_array($rule['any'])) {
                $found = self::firstLeafRule($rule['any']);
                if ($found) {
                    return $found;
                }
                continue;
            }
            if (trim((string)($rule['fieldKey'] ?? '')) !== '') {
                return $rule;
            }
        }
        return null;
    }

    /**
     * Option text as listed in the studio. Surrounding quotation marks are ignored.
     */
    public static function normalizeRuleValue($value): string
    {
        $value = trim((string)$value);
        $len = strlen($value);
        if ($len >= 2) {
            $quote = $value[0];
            if (($quote === '"' || $quote === "'") && $value[$len - 1] === $quote) {
                return trim(substr($value, 1, $len - 2));
            }
        }
        return $value;
    }

    public function evaluateRule(array $rule, array $values, array $fields = []): bool
    {
        $upgraded = self::upgrade($rule, $fields);
        if (is_array($upgraded)) {
            $rule = $upgraded;
        }
        if (self::containsLegacy($rule) && empty($rule['when'])) {
            return false;
        }
        if (isset($rule['when']) && is_array($rule['when'])) {
            $tree = $rule['when'];
        } elseif (isset($rule['op'])) {
            $tree = $rule;
        } else {
            return false;
        }
        $today = '';
        if (!isset($values['__loops']) && $fields) {
            $values['__loops'] = LoopService::formulaLoopIds(array_values($fields));
        }
        try {
            $context = $this->contextFor($values, $fields, $today);
            $context->steps = 0;
            return (new \humhub\modules\thiscoveryForms\services\formula\Evaluator($context))->truth($tree);
        } catch (\Throwable $e) {
            // A rule that cannot be evaluated is not met; it never fails the submit (V3-38).
            \Yii::warning('Thiscovery Forms rule could not be evaluated: ' . $e->getMessage(), 'thiscovery-forms');
            return false;
        }
    }

    /** @var array<string, \humhub\modules\thiscoveryForms\services\formula\Context> */
    private static array $contexts = [];

    /** @var array<string, array> */
    private static array $effective = [];

    /**
     * The formula context for these answers, reused while they are unchanged: every rule on
     * the form was rebuilding it, and decoding every question's options, for each check (V3-39).
     */
    private function contextFor(array $values, array $fields, string $today): \humhub\modules\thiscoveryForms\services\formula\Context
    {
        $key = self::structureKey($fields) . ':' . md5((string)json_encode($values)) . ':' . $today;
        if (!isset(self::$contexts[$key])) {
            if (count(self::$contexts) >= 16) {
                array_shift(self::$contexts);
            }
            self::$contexts[$key] = \humhub\modules\thiscoveryForms\services\formula\Context::fromValues($values, $fields, $today);
        }
        return self::$contexts[$key];
    }

    /** @param array<int|string,mixed> $fields */
    private static function structureKey(array $fields): string
    {
        $parts = [];
        foreach ($fields as $field) {
            if ($field instanceof FormField) {
                $parts[] = (int)$field->id . ':' . md5((string)$field->logic_json . '|' . (string)$field->options_json);
            }
        }
        return md5(implode(',', $parts));
    }

    /** For tests that change questions between checks in one request. */
    public static function resetCaches(): void
    {
        self::$contexts = [];
        self::$effective = [];
    }

    public function rulesMet(array $logic, array $values, array $fields = []): bool
    {
        $logic = self::normalize($logic);
        if (!is_array($logic['when'] ?? null)) {
            return true;
        }
        return $this->evaluateRule(['when' => $logic['when']], $values, $fields);
    }

    public function isVisible(FormField $field, array $values, array $allFields = []): bool
    {
        $logic = $field->getLogic();
        if (empty($logic['rules']) && empty($logic['when'])) {
            return true;
        }
        $met = $this->rulesMet($logic, $values, $allFields);
        $action = $logic['action'] ?? self::ACTION_SHOW;
        if ($action === self::ACTION_HIDE || $action === self::ACTION_SKIP) {
            return !$met;
        }
        if ($action === self::ACTION_SHOW) {
            return $met;
        }
        return true;
    }

    /**
     * Own logic plus any enclosing question group.
     * A hidden question's answer is treated as empty before later rules run, and that
     * pass repeats until the visible set stops changing.
     *
     * @param FormField[] $orderedFields
     */
    public function isFieldVisible(FormField $field, array $orderedFields, array $values): bool
    {
        if (RandomisationService::hides($field)) {
            return false;
        }
        if ($orderedFields) {
            $values = $this->valuesIgnoringHidden($orderedFields, $values);
        }
        return $this->fieldShown($field, $orderedFields, $values);
    }

    /**
     * @param FormField[] $orderedFields
     */
    private function fieldShown(FormField $field, array $orderedFields, array $values): bool
    {
        if (!$this->isVisible($field, $values, $orderedFields)) {
            return false;
        }
        if (!$orderedFields) {
            return true;
        }
        $open = [];
        foreach ($orderedFields as $candidate) {
            if ((int)$candidate->id === (int)$field->id) {
                break;
            }
            if ($candidate->type === FormField::TYPE_QUESTION_GROUP) {
                $open[] = $candidate;
            } elseif ($candidate->type === FormField::TYPE_GROUP_END && $open) {
                array_pop($open);
            }
        }
        foreach ($open as $group) {
            if (!$this->isVisible($group, $values, $orderedFields)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Drop answers for questions that are hidden, then judge again, until stable.
     *
     * @param FormField[] $fields
     */
    /**
     * Answers with every logic-hidden question treated as empty, evaluated in form order.
     *
     * @param FormField[] $fields
     * @param array<int|string,mixed> $values
     * @return array<int|string,mixed>
     */
    public function effectiveValues(array $fields, array $values): array
    {
        return $this->valuesIgnoringHidden($fields, $values);
    }

    private function valuesIgnoringHidden(array $fields, array $values): array
    {
        $fields = array_values(array_filter($fields, static fn ($field) => $field instanceof FormField));
        if (!$fields) {
            return $values;
        }
        // Each question's visibility check re-ran this fixed point over every question; the
        // result depends only on the structure and the answers, so it is worked out once (V3-39).
        $key = self::structureKey($fields) . ':' . md5((string)json_encode($values)) . ':' . (RandomisationService::$current ? spl_object_id(RandomisationService::$current) : 0);
        if (isset(self::$effective[$key])) {
            return self::$effective[$key];
        }
        if (count(self::$effective) >= 16) {
            array_shift(self::$effective);
        }
        return self::$effective[$key] = $this->computeValuesIgnoringHidden($fields, $values);
    }

    /**
     * @param FormField[] $fields
     */
    private function computeValuesIgnoringHidden(array $fields, array $values): array
    {
        $limit = count($fields) + 1;
        for ($pass = 0; $pass < $limit; $pass++) {
            $changed = false;
            foreach ($fields as $field) {
                if ($this->fieldShown($field, $fields, $values)) {
                    continue;
                }
                foreach ($this->answerKeys($field) as $key) {
                    if (!array_key_exists($key, $values)) {
                        continue;
                    }
                    if ($values[$key] === null || $values[$key] === '' || $values[$key] === []) {
                        continue;
                    }
                    $values[$key] = null;
                    $changed = true;
                }
            }
            if (!$changed) {
                break;
            }
        }
        return $values;
    }

    /**
     * @return array<int, int|string>
     */
    private function answerKeys(FormField $field): array
    {
        $keys = [(int)$field->id];
        $variable = trim((string)$field->variable);
        if ($variable !== '' && $variable !== (string)$field->id) {
            $keys[] = $variable;
        }
        $keys[] = 'id' . (int)$field->id;
        return $keys;
    }

    /**
     * First matching navigation action on fields of the current page.
     * @param FormField[] $fieldsOnPage
     * @param FormField[] $allFields Full form fields so coded options resolve against labels
     * @return array{action:string,gotoPageKey:string}|null
     */
    public function pageNavigation(array $fieldsOnPage, array $values, array $allFields = []): ?array
    {
        $lookup = $allFields ?: $fieldsOnPage;
        $values = $this->valuesIgnoringHidden($lookup, $values);
        foreach ($fieldsOnPage as $field) {
            $logic = $field->getLogic();
            $action = $logic['action'] ?? '';
            if (!in_array($action, [self::ACTION_GOTO_PAGE, self::ACTION_GOTO_END, self::ACTION_SCREEN_OUT], true)) {
                continue;
            }
            if ($action === self::ACTION_SCREEN_OUT && !\humhub\modules\thiscoveryForms\Module::randomisationEnabled()) {
                continue;
            }
            if (empty($logic['when']) || !$this->rulesMet($logic, $values, $lookup)) {
                continue;
            }
            // A rule on a question the respondent can't see (a hidden group, a randomised-out
            // item) doesn't route them anywhere (LOG-8).
            if (!$this->isFieldVisible($field, $lookup, $values)) {
                continue;
            }
            return [
                'action' => $action,
                'gotoPageKey' => (string)($logic['gotoPageKey'] ?? ''),
            ];
        }
        return null;
    }

    /**
     * @param FormField[] $fieldsOnPage
     * @param FormField[] $allFields Full form fields so coded options resolve against labels
     */
    public function shouldSkipPage(array $fieldsOnPage, array $values, array $allFields = []): bool
    {
        if (!$fieldsOnPage) {
            return true;
        }
        $lookup = $allFields ?: $fieldsOnPage;
        $values = $this->valuesIgnoringHidden($lookup, $values);
        foreach ($fieldsOnPage as $field) {
            $logic = $field->getLogic();
            if (($logic['action'] ?? '') !== self::ACTION_SKIP_PAGE) {
                continue;
            }
            if (!empty($logic['when']) && $this->rulesMet($logic, $values, $lookup)
                && $this->isFieldVisible($field, $lookup, $values)) {
                return true;
            }
        }
        return !$this->pageHasVisibleContent($fieldsOnPage, $values, $lookup);
    }

    /**
     * @param FormField[] $fieldsOnPage
     * @param FormField[] $allFields Ordered fields for group + choice matching
     */
    public function pageHasVisibleContent(array $fieldsOnPage, array $values, array $allFields = []): bool
    {
        $ordered = $allFields ?: $fieldsOnPage;
        foreach ($fieldsOnPage as $field) {
            if ($field->type === FormField::TYPE_GROUP_END) {
                continue;
            }
            if ($field->isHiddenFromRespondent()) {
                continue;
            }
            if ($this->isFieldVisible($field, $ordered, $values)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param FormField[] $fields
     */
    private function fieldByKey(array $fields, string $fieldKey): ?FormField
    {
        if ($fieldKey === '' || !$fields) {
            return null;
        }
        $wantId = null;
        if (ctype_digit($fieldKey)) {
            $wantId = (int)$fieldKey;
        } elseif (str_starts_with($fieldKey, 'id') && ctype_digit(substr($fieldKey, 2))) {
            $wantId = (int)substr($fieldKey, 2);
        }
        foreach ($fields as $field) {
            if (!$field instanceof FormField) {
                continue;
            }
            if ($wantId !== null && (int)$field->id === $wantId) {
                return $field;
            }
            if (FormField::studioKey((int)$field->id) === $fieldKey) {
                return $field;
            }
            if (strcasecmp(trim((string)$field->variable), $fieldKey) === 0) {
                return $field;
            }
        }
        return null;
    }
}
