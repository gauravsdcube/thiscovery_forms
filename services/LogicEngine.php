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
        if (isset($rule['when']) && is_array($rule['when'])) {
            $tree = $rule['when'];
        } else {
            $tree = \humhub\modules\thiscoveryForms\services\formula\RuleBuilder::fromSimple($rule);
        }
        if (!is_array($tree)) {
            return false;
        }
        $today = '';
        $context = \humhub\modules\thiscoveryForms\services\formula\Context::fromValues($values, $fields, $today);
        return (new \humhub\modules\thiscoveryForms\services\formula\Evaluator($context))->truth($tree);
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
}
