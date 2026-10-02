<?php

namespace humhub\modules\thiscoveryForms\services\formula;

/**
 * Starter formulas. They are copies an author can put on a form, not a hidden scorer.
 */
final class FormulaLibrary
{
    public static function bmi(): string
    {
        return 'round([weight_kg] / pow([height_m], 2), 1)';
    }

    public static function age(): string
    {
        return 'date_diff([dob], today(), "years")';
    }

    public static function phq9Total(): string
    {
        return 'min_valid(7, score_of([phq1]), score_of([phq2]), score_of([phq3]), score_of([phq4]), score_of([phq5]), score_of([phq6]), score_of([phq7]), score_of([phq8]), score_of([phq9]))';
    }

    public static function phq9Band(): string
    {
        return 'if([phq_total] <= 4, "0-4", if([phq_total] <= 9, "5-9", if([phq_total] <= 14, "10-14", if([phq_total] <= 19, "15-19", "20-27"))))';
    }

    public static function gad7Total(): string
    {
        return 'min_valid(6, score_of([gad1]), score_of([gad2]), score_of([gad3]), score_of([gad4]), score_of([gad5]), score_of([gad6]), score_of([gad7]))';
    }

    public static function gad7Band(): string
    {
        return 'if([gad_total] <= 4, "0-4", if([gad_total] <= 9, "5-9", if([gad_total] <= 14, "10-14", "15-21")))';
    }

    public static function eq5dProfile(): string
    {
        // All five dimensions or nothing: a missing one gave a 4-digit profile (V3-42).
        return 'if(and(is_answered([d1]), is_answered([d2]), is_answered([d3]), is_answered([d4]), is_answered([d5])), concat([d1], [d2], [d3], [d4], [d5]), empty)';
    }

    public static function eq5dLevelSum(): string
    {
        return 'sum(score_of([d1]), score_of([d2]), score_of([d3]), score_of([d4]), score_of([d5]))';
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        return [
            'bmi' => self::bmi(),
            'age' => self::age(),
            'phq9_total' => self::phq9Total(),
            'phq9_band' => self::phq9Band(),
            'gad7_total' => self::gad7Total(),
            'gad7_band' => self::gad7Band(),
            'eq5d_profile' => self::eq5dProfile(),
            'eq5d_level_sum' => self::eq5dLevelSum(),
        ];
    }
}
