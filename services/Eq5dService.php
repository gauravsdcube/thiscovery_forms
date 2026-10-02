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
            $digits .= $field ? (string)$this->dimensionLevel($field, $values[$field->id] ?? null) : (string)self::MISSING_DIMENSION;
        }
        if (strlen($digits) < 5) {
            $digits = str_pad($digits, 5, (string)self::MISSING_DIMENSION);
        }

        $vasField = $this->vasField($form);
        $vas = self::MISSING_VAS;
        if ($vasField) {
            $raw = $values[$vasField->id] ?? '';
            if ($raw !== '' && $raw !== null && is_numeric($raw)) {
                // 0-100 only, rounded rather than truncated (72.6 is 73) (SCO-23).
                $number = (float)$raw;
                $vas = ($number >= 0 && $number <= 100) ? (int)round($number) : self::MISSING_VAS;
            }
        }

        return [
            'profile' => $digits,
            'vas' => $vas,
        ];
    }

    /**
     * The five dimensions in order. Once any dimension is tagged, untagged questions are never
     * borrowed: a dimension without a tagged question is null and scores as missing (V3-42).
     * A form with no tags is not guessed at either: scoring the first five radio questions
     * gave wrong profiles (SCO-11). Publishing an EQ-5D form needs all five tagged.
     *
     * @return array<int, FormField|null>
     */
    public function dimensionFields(CustomForm $form): array
    {
        $byRole = [];
        foreach ($form->getAllFields()->all() as $field) {
            if ($field->type !== FormField::TYPE_RADIO || !$field->collectsAnswer()) {
                continue;
            }
            $role = $field->getInstrumentRole();
            if (in_array($role, self::dimensionRoles(), true)) {
                $byRole[$role] = $field;
            }
        }
        $ordered = [];
        foreach (self::dimensionRoles() as $role) {
            $ordered[] = $byRole[$role] ?? null;
        }
        return $ordered;
    }

    /** @return string[] why an EQ-5D form cannot be published */
    public function authoringErrors(CustomForm $form): array
    {
        if (!$form->isEq5d()) {
            return [];
        }
        $missing = count(array_filter($this->dimensionFields($form), static fn ($f) => $f === null));
        return $missing > 0
            ? [\Yii::t('ThiscoveryFormsModule.base', 'EQ-5D needs a question tagged for each of the five dimensions; {n} are not tagged.', ['n' => $missing])]
            : [];
    }

    public function vasField(CustomForm $form): ?FormField
    {
        $fallback = null;
        foreach ($form->getAllFields()->all() as $field) {
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
        $text = trim(is_array($value) ? (string)reset($value) : (string)$value);
        if ($text === '') {
            return self::MISSING_DIMENSION;
        }
        $code = $text;
        foreach ($field->getChoicePairs() as $pair) {
            if ($pair['code'] === $text || $pair['label'] === $text) {
                $code = (string)$pair['code'];
                break;
            }
        }
        if (!ctype_digit($code)) {
            return self::MISSING_DIMENSION;
        }
        $level = (int)$code;
        return ($level >= 1 && $level <= 5) ? $level : self::MISSING_DIMENSION;
    }
}
