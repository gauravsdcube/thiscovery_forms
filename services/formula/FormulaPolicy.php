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
                    self::walk($parser->parse($text), $anonymous, (string)$field->label, $errors);
                } catch (FormulaException $e) {
                    continue;
                }
            }
        }
        $calcByKey = [];
        $calcDeps = [];
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
                $calcDeps[$key] = FormulaDeps::names($parser->parse($formula));
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
