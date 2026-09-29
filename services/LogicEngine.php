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
            self::ACTION_SKIP => \Yii::t('ThiscoveryFormsModule.base', 'Skip this question if'),
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
            'rules' => [],
        ];
    }

    public static function normalize(?array $logic): array
    {
        $base = self::defaultLogic();
        if (!is_array($logic)) {
            return $base;
        }
        $action = (string)($logic['action'] ?? $base['action']);
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
            'action' => $action,
            'combinator' => $combinator,
            'gotoPageKey' => trim((string)($logic['gotoPageKey'] ?? '')),
            'rules' => $rules,
        ];
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

    public static function fromLegacy(?int $fieldId, ?string $operator, ?string $value): array
    {
        $logic = self::defaultLogic();
        if (!$fieldId) {
            return $logic;
        }
        $logic['rules'][] = [
            'fieldKey' => (string)$fieldId,
            'operator' => $operator ?: FormField::OP_EQUALS,
            'value' => (string)$value,
        ];
        return self::normalize($logic);
    }

    public function evaluateRule(array $rule, array $values, array $fields = []): bool
    {
        if (!empty($rule['all']) && is_array($rule['all'])) {
            if ($rule['all'] === []) {
                return false;
            }
            foreach ($rule['all'] as $sub) {
                if (!is_array($sub) || !$this->evaluateRule($sub, $values, $fields)) {
                    return false;
                }
            }
            return true;
        }
        if (!empty($rule['any']) && is_array($rule['any'])) {
            foreach ($rule['any'] as $sub) {
                if (is_array($sub) && $this->evaluateRule($sub, $values, $fields)) {
                    return true;
                }
            }
            return false;
        }
        $fieldKey = (string)($rule['fieldKey'] ?? '');
        if ($fieldKey === '') {
            return false;
        }
        $raw = $values[$fieldKey] ?? null;
        if ($raw === null && ctype_digit($fieldKey)) {
            $raw = $values[(int)$fieldKey] ?? null;
        }
        if ($raw === null && str_starts_with($fieldKey, 'id') && ctype_digit(substr($fieldKey, 2))) {
            $id = substr($fieldKey, 2);
            $raw = $values[$id] ?? $values[(int)$id] ?? null;
        }
        if ($raw === null && $fields) {
            $named = $this->fieldByKey($fields, $fieldKey);
            if ($named) {
                $raw = $values[(int)$named->id] ?? $values[(string)$named->id] ?? null;
            }
        }
        $op = (string)($rule['operator'] ?? FormField::OP_EQUALS);
        $expected = self::normalizeRuleValue($rule['value'] ?? '');
        $leafSource = (string)($rule['source'] ?? '');
        if ($leafSource === 'panel' || str_starts_with($fieldKey, 'panel.')) {
            $attr = str_starts_with($fieldKey, 'panel.') ? $fieldKey : 'panel.' . $fieldKey;
            return $this->compare($values[$attr] ?? null, $op, $expected);
        }
        if ($leafSource === 'arm' || $fieldKey === 'arm') {
            return $this->compare($values['arm'] ?? null, $op, $expected);
        }

        $source = $this->fieldByKey($fields, $fieldKey);
        if ($source && FormField::isChoiceType($source->type) && in_array($op, [FormField::OP_EQUALS, FormField::OP_NOT_EQUALS], true)) {
            $hit = $source->choiceMatchesExpected($raw, $expected);
            return $op === FormField::OP_EQUALS ? $hit : !$hit;
        }

        return $this->compare($raw, $op, $expected);
    }

    public function rulesMet(array $logic, array $values, array $fields = []): bool
    {
        $logic = self::normalize($logic);
        if (!$logic['rules']) {
            return true;
        }
        $results = [];
        foreach ($logic['rules'] as $rule) {
            $results[] = $this->evaluateRule($rule, $values, $fields);
        }
        if ($logic['combinator'] === 'or') {
            return in_array(true, $results, true);
        }
        return !in_array(false, $results, true);
    }

    public function isVisible(FormField $field, array $values, array $allFields = []): bool
    {
        $logic = $field->getLogic();
        if (empty($logic['rules'])) {
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
    private function valuesIgnoringHidden(array $fields, array $values): array
    {
        $fields = array_values(array_filter($fields, static fn ($field) => $field instanceof FormField));
        if (!$fields) {
            return $values;
        }
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
            if (empty($logic['rules']) || !$this->rulesMet($logic, $values, $lookup)) {
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
            if (!empty($logic['rules']) && $this->rulesMet($logic, $values, $lookup)) {
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

    private function compare($raw, string $op, string $expected): bool
    {
        if (is_array($raw)) {
            $list = $this->flatten($raw);
            if ($op === FormField::OP_CHECKED) {
                return $expected !== '' ? $this->listIncludes($list, $expected) : $list !== [];
            }
            if ($op === FormField::OP_EQUALS) {
                return $this->listIncludes($list, $expected);
            }
            if ($op === FormField::OP_NOT_EQUALS) {
                return !$this->listIncludes($list, $expected);
            }
            if ($op === FormField::OP_CONTAINS) {
                if ($expected === '') {
                    return false;
                }
                foreach ($list as $item) {
                    if (mb_stripos($item, $expected) !== false) {
                        return true;
                    }
                }
                return false;
            }
            if (in_array($op, [FormField::OP_GT, FormField::OP_GTE, FormField::OP_LT, FormField::OP_LTE], true)) {
                foreach ($list as $item) {
                    if ($this->compareNumeric($item, $op, $expected)) {
                        return true;
                    }
                }
                return false;
            }
            if ($op === FormField::OP_BETWEEN) {
                foreach ($list as $item) {
                    if ($this->valueBetween($item, $expected)) {
                        return true;
                    }
                }
                return false;
            }
            return false;
        }

        $value = (string)$raw;
        switch ($op) {
            case FormField::OP_EQUALS:
                return FormField::choiceValueMatchesOption($value, $expected);
            case FormField::OP_NOT_EQUALS:
                return !FormField::choiceValueMatchesOption($value, $expected);
            case FormField::OP_CONTAINS:
                return $expected !== '' && mb_stripos($value, $expected) !== false;
            case FormField::OP_CHECKED:
                return $value !== '' && $value !== '0';
            case FormField::OP_GT:
            case FormField::OP_GTE:
            case FormField::OP_LT:
            case FormField::OP_LTE:
                return $this->compareNumeric($value, $op, $expected);
            case FormField::OP_BETWEEN:
                return $this->valueBetween($value, $expected);
            default:
                return false;
        }
    }

    /**
     * Inclusive range. The expected value is "min,max".
     */
    private function valueBetween(string $value, string $expected): bool
    {
        if ($value === '' || !is_numeric($value)) {
            return false;
        }
        $parts = array_map('trim', explode(',', $expected, 2));
        if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
            return false;
        }
        $low = (float)$parts[0];
        $high = (float)$parts[1];
        if ($low > $high) {
            [$low, $high] = [$high, $low];
        }
        $number = (float)$value;
        return $number >= $low && $number <= $high;
    }

    private function compareNumeric(string $value, string $op, string $expected): bool
    {
        if ($value === '' || $expected === '' || !is_numeric($value) || !is_numeric($expected)) {
            return false;
        }
        $left = (float)$value;
        $right = (float)$expected;
        return match ($op) {
            FormField::OP_GT => $left > $right,
            FormField::OP_GTE => $left >= $right,
            FormField::OP_LT => $left < $right,
            FormField::OP_LTE => $left <= $right,
            default => false,
        };
    }

    /**
     * @return string[]
     */
    private function flatten($raw): array
    {
        $out = [];
        $walk = static function ($node) use (&$out, &$walk) {
            if (is_array($node)) {
                foreach ($node as $v) {
                    $walk($v);
                }
                return;
            }
            $s = trim((string)$node);
            if ($s !== '') {
                $out[] = $s;
            }
        };
        $walk($raw);
        return $out;
    }

    /**
     * @param string[] $list
     */
    private function listIncludes(array $list, string $expected): bool
    {
        foreach ($list as $item) {
            if (FormField::choiceValueMatchesOption((string)$item, $expected)) {
                return true;
            }
        }
        return false;
    }
}
