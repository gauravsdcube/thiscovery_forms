<?php

namespace humhub\modules\thiscoveryForms\services\formula;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;

/**
 * Server-side calculated values. A posted calculated answer is ignored.
 */
final class FormulaRuntime
{
    public static function today(?CustomForm $form = null): string
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone(self::timeZone($form)));
        return $now->format('Y-m-d');
    }

    /** The form's time zone (Settings), Europe/London by default. */
    public static function timeZone(?CustomForm $form = null): string
    {
        $zone = 'Europe/London';
        if ($form) {
            $settings = json_decode((string)$form->settings_json, true);
            $chosen = is_array($settings) ? trim((string)($settings['timezone'] ?? '')) : '';
            if ($chosen !== '' && in_array($chosen, timezone_identifiers_list(), true)) {
                $zone = $chosen;
            }
        }
        return $zone;
    }

    /**
     * The date today() uses for a response: frozen when the response started (V3-9).
     */
    public static function frozenToday(?CustomForm $form, ?\humhub\modules\thiscoveryForms\models\FormAnswer $answer = null): string
    {
        if ($answer && !$answer->isNewRecord) {
            $stored = (string)($answer->formula_today ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $stored)) {
                return $stored;
            }
        }
        return self::today($form);
    }

    /**
     * Named formulas (Settings -> Custom functions) parsed into trees, for fn:name (V3-13).
     *
     * @return array<string, array<string,mixed>>
     */
    public static function named(?CustomForm $form): array
    {
        if (!$form) {
            return [];
        }
        $out = [];
        $parser = new Parser();
        $list = \humhub\modules\thiscoveryForms\services\FormActionService::normalizeFunctions(
            $form->custom_functions ?? $form->getSetting('custom_functions', [])
        );
        foreach ($list as $fn) {
            $text = trim((string)$fn['value']);
            if ($text === '') {
                continue;
            }
            try {
                $out[(string)$fn['name']] = $parser->parse($text);
            } catch (\Throwable $e) {
                continue;
            }
        }
        return $out;
    }

    /**
     * @param array<int|string,mixed> $values
     * @param FormField[] $fields
     */
    public static function fill(array &$values, array $fields, string $today = ''): void
    {
        if (isset($values['__today']) && is_string($values['__today']) && $values['__today'] !== '') {
            $today = $values['__today'];
        }
        if ($today === '') {
            $today = self::today();
        }
        if (!isset($values['__loops'])) {
            $values['__loops'] = \humhub\modules\thiscoveryForms\services\LoopService::formulaLoopIds($fields);
        }
        $calculated = [];
        foreach ($fields as $field) {
            if ($field instanceof FormField && $field->type === FormField::TYPE_CALCULATED) {
                $calculated[] = $field;
            }
        }
        if (!$calculated) {
            return;
        }

        // The server owns calculated values. A value posted by the browser is discarded
        // before anything is evaluated, so no other formula can read it.
        foreach ($calculated as $field) {
            self::store($values, $field, null);
        }

        $parser = new Parser();
        $trees = [];
        $deps = [];
        foreach ($calculated as $field) {
            $key = self::key($field);
            $trees[$key] = null;
            $deps[$key] = [];
            $config = $field->getFormulaConfig();
            if ($config['formula'] === '') {
                continue;
            }
            try {
                $trees[$key] = $parser->parse($config['formula']);
                $deps[$key] = FormulaDeps::names($trees[$key], is_array($values['__named'] ?? null) ? $values['__named'] : []);
            } catch (\Throwable $e) {
                $trees[$key] = null;
            }
        }

        $byKey = [];
        foreach ($calculated as $field) {
            $byKey[self::key($field)] = $field;
        }
        [$order, $cyclic] = self::order($byKey, $deps);
        if ($cyclic) {
            \Yii::warning('Thiscovery Forms calculated fields form a cycle: ' . implode(', ', $cyclic), 'thiscovery-forms');
        }

        // Calculations read only answers the respondent could see: a hidden question's stale
        // answer does not feed a score (V3-38). Worked out once per fill, not per calculation.
        $visible = (new \humhub\modules\thiscoveryForms\services\LogicEngine())->effectiveValues($fields, $values);
        foreach ($order as $key) {
            $field = $byKey[$key];
            $tree = $trees[$key];
            $stored = null;
            if ($tree !== null) {
                try {
                    $context = Context::fromValues($visible, $fields, $today);
                    $context->steps = 0;
                    $stored = self::present((new Evaluator($context))->evaluate($tree), $field->getFormulaConfig());
                    if ($context->steps > Limits::STEPS) {
                        // Out of budget is an authoring problem, not a blank answer to pass silently.
                        \Yii::warning('Thiscovery Forms calculated field ' . $key . ' ran out of steps (' . Limits::STEPS . ') and was left empty.', 'thiscovery-forms');
                        $stored = null;
                    }
                } catch (\Throwable $e) {
                    \Yii::warning('Thiscovery Forms calculated field ' . $key . ' failed: ' . $e->getMessage(), 'thiscovery-forms');
                    $stored = null;
                }
            }
            self::store($values, $field, $stored);
            self::store($visible, $field, $stored);
        }
        // Fields in a cycle stay empty.
    }

    private static function key(FormField $field): string
    {
        $name = trim((string)$field->variable);
        return $name !== '' ? $name : 'id' . (int)$field->id;
    }

    /** @param array<int|string,mixed> $values */
    private static function store(array &$values, FormField $field, ?string $value): void
    {
        $values[(int)$field->id] = $value;
        $values['id' . (int)$field->id] = $value;
        $name = trim((string)$field->variable);
        if ($name !== '') {
            $values[$name] = $value;
        }
    }

    /**
     * Ids of calculated questions caught in a dependency cycle (named formulas followed). The
     * server leaves them empty, and the browser is told to as well (V3-38).
     *
     * @return list<int>
     */
    public static function cyclicIds(CustomForm $form): array
    {
        $parser = new Parser();
        $named = self::named($form);
        $byKey = [];
        $deps = [];
        foreach ($form->fields as $field) {
            if (!$field instanceof FormField || $field->type !== FormField::TYPE_CALCULATED) {
                continue;
            }
            $key = self::key($field);
            $byKey[$key] = $field;
            $deps[$key] = [];
            $formula = trim((string)$field->getFormulaConfig()['formula']);
            if ($formula === '') {
                continue;
            }
            try {
                $deps[$key] = FormulaDeps::names($parser->parse($formula), $named);
            } catch (\Throwable $e) {
                continue;
            }
        }
        [, $cyclic] = self::order($byKey, $deps);
        return array_values(array_map(static fn(string $key) => (int)$byKey[$key]->id, $cyclic));
    }

    /**
     * Topological order of calculated fields by the calculated fields they read.
     *
     * @param array<string,FormField> $byKey
     * @param array<string,list<string>> $deps
     * @return array{0:list<string>,1:list<string>} order, fields left out because they are in a cycle
     */
    public static function order(array $byKey, array $deps): array
    {
        $lower = [];
        foreach (array_keys($byKey) as $key) {
            $lower[strtolower($key)] = $key;
            $lower['id' . (int)$byKey[$key]->id] = $key;
        }
        $edges = [];
        foreach ($deps as $key => $names) {
            $edges[$key] = [];
            foreach ($names as $name) {
                $target = $lower[strtolower((string)$name)] ?? null;
                if ($target !== null) {
                    $edges[$key][] = $target;
                }
            }
        }
        $order = [];
        $state = [];
        $cyclic = [];
        $visit = static function (string $key) use (&$visit, &$state, &$order, &$cyclic, $edges): bool {
            if (($state[$key] ?? 0) === 2) {
                return true;
            }
            if (($state[$key] ?? 0) === 1) {
                return false;
            }
            $state[$key] = 1;
            $ok = true;
            foreach ($edges[$key] ?? [] as $dep) {
                if (!$visit($dep)) {
                    $ok = false;
                }
            }
            $state[$key] = 2;
            if ($ok) {
                $order[] = $key;
            } else {
                $cyclic[] = $key;
            }
            return $ok;
        };
        foreach (array_keys($byKey) as $key) {
            $visit($key);
        }
        return [$order, $cyclic];
    }

    /** @param array{result:string,places:int} $config */
    private static function present(Value $value, array $config): ?string
    {
        if ($value->isEmpty()) {
            return null;
        }
        if ($config['result'] === 'number' && $value->type === 'number') {
            $rounded = Decimal::round((string)$value->data, (int)$config['places']);
            return $rounded;
        }
        if ($config['result'] === 'boolean') {
            return $value->truth() ? '1' : '0';
        }
        $text = (string)$value->data;
        return $text === '' ? null : mb_substr($text, 0, Limits::RESULT_TEXT);
    }
}
