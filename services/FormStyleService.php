<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\FormField;
use Yii;

/**
 * Per-form visual tokens compiled to CSS. Empty values inherit the site theme.
 */
class FormStyleService
{
    /**
     * Accordion groups and fields for the CSS studio tab.
     *
     * @return array<int, array{id: string, label: string, fields: array<int, array{name: string, label: string, css: string, selector: string, type?: string, options?: array}>}>
     */
    public function groups(): array
    {
        $groups = [
            [
                'id' => 'page',
                'label' => Yii::t('ThiscoveryFormsModule.base', 'Form page'),
                'fields' => $this->boxFields('#cf-fill.cf-fill-page', [
                    'background', 'color', 'maxWidth', 'padding',
                ]),
            ],
            [
                'id' => 'card',
                'label' => Yii::t('ThiscoveryFormsModule.base', 'Form card'),
                'fields' => $this->boxFields('#cf-fill .cf-fill-body', [
                    'background', 'color', 'borderColor', 'borderRadius', 'padding',
                ]),
            ],
            [
                'id' => 'title',
                'label' => Yii::t('ThiscoveryFormsModule.base', 'Title'),
                'fields' => $this->textFields('#cf-fill .cf-fill-hero__title', [
                    'color', 'fontSize', 'fontWeight', 'marginBottom',
                ]),
            ],
            [
                'id' => 'description',
                'label' => Yii::t('ThiscoveryFormsModule.base', 'Description'),
                'fields' => $this->textFields('#cf-fill .cf-fill-hero__desc', [
                    'color', 'fontSize', 'marginBottom',
                ]),
            ],
            [
                'id' => 'question',
                'label' => Yii::t('ThiscoveryFormsModule.base', 'Question block'),
                'fields' => array_merge(
                    $this->boxFields('#cf-fill .cf-question', ['background', 'padding', 'marginBottom', 'borderColor']),
                    [
                        $this->field('labelColor', Yii::t('ThiscoveryFormsModule.base', 'Label colour'), 'color', '#cf-fill .cf-question__label'),
                        $this->field('labelSize', Yii::t('ThiscoveryFormsModule.base', 'Label size'), 'font-size', '#cf-fill .cf-question__label'),
                        $this->field('helpColor', Yii::t('ThiscoveryFormsModule.base', 'Help text colour'), 'color', '#cf-fill .cf-question__help'),
                    ]
                ),
            ],
            [
                'id' => 'inputs',
                'label' => Yii::t('ThiscoveryFormsModule.base', 'Text fields'),
                'fields' => $this->controlFields('#cf-fill .cf-input, #cf-fill .cf-fill-body .form-control'),
            ],
            [
                'id' => 'buttons',
                'label' => Yii::t('ThiscoveryFormsModule.base', 'Buttons'),
                'fields' => [
                    $this->field('background', Yii::t('ThiscoveryFormsModule.base', 'Primary background'), 'background-color', '#cf-fill .btn-primary, #cf-fill .cf-fill-submit .btn-primary'),
                    $this->field('color', Yii::t('ThiscoveryFormsModule.base', 'Primary text'), 'color', '#cf-fill .btn-primary, #cf-fill .cf-fill-submit .btn-primary'),
                    $this->field('borderRadius', Yii::t('ThiscoveryFormsModule.base', 'Corner radius'), 'border-radius', '#cf-fill .cf-fill-nav .btn, #cf-fill .cf-fill-submit .btn'),
                    $this->field('fontSize', Yii::t('ThiscoveryFormsModule.base', 'Font size'), 'font-size', '#cf-fill .cf-fill-nav .btn, #cf-fill .cf-fill-submit .btn'),
                ],
            ],
            [
                'id' => 'progress',
                'label' => Yii::t('ThiscoveryFormsModule.base', 'Progress bar'),
                'fields' => [
                    $this->field('background', Yii::t('ThiscoveryFormsModule.base', 'Track'), 'background-color', '#cf-fill .cf-fill-progress'),
                    $this->field('color', Yii::t('ThiscoveryFormsModule.base', 'Bar'), 'background-color', '#cf-fill .cf-fill-progress__bar'),
                    $this->field('borderRadius', Yii::t('ThiscoveryFormsModule.base', 'Corner radius'), 'border-radius', '#cf-fill .cf-fill-progress, #cf-fill .cf-fill-progress__bar'),
                ],
            ],
        ];

        foreach (FormField::getTypeLabels() as $type => $label) {
            if ($type === FormField::TYPE_PAGE_BREAK || $type === FormField::TYPE_GROUP_END) {
                continue;
            }
            $sel = '#cf-fill [data-cf-field-type="' . $type . '"]';
            $fields = array_merge(
                $this->boxFields($sel, ['background', 'padding', 'marginBottom']),
                [
                    $this->field('labelColor', Yii::t('ThiscoveryFormsModule.base', 'Label colour'), 'color', $sel . ' .cf-question__label'),
                    $this->field('color', Yii::t('ThiscoveryFormsModule.base', 'Text colour'), 'color', $sel),
                ]
            );
            $controlSel = $this->controlSelectorForType($type, $sel);
            if ($controlSel !== '') {
                $fields = array_merge($fields, $this->controlFields($controlSel));
            }
            $groups[] = [
                'id' => 'type_' . $type,
                'label' => $label,
                'fields' => $fields,
            ];
        }

        return $groups;
    }

    public function compile(array $style): string
    {
        $rules = [];
        foreach ($this->groups() as $group) {
            $values = $style[$group['id']] ?? [];
            if (!is_array($values)) {
                continue;
            }
            foreach ($group['fields'] as $field) {
                $raw = trim((string)($values[$field['name']] ?? ''));
                if ($raw === '') {
                    continue;
                }
                $safe = $this->sanitizeCssValue($field['css'], $raw);
                if ($safe === null) {
                    continue;
                }
                $selector = $field['selector'];
                if (!isset($rules[$selector])) {
                    $rules[$selector] = [];
                }
                $rules[$selector][$field['css']] = $safe;
            }
        }

        $out = [];
        foreach ($rules as $selector => $decls) {
            $parts = [];
            foreach ($decls as $prop => $val) {
                $parts[] = $prop . ': ' . $val;
            }
            $out[] = $selector . ' { ' . implode('; ', $parts) . '; }';
        }

        return implode("\n", $out);
    }

    public function normalize(array $style): array
    {
        $clean = [];
        foreach ($this->groups() as $group) {
            $values = $style[$group['id']] ?? [];
            if (!is_array($values)) {
                continue;
            }
            $row = [];
            foreach ($group['fields'] as $field) {
                $raw = trim((string)($values[$field['name']] ?? ''));
                if ($raw === '') {
                    continue;
                }
                if ($this->sanitizeCssValue($field['css'], $raw) === null) {
                    continue;
                }
                $row[$field['name']] = $raw;
            }
            if ($row) {
                $clean[$group['id']] = $row;
            }
        }
        return $clean;
    }

    protected function controlSelectorForType(string $type, string $block): string
    {
        return match ($type) {
            FormField::TYPE_TEXT,
            FormField::TYPE_TEXTAREA,
            FormField::TYPE_NUMBER,
            FormField::TYPE_EMAIL,
            FormField::TYPE_DATE,
            FormField::TYPE_DROPDOWN,
            FormField::TYPE_FILE => $block . ' .cf-input, ' . $block . ' .form-control',
            FormField::TYPE_RADIO,
            FormField::TYPE_CHECKBOX => $block . ' .cf-choice, ' . $block . ' label',
            FormField::TYPE_RATING => $block . ' .cf-rating, ' . $block . ' .cf-star, ' . $block . ' .cf-vas',
            FormField::TYPE_RANKING => $block . ' .cf-ranking-item',
            FormField::TYPE_GRID_SINGLE,
            FormField::TYPE_GRID_MULTI => $block . ' table, ' . $block . ' .cf-grid',
            FormField::TYPE_BEST_WORST,
            FormField::TYPE_MAXDIFF => $block . ' .cf-maxdiff, ' . $block . ' .cf-best-worst',
            FormField::TYPE_DRILLDOWN => $block . ' select, ' . $block . ' .form-control',
            FormField::TYPE_IMAGE_AREA => $block . ' .cf-image-area',
            FormField::TYPE_MAP => $block . ' .cf-map-field, ' . $block . ' .tm-shell',
            FormField::TYPE_RICH_TEXT => $block . ' .cf-rich-block',
            FormField::TYPE_HTML => $block . ' .cf-html-block',
            default => '',
        };
    }

    protected function boxFields(string $selector, array $names): array
    {
        $map = [
            'background' => ['label' => Yii::t('ThiscoveryFormsModule.base', 'Background'), 'css' => 'background-color'],
            'color' => ['label' => Yii::t('ThiscoveryFormsModule.base', 'Text colour'), 'css' => 'color'],
            'borderColor' => ['label' => Yii::t('ThiscoveryFormsModule.base', 'Border colour'), 'css' => 'border-color'],
            'borderRadius' => ['label' => Yii::t('ThiscoveryFormsModule.base', 'Corner radius'), 'css' => 'border-radius'],
            'padding' => ['label' => Yii::t('ThiscoveryFormsModule.base', 'Padding'), 'css' => 'padding'],
            'marginBottom' => ['label' => Yii::t('ThiscoveryFormsModule.base', 'Space below'), 'css' => 'margin-bottom'],
            'maxWidth' => ['label' => Yii::t('ThiscoveryFormsModule.base', 'Max width'), 'css' => 'max-width'],
        ];
        $fields = [];
        foreach ($names as $name) {
            if (!isset($map[$name])) {
                continue;
            }
            $fields[] = $this->field($name, $map[$name]['label'], $map[$name]['css'], $selector);
        }
        return $fields;
    }

    protected function textFields(string $selector, array $names): array
    {
        $map = [
            'color' => ['label' => Yii::t('ThiscoveryFormsModule.base', 'Text colour'), 'css' => 'color'],
            'fontSize' => ['label' => Yii::t('ThiscoveryFormsModule.base', 'Font size'), 'css' => 'font-size'],
            'fontWeight' => ['label' => Yii::t('ThiscoveryFormsModule.base', 'Font weight'), 'css' => 'font-weight', 'type' => 'weight'],
            'marginBottom' => ['label' => Yii::t('ThiscoveryFormsModule.base', 'Space below'), 'css' => 'margin-bottom'],
        ];
        $fields = [];
        foreach ($names as $name) {
            if (!isset($map[$name])) {
                continue;
            }
            $fields[] = $this->field(
                $name,
                $map[$name]['label'],
                $map[$name]['css'],
                $selector,
                $map[$name]['type'] ?? 'text'
            );
        }
        return $fields;
    }

    protected function controlFields(string $selector): array
    {
        return [
            $this->field('controlBackground', Yii::t('ThiscoveryFormsModule.base', 'Field background'), 'background-color', $selector),
            $this->field('controlColor', Yii::t('ThiscoveryFormsModule.base', 'Field text'), 'color', $selector),
            $this->field('controlBorder', Yii::t('ThiscoveryFormsModule.base', 'Field border'), 'border-color', $selector),
            $this->field('controlRadius', Yii::t('ThiscoveryFormsModule.base', 'Field corners'), 'border-radius', $selector),
            $this->field('controlSize', Yii::t('ThiscoveryFormsModule.base', 'Field font size'), 'font-size', $selector),
        ];
    }

    protected function field(string $name, string $label, string $css, string $selector, string $type = 'text'): array
    {
        if ($type === 'text' && in_array($css, ['color', 'background-color', 'border-color'], true)) {
            $type = 'color';
        }
        $field = [
            'name' => $name,
            'label' => $label,
            'css' => $css,
            'selector' => $selector,
            'type' => $type,
        ];
        if ($type === 'weight') {
            $field['options'] = [
                '' => Yii::t('ThiscoveryFormsModule.base', 'Theme default'),
                'normal' => Yii::t('ThiscoveryFormsModule.base', 'Normal'),
                '600' => Yii::t('ThiscoveryFormsModule.base', 'Semibold'),
                '700' => Yii::t('ThiscoveryFormsModule.base', 'Bold'),
            ];
        }
        return $field;
    }

    /**
     * Hex for native colour inputs. Empty or non-hex values fall back to white.
     */
    public static function swatchHex(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^#([0-9a-f]{3})$/i', $value, $m)) {
            $h = $m[1];
            return '#' . $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        }
        if (preg_match('/^#([0-9a-f]{6})(?:[0-9a-f]{2})?$/i', $value, $m)) {
            return '#' . $m[1];
        }
        return '#ffffff';
    }

    protected function sanitizeCssValue(string $property, string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || str_contains($value, ';') || str_contains($value, '{') || str_contains($value, '}')) {
            return null;
        }

        if (in_array($property, ['color', 'background-color', 'border-color'], true)) {
            if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value)) {
                return $value;
            }
            if (preg_match('/^(?:rgb|rgba|hsl|hsla)\(\s*[\d.%,\s\/]+\s*\)$/i', $value)) {
                return $value;
            }
            if (preg_match('/^var\(--[a-z0-9\-]+\)$/i', $value)) {
                return $value;
            }
            if (preg_match('/^(transparent|inherit|currentColor|none)$/i', $value)) {
                return strtolower($value);
            }
            return null;
        }

        if ($property === 'font-weight') {
            if (preg_match('/^(normal|bold|[1-9]00)$/i', $value)) {
                return strtolower($value);
            }
            return null;
        }

        if (in_array($property, ['font-size', 'border-radius', 'margin-bottom', 'max-width', 'padding', 'border-width'], true)) {
            if (preg_match('/^(?:auto|none|inherit|0)$/i', $value)) {
                return strtolower($value);
            }
            $parts = preg_split('/\s+/', $value) ?: [];
            if ($property !== 'padding' && count($parts) !== 1) {
                return null;
            }
            if ($property === 'padding' && (count($parts) < 1 || count($parts) > 4)) {
                return null;
            }
            foreach ($parts as $part) {
                if (!preg_match('/^-?\d+(?:\.\d+)?(?:px|rem|em|%)$/', $part)) {
                    return null;
                }
            }
            return $value;
        }

        return null;
    }
}
