<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services\dashboard;

use humhub\modules\thiscoveryDashboard\interfaces\QuestionTypeInterface;
use humhub\modules\thiscoveryForms\models\FormField;

/**
 * Forms-owned registry. Dashboard never enumerates these keys.
 * New FormField types work once registered here; unknown types fall back to table.
 */
class QuestionTypeRegistry
{
    /**
     * @var array<string, callable(FormField):QuestionTypeInterface>|null
     */
    private static ?array $factories = null;

    public static function forField(FormField $field): QuestionTypeInterface
    {
        self::boot();
        $type = (string)$field->type;
        $factory = self::$factories[$type] ?? null;
        if ($factory) {
            return $factory($field);
        }
        return new UnknownQuestionType($type, FormField::defaultLabelForType($type));
    }

    /**
     * Allow other form extensions to register a type without editing dashboard.
     */
    public static function register(string $typeId, callable $factory): void
    {
        self::boot();
        self::$factories[$typeId] = $factory;
    }

    private static function boot(): void
    {
        if (self::$factories !== null) {
            return;
        }
        self::$factories = [];
        $cat = static fn(FormField $f) => new CategoricalQuestionType($f->type, FormField::defaultLabelForType($f->type));
        $num = static fn(FormField $f) => new NumericQuestionType($f->type, FormField::defaultLabelForType($f->type));
        $text = static fn(FormField $f) => new FreeTextQuestionType($f->type, FormField::defaultLabelForType($f->type));
        $multi = static fn(FormField $f) => new MultiValueQuestionType($f->type, FormField::defaultLabelForType($f->type));
        $rank = static fn(FormField $f) => new RankedQuestionType($f->type, FormField::defaultLabelForType($f->type));
        $matrix = static fn(FormField $f) => new MatrixQuestionType($f->type, FormField::defaultLabelForType($f->type));
        $time = static fn(FormField $f) => new TemporalQuestionType($f->type, FormField::defaultLabelForType($f->type));

        foreach ([FormField::TYPE_DROPDOWN, FormField::TYPE_RADIO, FormField::TYPE_DRILLDOWN, FormField::TYPE_RESPONDENT_META, FormField::TYPE_PANEL_ATTR, FormField::TYPE_IMAGE_AREA] as $id) {
            self::$factories[$id] = $cat;
        }
        foreach ([FormField::TYPE_NUMBER, FormField::TYPE_RATING] as $id) {
            self::$factories[$id] = $num;
        }
        foreach ([FormField::TYPE_TEXT, FormField::TYPE_TEXTAREA, FormField::TYPE_EMAIL, FormField::TYPE_FILE, FormField::TYPE_HTML] as $id) {
            self::$factories[$id] = $text;
        }
        self::$factories[FormField::TYPE_CHECKBOX] = $multi;
        foreach ([FormField::TYPE_RANKING, FormField::TYPE_BEST_WORST, FormField::TYPE_MAXDIFF] as $id) {
            self::$factories[$id] = $rank;
        }
        foreach ([FormField::TYPE_GRID_SINGLE, FormField::TYPE_GRID_MULTI] as $id) {
            self::$factories[$id] = $matrix;
        }
        self::$factories[FormField::TYPE_DATE] = $time;
        self::$factories[FormField::TYPE_MAP] = static fn(FormField $f) => new UnknownQuestionType($f->type, FormField::defaultLabelForType($f->type));
    }
}
