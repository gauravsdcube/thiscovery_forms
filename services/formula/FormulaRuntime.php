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
        $zone = 'Europe/London';
        if ($form) {
            $settings = json_decode((string)$form->settings_json, true);
            $chosen = is_array($settings) ? trim((string)($settings['timezone'] ?? '')) : '';
            if ($chosen !== '' && in_array($chosen, timezone_identifiers_list(), true)) {
                $zone = $chosen;
            }
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone($zone));
        return $now->format('Y-m-d');
    }

    /**
     * @param array<int|string,mixed> $values
     * @param FormField[] $fields
     */
    public static function fill(array &$values, array $fields, string $today = ''): void
    {
        if ($today === '') {
            $today = self::today();
        }
        $context = Context::fromValues($values, $fields, $today);
        $parser = new Parser();
        foreach ($fields as $field) {
            if (!$field instanceof FormField || $field->type !== FormField::TYPE_CALCULATED) {
                continue;
            }
            $config = $field->getFormulaConfig();
            $stored = null;
            if ($config['formula'] !== '') {
                try {
                    $tree = $parser->parse($config['formula']);
                    $stored = self::present((new Evaluator($context))->evaluate($tree), $config);
                } catch (\Throwable $e) {
                    $stored = null;
                }
            }
            $values[(int)$field->id] = $stored;
            $name = trim((string)$field->variable);
            if ($name !== '') {
                $values[$name] = $stored;
            }
            $context = Context::fromValues($values, $fields, $today);
        }
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
        return $text === '' ? null : mb_substr($text, 0, 2000);
    }
}
