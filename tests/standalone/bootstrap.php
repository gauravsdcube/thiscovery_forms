<?php
/**
 * Standalone bootstrap for pure-PHP tests that do not need HumHub or a database.
 * Loads the formula and randomisation classes with small stubs for FormField and Yii.
 * Run: php tests/standalone/run.php
 */

namespace {
    if (!class_exists('Yii')) {
        class Yii
        {
            public static array $warnings = [];
            public static function warning($message, $category = ''): void { self::$warnings[] = (string)$message; }
            public static function error($message, $category = ''): void { self::$warnings[] = (string)$message; }
            public static function t($category, $message, $params = []): string
            {
                foreach ($params as $k => $v) {
                    $message = str_replace('{' . $k . '}', (string)$v, $message);
                }
                return $message;
            }
        }
    }
}

namespace humhub\modules\thiscoveryForms\models {
    if (!class_exists(FormField::class)) {
        class FormField
        {
            public const TYPE_TEXT = 'text';
            public const TYPE_NUMBER = 'number';
            public const TYPE_DATE = 'date';
            public const TYPE_DROPDOWN = 'dropdown';
            public const TYPE_RADIO = 'radio';
            public const TYPE_CHECKBOX = 'checkbox';
            public const TYPE_RANKING = 'ranking';
            public const TYPE_RATING = 'rating';
            public const TYPE_CALCULATED = 'calculated';
            public const TYPE_GRID_SINGLE = 'grid_single';
            public const TYPE_GRID_MULTI = 'grid_multi';
            public $id; public $variable; public $type; public $formula; public $label; public $pairs;
            public function __construct($id, $variable, $type, $formula = '', array $pairs = [])
            {
                $this->id = $id; $this->variable = $variable; $this->type = $type; $this->formula = $formula;
                $this->label = $variable; $this->pairs = $pairs;
            }
            public function getChoicePairs(): array { return $this->pairs; }
            public function getFormulaConfig(): array { return ['formula' => $this->formula, 'result' => 'number', 'places' => 2]; }
            public static function isChoiceType($type): bool { return in_array($type, ['radio', 'dropdown', 'checkbox', 'ranking'], true); }
        }
    }
    if (!class_exists(CustomForm::class)) {
        class CustomForm {}
    }
}

namespace humhub\modules\thiscoveryForms\services {
    // Loop membership needs the database; standalone tests pass `__loops` explicitly.
    if (!class_exists(LogicEngine::class, false)) {
        // Visibility needs HumHub; standalone fields have no logic, so every answer is visible.
        class LogicEngine
        {
            public function effectiveValues(array $fields, array $values): array
            {
                return $values;
            }
        }
    }
    if (!class_exists(LoopService::class, false)) {
        class LoopService
        {
            public static function formulaLoopIds(array $fields): array
            {
                return [];
            }
        }
    }
}

namespace {
    // Any PHP warning or notice fails the test: in HumHub these become 500 errors.
    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });
    $base = dirname(__DIR__, 2) . '/services/';
    foreach (['FormulaException', 'Decimal', 'Value', 'Limits', 'Parser', 'Evaluator', 'Context', 'FormulaDeps', 'FormulaRuntime'] as $class) {
        require_once $base . 'formula/' . $class . '.php';
    }
    require_once $base . 'RandomisationEngine.php';

    function standalone_assert(bool $ok, string $label, array &$failures): void
    {
        if (!$ok) {
            $failures[] = $label;
        }
    }
}
