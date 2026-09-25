<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\Module;
use Yii;

/**
 * Map brief / questionnaire text to import-shaped field payloads.
 */
class SurveyDesignMapper
{
    private LlmClient $llm;

    /**
     * Types the from-brief mapper prefers to emit (subset of FormField types).
     *
     * @return list<string>
     */
    public static function fromBriefPreferredTypes(): array
    {
        return [
            FormField::TYPE_TEXT,
            FormField::TYPE_TEXTAREA,
            FormField::TYPE_NUMBER,
            FormField::TYPE_EMAIL,
            FormField::TYPE_DATE,
            FormField::TYPE_RADIO,
            FormField::TYPE_CHECKBOX,
            FormField::TYPE_DROPDOWN,
            FormField::TYPE_RATING,
            FormField::TYPE_RANKING,
            FormField::TYPE_PAGE_BREAK,
            FormField::TYPE_RICH_TEXT,
            FormField::TYPE_GRID_SINGLE,
            FormField::TYPE_GRID_MULTI,
            FormField::TYPE_FILE,
        ];
    }

    /**
     * Human-readable type guide for LLM chat / mapping prompts.
     */
    public static function fromBriefTypeGuide(): string
    {
        $labels = FormField::getTypeLabels();
        $lines = [];
        foreach (self::fromBriefPreferredTypes() as $type) {
            $lines[] = $type . ' (' . ($labels[$type] ?? $type) . ')';
        }
        return implode(', ', $lines);
    }

    public function __construct(?LlmClient $llm = null)
    {
        $this->llm = $llm ?? new LlmClient();
    }

    /**
     * @return array{title: string, fields: array<int, array<string, mixed>>, mode: string, warning: string}
     */
    public function map(string $brief, bool $preferLlm = true, ?string $sessionId = null, string $extraInstructions = ''): array
    {
        $brief = trim($brief);
        $title = $this->guessTitle($brief);
        $warning = '';
        $mode = 'rules';
        $fields = [];

        if ($preferLlm && Module::isFromBriefLlmEnabled()) {
            try {
                $llmResult = $this->mapWithLlm($brief, $sessionId, $extraInstructions);
                if (!empty($llmResult['fields'])) {
                    return [
                        'title' => $llmResult['title'] ?: $title,
                        'fields' => $llmResult['fields'],
                        'mode' => 'llm',
                        'warning' => '',
                    ];
                }
                $warning = Yii::t('ThiscoveryFormsModule.base', 'AI mapping returned no questions. Used rules-based mapping instead.');
            } catch (\Throwable $e) {
                $warning = Yii::t(
                    'ThiscoveryFormsModule.base',
                    'AI mapping failed ({error}). Used rules-based mapping instead.',
                    ['error' => $e->getMessage()]
                );
            }
        }

        $fields = $this->mapWithRules($brief);
        return [
            'title' => $title,
            'fields' => $fields,
            'mode' => $mode,
            'warning' => $warning,
        ];
    }

    /**
     * @return array{title: string, fields: array<int, array<string, mixed>>}
     */
    protected function mapWithLlm(string $brief, ?string $sessionId, string $extraInstructions = ''): array
    {
        $module = Yii::$app->getModule('thiscovery-forms');
        $maxChars = $module instanceof Module
            ? (int)$module->settings->get(Module::SETTING_LLM_MAX_BRIEF_CHARS, 60000)
            : 60000;
        if (mb_strlen($brief) > $maxChars) {
            $brief = mb_substr($brief, 0, $maxChars);
        }

        $types = implode(', ', self::fromBriefPreferredTypes());

        $system = 'You convert research briefs or paper questionnaires into Thiscovery Forms question JSON. '
            . 'Return ONLY a JSON object: {"title":"...","fields":[...]}. '
            . 'Each field needs "type" (one of: ' . $types . '), "label", optional "help_text", "required" ("1" or ""), '
            . 'optional "options" (string array for choice types), optional page_key/page_title for page_break. '
            . 'Use page_break between sections. Prefer radio for single choice, checkbox for multi. '
            . 'Keep labels close to the source wording. Do not invent personal data. '
            . 'Be compact: omit empty optional keys; keep option lists short; do not add commentary. '
            . 'If creator instructions are provided, apply them even when they conflict with earlier brief wording.';

        // Large briefs blow the output budget; keep mapping input bounded.
        $mapChars = min($maxChars, 25000);
        if (mb_strlen($brief) > $mapChars) {
            $brief = mb_substr($brief, 0, $mapChars);
        }

        $userPayload = $brief;
        $extraInstructions = trim($extraInstructions);
        if ($extraInstructions !== '') {
            $userPayload .= "\n\n---\n" . $extraInstructions;
        }

        $raw = $this->llm->chatJson($system, $userPayload, 'map', $sessionId, null);
        $title = trim((string)($raw['title'] ?? ''));
        $fields = [];
        foreach (($raw['fields'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = $this->normalizeFieldRow($row);
            if ($normalized !== null) {
                $fields[] = $normalized;
            }
        }
        return ['title' => $title, 'fields' => $fields];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function mapWithRules(string $brief): array
    {
        $lines = preg_split("/\n/u", $brief) ?: [];
        $fields = [];
        $pendingOptions = [];
        $current = null;
        $pageIndex = 0;
        $qIndex = 0;

        $flushCurrent = function () use (&$current, &$pendingOptions, &$fields, &$qIndex) {
            if ($current === null) {
                return;
            }
            if ($pendingOptions) {
                $current['options'] = $pendingOptions;
                if (($current['type'] ?? '') === FormField::TYPE_TEXT) {
                    $current['type'] = FormField::TYPE_RADIO;
                }
            }
            if (empty($current['key'])) {
                $qIndex++;
                $current['key'] = 'q' . $qIndex;
            }
            $fields[] = $current;
            $current = null;
            $pendingOptions = [];
        };

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^(section|part|page)\s*[\dIVX]+[:.\-\s]+(.+)$/iu', $line, $m)
                || preg_match('/^#{1,3}\s+(.+)$/u', $line, $m)
                || (preg_match('/^[A-Z][A-Z0-9 \-]{8,}$/u', $line) && !preg_match('/^\d/', $line))) {
                $flushCurrent();
                $pageIndex++;
                $title = isset($m[2]) ? trim($m[2]) : (isset($m[1]) ? trim($m[1]) : $line);
                $fields[] = [
                    'type' => FormField::TYPE_PAGE_BREAK,
                    'key' => 'pb' . $pageIndex,
                    'label' => 'Page break',
                    'page_key' => 'page-' . $pageIndex,
                    'page_title' => $title,
                    'required' => '',
                ];
                continue;
            }

            if (preg_match('/^(?:[A-Za-z]|\d+)[).:\-\s]\s*(.+)$/u', $line, $m)
                && (
                    preg_match('/^(?:[A-Ea-e]|[1-5]|yes|no|other)\b/iu', $line)
                    || (isset($current) && preg_match('/^[A-Ea-e\d][).]/', $line))
                )
                && isset($current)
                && !preg_match('/^\d+[.)]\s+.+\?/', $line)
            ) {
                $opt = trim($m[1]);
                if ($opt !== '') {
                    $pendingOptions[] = $opt;
                }
                continue;
            }

            if (preg_match('/^(?:\d+[.)]|Q\d+[.)]?)\s*(.+)$/iu', $line, $m)
                || preg_match('/^.+\?\s*$/u', $line)) {
                $flushCurrent();
                $label = isset($m[1]) ? trim($m[1]) : $line;
                $type = FormField::TYPE_TEXT;
                if (preg_match('/\b(select all|tick all|all that apply|multi[- ]?select)\b/iu', $label)) {
                    $type = FormField::TYPE_CHECKBOX;
                } elseif (preg_match('/\b(rate|rating|scale|likert|satisfied|agree)\b/iu', $label)) {
                    $type = FormField::TYPE_RATING;
                } elseif (preg_match('/\b(describe|comment|anything else|open[- ]?ended)\b/iu', $label)) {
                    $type = FormField::TYPE_TEXTAREA;
                } elseif (preg_match('/\b(yes\/no|yes or no)\b/iu', $label)) {
                    $type = FormField::TYPE_RADIO;
                    $pendingOptions = ['Yes', 'No'];
                }
                $qIndex++;
                $current = [
                    'type' => $type,
                    'key' => 'q' . $qIndex,
                    'label' => $label,
                    'help_text' => '',
                    'required' => '',
                ];
                if ($type === FormField::TYPE_RATING) {
                    $current['rating_min'] = 1;
                    $current['rating_max'] = 5;
                    $current['rating_step'] = 1;
                    $current['rating_display'] = FormField::RATING_DISPLAY_PILLS;
                }
                continue;
            }

            if ($current === null && mb_strlen($line) > 40) {
                $fields[] = [
                    'type' => FormField::TYPE_RICH_TEXT,
                    'key' => 'intro' . (count($fields) + 1),
                    'label' => 'Instructions',
                    'rich_content' => '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>',
                    'required' => '',
                ];
            } elseif ($current !== null && $pendingOptions === [] && mb_strlen($line) < 120) {
                $current['help_text'] = trim(($current['help_text'] ?? '') . ' ' . $line);
            }
        }
        $flushCurrent();

        if ($fields === []) {
            $fields[] = [
                'type' => FormField::TYPE_TEXTAREA,
                'key' => 'q1',
                'label' => Yii::t('ThiscoveryFormsModule.base', 'Please add your response'),
                'help_text' => Yii::t('ThiscoveryFormsModule.base', 'Generated from a brief that had no detectable questions — edit in the builder.'),
                'required' => '',
            ];
        }

        return $fields;
    }

    protected function guessTitle(string $brief): string
    {
        $lines = preg_split("/\n/u", trim($brief)) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && mb_strlen($line) <= 120 && !preg_match('/^\d+[.)]/', $line)) {
                return $line;
            }
        }
        return Yii::t('ThiscoveryFormsModule.base', 'Survey from brief');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    protected function normalizeFieldRow(array $row): ?array
    {
        $type = strtolower(trim((string)($row['type'] ?? '')));
        $aliases = [
            'single_choice' => FormField::TYPE_RADIO,
            'multiple_choice' => FormField::TYPE_CHECKBOX,
            'select' => FormField::TYPE_DROPDOWN,
            'paragraph' => FormField::TYPE_TEXTAREA,
            'short_text' => FormField::TYPE_TEXT,
            'instruction' => FormField::TYPE_RICH_TEXT,
            'section' => FormField::TYPE_PAGE_BREAK,
        ];
        $type = $aliases[$type] ?? $type;
        if ($type === '' || !isset(FormField::getTypeLabels()[$type])) {
            return null;
        }
        $label = trim((string)($row['label'] ?? ''));
        if ($label === '' && $type !== FormField::TYPE_PAGE_BREAK) {
            return null;
        }
        $helpText = trim((string)($row['help_text'] ?? ''));
        $out = [
            'type' => $type,
            'key' => preg_replace('/[^a-zA-Z0-9_]/', '', (string)($row['key'] ?? '')) ?: '',
            'label' => mb_substr($label !== '' ? $label : 'Page break', 0, 255),
            'help_text' => $helpText,
            'required' => !empty($row['required']) ? '1' : '',
        ];
        $options = $this->normalizeOptionList($row['options'] ?? null);
        if ($options) {
            $out['options'] = $options;
        }
        if ($type === FormField::TYPE_PAGE_BREAK) {
            $out['page_key'] = (string)($row['page_key'] ?? ('page-' . substr(md5($label), 0, 6)));
            $out['page_title'] = (string)($row['page_title'] ?? $label);
        }
        if ($type === FormField::TYPE_RATING) {
            $out['rating_min'] = $row['rating_min'] ?? 1;
            $out['rating_max'] = $row['rating_max'] ?? 5;
            $out['rating_step'] = $row['rating_step'] ?? 1;
            $out['rating_display'] = $row['rating_display'] ?? FormField::RATING_DISPLAY_PILLS;
        }
        if ($type === FormField::TYPE_RICH_TEXT) {
            $out['rich_content'] = $this->normalizeRichContent(
                (string)($row['rich_content'] ?? ''),
                $out['label'],
                $helpText
            );
            // Long body copy belongs in rich_content; help_text is capped at 500 in FormField.
            $out['help_text'] = '';
        }
        if ($type === FormField::TYPE_GRID_SINGLE || $type === FormField::TYPE_GRID_MULTI) {
            $rows = $this->normalizeOptionList($row['grid_rows'] ?? null) ?: $options;
            $cols = $this->normalizeOptionList($row['grid_columns'] ?? null);
            if (!$cols) {
                $cols = ['Not at all', 'A little', 'Somewhat', 'Quite a bit', 'Very much', 'N/A'];
            }
            if ($rows) {
                $out['grid_rows'] = $rows;
            }
            $out['grid_columns'] = $cols;
        }
        // FormField::help_text max 500 — truncate after rich_text merge.
        if (mb_strlen((string)$out['help_text']) > 500) {
            $out['help_text'] = mb_substr((string)$out['help_text'], 0, 500);
        }
        if (($out['key'] ?? '') === '') {
            $out['key'] = 'f' . substr(md5($type . $label), 0, 8);
        }
        return $out;
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    protected function normalizeOptionList($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                $label = trim((string)($item['label'] ?? $item['text'] ?? $item['value'] ?? ''));
                if ($label === '') {
                    $label = trim((string)($item['code'] ?? ''));
                }
            } else {
                $label = trim((string)$item);
            }
            if ($label !== '') {
                $out[] = $label;
            }
        }
        return array_values($out);
    }

    protected function normalizeRichContent(string $rich, string $label, string $helpText): string
    {
        $rich = trim($rich);
        $labelHtml = '<p>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        $placeholder = ($rich === '' || $rich === $labelHtml);
        if ($helpText === '') {
            return $placeholder ? $labelHtml : $rich;
        }
        $paras = preg_split("/\n\s*\n/u", $helpText) ?: [$helpText];
        $body = '';
        foreach ($paras as $para) {
            $para = trim((string)$para);
            if ($para === '') {
                continue;
            }
            $body .= '<p>' . nl2br(htmlspecialchars($para, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';
        }
        if ($placeholder) {
            return ($label !== '' ? '<p><strong>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong></p>' : '') . $body;
        }
        return $rich . $body;
    }
}
