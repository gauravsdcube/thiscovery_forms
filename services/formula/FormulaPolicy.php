<?php

namespace humhub\modules\thiscoveryForms\services\formula;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\FormActionService;
use Yii;

/**
 * Publishing refuses panel values on a fully anonymous form, and refuses identity meta on every form.
 */
final class FormulaPolicy
{
    /** @var list<string> */
    public const META_ALLOWED = ['language', 'device_type'];

    /** @return string[] */
    /**
     * Question names a formula reads that are not on the form (variable, id or idN).
     *
     * @param array<string,mixed> $tree
     * @return list<string>
     */
    public static function unknownQuestions(CustomForm $form, array $tree): array
    {
        $known = [];
        foreach ($form->fields as $field) {
            if (!$field instanceof FormField) {
                continue;
            }
            $known[strtolower(trim((string)$field->variable))] = true;
            $known[(string)(int)$field->id] = true;
            $known['id' . (int)$field->id] = true;
        }
        $unknown = [];
        foreach (FormulaDeps::names($tree) as $name) {
            if ($name === 'arm' || str_contains($name, ':')) {
                continue;
            }
            if (!isset($known[strtolower($name)])) {
                $unknown[] = $name;
            }
        }
        return $unknown;
    }

    public static function authoringErrors(CustomForm $form): array
    {
        $errors = [];
        unset($form->fields);
        $anonymous = $form->hidesIdentityFromManagers();
        $parser = new Parser();
        foreach ($form->fields as $field) {
            if (!$field instanceof FormField) {
                continue;
            }
            $texts = [];
            $logic = $field->getLogic();
            if (trim((string)($logic['text'] ?? '')) !== '') {
                $texts[] = (string)$logic['text'];
            }
            if ($field->type === FormField::TYPE_CALCULATED) {
                $formula = trim($field->getFormulaConfig()['formula']);
                if ($formula !== '') {
                    $texts[] = $formula;
                }
            }
            foreach ($texts as $text) {
                try {
                    $tree = $parser->parse($text);
                    self::walk($tree, $anonymous, (string)$field->label, $errors);
                    foreach (self::unknownQuestions($form, $tree) as $name) {
                        // A reference to no question is a typo or a rename: refused at publish (V3-54).
                        $errors[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” uses [{name}], which is not a question on this form.', [
                            'label' => (string)$field->label,
                            'name' => $name,
                        ]);
                    }
                } catch (FormulaException $e) {
                    continue;
                }
            }
        }
        $calcByKey = [];
        $calcDeps = [];
        // Named formulas are followed, so a cycle through fn:name is caught too (V3-38).
        $named = FormulaRuntime::named($form);
        foreach ($form->fields as $field) {
            if (!$field instanceof FormField || $field->type !== FormField::TYPE_CALCULATED) {
                continue;
            }
            $key = trim((string)$field->variable) !== '' ? trim((string)$field->variable) : 'id' . (int)$field->id;
            $calcByKey[$key] = $field;
            $calcDeps[$key] = [];
            $formula = trim($field->getFormulaConfig()['formula']);
            if ($formula === '') {
                continue;
            }
            try {
                $calcDeps[$key] = FormulaDeps::names($parser->parse($formula), $named);
            } catch (FormulaException $e) {
                continue;
            }
        }
        [, $cyclic] = FormulaRuntime::order($calcByKey, $calcDeps);
        if ($cyclic) {
            $labels = [];
            foreach ($cyclic as $key) {
                $labels[] = (string)$calcByKey[$key]->label;
            }
            $errors[] = Yii::t('ThiscoveryFormsModule.base', 'Calculated questions depend on each other in a loop: {labels}.', [
                'labels' => implode(', ', $labels),
            ]);
        }
        foreach (FormActionService::normalizeFunctions($form->custom_functions ?? $form->getSetting('custom_functions', [])) as $fn) {
            $value = trim((string)$fn['value']);
            if ($value === '' || !str_contains($value, '[')) {
                continue;
            }
            try {
                self::walk($parser->parse($value), $anonymous, (string)$fn['name'], $errors);
            } catch (FormulaException $e) {
                continue;
            }
        }
        return array_values(array_unique($errors));
    }

    /**
     * @param array<string,mixed> $tree
     * @param string[] $errors
     */
    private static function walk(array $tree, bool $anonymous, string $label, array &$errors): void
    {
        if (($tree['op'] ?? '') === 'ref') {
            $ref = (string)($tree['ref'] ?? '');
            $name = (string)($tree['name'] ?? '');
            if ($ref === 'panel' && $anonymous) {
                $errors[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” cannot use a panel value on a fully anonymous form.', [
                    'label' => $label,
                ]);
            }
            if ($ref === 'meta' && !in_array($name, self::META_ALLOWED, true)) {
                $errors[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” cannot use meta:{name}.', [
                    'label' => $label,
                    'name' => $name,
                ]);
            }
        }
        foreach ($tree['args'] ?? [] as $arg) {
            if (is_array($arg)) {
                self::walk($arg, $anonymous, $label, $errors);
            }
        }
    }
}
