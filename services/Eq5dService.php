<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;

/**
 * Licence-safe scoring helpers for the EQ-5D form type.
 * Does not ship official wording; maps the five dimension fields and VAS by role or order.
 */
class Eq5dService
{
    public const ROLE_D1 = 'eq5d_d1';
    public const ROLE_D2 = 'eq5d_d2';
    public const ROLE_D3 = 'eq5d_d3';
    public const ROLE_D4 = 'eq5d_d4';
    public const ROLE_D5 = 'eq5d_d5';
    public const ROLE_VAS = 'eq5d_vas';

    public const MISSING_VAS = 999;
    public const MISSING_DIMENSION = 9;

    /**
     * @return string[]
     */
    public static function dimensionRoles(): array
    {
        return [self::ROLE_D1, self::ROLE_D2, self::ROLE_D3, self::ROLE_D4, self::ROLE_D5];
    }

    /**
     * @param array<int|string,mixed> $values
     * @return array{profile:string,vas:int}
     */
    public function score(CustomForm $form, array $values): array
    {
        $digits = '';
        foreach ($this->dimensionFields($form) as $field) {
            $digits .= (string)$this->dimensionLevel($field, $values[$field->id] ?? null);
        }
        if (strlen($digits) < 5) {
            $digits = str_pad($digits, 5, (string)self::MISSING_DIMENSION);
        }

        $vasField = $this->vasField($form);
        $vas = self::MISSING_VAS;
        if ($vasField) {
            $raw = $values[$vasField->id] ?? '';
            if ($raw !== '' && $raw !== null && is_numeric($raw)) {
                $vas = (int)$raw;
            }
        }

        return [
            'profile' => $digits,
            'vas' => $vas,
        ];
    }

    /**
     * @return FormField[]
     */
    public function dimensionFields(CustomForm $form): array
    {
        $byRole = [];
        $radios = [];
        foreach ($form->fields as $field) {
            if ($field->type !== FormField::TYPE_RADIO || !$field->collectsAnswer()) {
                continue;
            }
            $role = $field->getInstrumentRole();
            if (in_array($role, self::dimensionRoles(), true)) {
                $byRole[$role] = $field;
            } else {
                $radios[] = $field;
            }
        }
        if (count($byRole) === 5) {
            $ordered = [];
            foreach (self::dimensionRoles() as $role) {
                $ordered[] = $byRole[$role];
            }
            return $ordered;
        }
        return array_slice($radios, 0, 5);
    }

    public function vasField(CustomForm $form): ?FormField
    {
        $fallback = null;
        foreach ($form->fields as $field) {
            if ($field->type !== FormField::TYPE_RATING || !$field->collectsAnswer()) {
                continue;
            }
            if ($field->getInstrumentRole() === self::ROLE_VAS) {
                return $field;
            }
            $display = (string)($field->getRatingScale()['display'] ?? '');
            if ($display === FormField::RATING_DISPLAY_THERMOMETER && $fallback === null) {
                $fallback = $field;
            }
        }
        return $fallback;
    }

    /**
     * @param mixed $value
     */
    public function dimensionLevel(FormField $field, $value): int
    {
        if ($value === null || $value === '') {
            return self::MISSING_DIMENSION;
        }
        $options = $field->getOptions();
        $text = is_array($value) ? (string)reset($value) : (string)$value;
        foreach ($options as $i => $option) {
            if ((string)$option === $text || (string)$i === $text) {
                return min(5, $i + 1);
            }
        }
        if (ctype_digit($text)) {
            $n = (int)$text;
            return ($n >= 1 && $n <= 5) ? $n : self::MISSING_DIMENSION;
        }
        return self::MISSING_DIMENSION;
    }
}
