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

    public static function actionLabels(): array
    {
        return [
            self::ACTION_SHOW => \Yii::t('ThiscoveryFormsModule.base', 'Show this question if'),
            self::ACTION_HIDE => \Yii::t('ThiscoveryFormsModule.base', 'Hide this question if'),
            self::ACTION_SKIP => \Yii::t('ThiscoveryFormsModule.base', 'Skip this question if'),
            self::ACTION_SKIP_PAGE => \Yii::t('ThiscoveryFormsModule.base', 'Skip this page if'),
            self::ACTION_GOTO_PAGE => \Yii::t('ThiscoveryFormsModule.base', 'Go to page if'),
            self::ACTION_GOTO_END => \Yii::t('ThiscoveryFormsModule.base', 'Go to end if'),
        ];
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
            if (!is_array($rule)) {
                continue;
            }
            $fieldKey = trim((string)($rule['fieldKey'] ?? ''));
            if ($fieldKey === '') {
                continue;
            }
            $operator = (string)($rule['operator'] ?? FormField::OP_EQUALS);
            if (!isset(FormField::getOperatorLabels()[$operator])) {
                $operator = FormField::OP_EQUALS;
            }
            $rules[] = [
                'fieldKey' => $fieldKey,
                'operator' => $operator,
                'value' => (string)($rule['value'] ?? ''),
            ];
        }

        return [
            'action' => $action,
            'combinator' => $combinator,
            'gotoPageKey' => trim((string)($logic['gotoPageKey'] ?? '')),
            'rules' => $rules,
        ];
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

    public function evaluateRule(array $rule, array $values): bool
    {
        $fieldKey = (string)($rule['fieldKey'] ?? '');
        if ($fieldKey === '') {
            return false;
        }
        $raw = $values[$fieldKey] ?? null;
        if ($raw === null && ctype_digit($fieldKey)) {
            $raw = $values[(int)$fieldKey] ?? null;
        }
        $op = (string)($rule['operator'] ?? FormField::OP_EQUALS);
        $expected = (string)($rule['value'] ?? '');

        return $this->compare($raw, $op, $expected);
    }

    public function rulesMet(array $logic, array $values): bool
    {
        $logic = self::normalize($logic);
        if (!$logic['rules']) {
            return true;
        }
        $results = [];
        foreach ($logic['rules'] as $rule) {
            $results[] = $this->evaluateRule($rule, $values);
        }
        if ($logic['combinator'] === 'or') {
            return in_array(true, $results, true);
        }
        return !in_array(false, $results, true);
    }

    public function isVisible(FormField $field, array $values): bool
    {
        $logic = $field->getLogic();
        if (empty($logic['rules'])) {
            return true;
        }
        $met = $this->rulesMet($logic, $values);
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
     * First matching navigation action on fields of the current page.
     * @param FormField[] $fieldsOnPage
     * @return array{action:string,gotoPageKey:string}|null
     */
    public function pageNavigation(array $fieldsOnPage, array $values): ?array
    {
        foreach ($fieldsOnPage as $field) {
            $logic = $field->getLogic();
            $action = $logic['action'] ?? '';
            if (!in_array($action, [self::ACTION_GOTO_PAGE, self::ACTION_GOTO_END], true)) {
                continue;
            }
            if (empty($logic['rules']) || !$this->rulesMet($logic, $values)) {
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
     */
    public function shouldSkipPage(array $fieldsOnPage, array $values): bool
    {
        if (!$fieldsOnPage) {
            return true;
        }
        foreach ($fieldsOnPage as $field) {
            $logic = $field->getLogic();
            if (($logic['action'] ?? '') !== self::ACTION_SKIP_PAGE) {
                continue;
            }
            if (!empty($logic['rules']) && $this->rulesMet($logic, $values)) {
                return true;
            }
        }
        foreach ($fieldsOnPage as $field) {
            if ($this->isVisible($field, $values)) {
                return false;
            }
        }
        return true;
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
            default:
                return false;
        }
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
