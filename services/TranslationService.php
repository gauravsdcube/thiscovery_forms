<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormFieldI18n;
use humhub\modules\thiscoveryForms\models\FormI18n;
use Yii;

class TranslationService
{
    /**
     * Full catalogue of language codes Forms can understand (labels for display).
     * Merges Thiscovery Translate LocaleMap when that module is present.
     *
     * @return array<string, string>
     */
    public static function languageLabels(): array
    {
        $labels = [];
        foreach (self::englishLanguageNames() as $code => $name) {
            $labels[$code] = Yii::t('ThiscoveryFormsModule.base', $name);
        }

        try {
            if (class_exists(\humhub\modules\thiscoveryTranslate\services\LocaleMap::class)) {
                foreach (\humhub\modules\thiscoveryTranslate\services\LocaleMap::labels() as $code => $label) {
                    if (!isset($labels[$code])) {
                        $labels[$code] = $label;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Forms must work without Translate installed.
        }

        return $labels;
    }

    /**
     * Participant language switch: English name, then the name in that language.
     * English stays "English (UK)" when the two names are the same.
     */
    public static function participantLanguageLabel(string $code): string
    {
        $english = self::englishLanguageName($code);
        $native = self::nativeLanguageName($code);
        if ($native === '' || strcasecmp($english, $native) === 0) {
            return $english;
        }
        return $english . '/' . $native;
    }

    public static function englishLanguageName(string $code): string
    {
        $names = self::englishLanguageNames();
        $found = self::lookupLabel($names, $code);
        if ($found !== null) {
            return $found;
        }
        try {
            if (class_exists(\humhub\modules\thiscoveryTranslate\services\LocaleMap::class)) {
                $found = self::lookupLabel(\humhub\modules\thiscoveryTranslate\services\LocaleMap::labels(), $code);
                if ($found !== null) {
                    return $found;
                }
            }
        } catch (\Throwable $e) {
            // Forms must work without Translate installed.
        }
        return $code;
    }

    /**
     * @return array<string, string>
     */
    private static function englishLanguageNames(): array
    {
        return [
            'en-GB' => 'English (UK)',
            'en-US' => 'English (US)',
            'cy' => 'Welsh',
            'gd' => 'Scottish Gaelic',
            'ga' => 'Irish',
            'fr' => 'French',
            'de' => 'German',
            'es' => 'Spanish',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'pl' => 'Polish',
            'ro' => 'Romanian',
            'ar' => 'Arabic',
            'ur' => 'Urdu',
            'zh' => 'Chinese (Simplified)',
            'hi' => 'Hindi',
            'bn' => 'Bengali',
            'pa' => 'Punjabi',
            'gu' => 'Gujarati',
            'tr' => 'Turkish',
            'uk' => 'Ukrainian',
            'ru' => 'Russian',
            'sv' => 'Swedish',
            'da' => 'Danish',
            'fi' => 'Finnish',
            'el' => 'Greek',
        ];
    }

    private static function nativeLanguageName(string $code): string
    {
        $natives = ['gd' => 'Gàidhlig'];
        try {
            if (class_exists(\humhub\modules\thiscoveryTranslate\services\LocaleMap::class)) {
                $natives = array_merge(\humhub\modules\thiscoveryTranslate\services\LocaleMap::nativeLabels(), $natives);
            }
        } catch (\Throwable $e) {
            // Forms must work without Translate installed.
        }
        $found = self::lookupLabel($natives, $code);
        if ($found !== null) {
            return $found;
        }
        $base = explode('-', str_replace('_', '-', $code))[0] ?? '';
        if ($base !== '' && $base !== $code) {
            $found = self::lookupLabel($natives, $base);
            if ($found !== null) {
                return $found;
            }
        }
        return '';
    }

    /**
     * @param array<string, string> $labels
     */
    private static function lookupLabel(array $labels, string $code): ?string
    {
        if (isset($labels[$code])) {
            return (string)$labels[$code];
        }
        foreach ($labels as $key => $label) {
            if (strcasecmp((string)$key, $code) === 0) {
                return (string)$label;
            }
        }
        return null;
    }

    /**
     * Languages offered in form Settings (checkboxes).
     * When Thiscovery Translate is enabled, mirrors its instance language list
     * (plus any languages already enabled on this form so nothing disappears).
     *
     * @return array<string, string>
     */
    public static function selectableLanguageLabels(?CustomForm $form = null): array
    {
        $all = self::languageLabels();
        $codes = null;
        try {
            if (Yii::$app->hasModule('thiscovery-translate')) {
                $module = Yii::$app->getModule('thiscovery-translate');
                if ($module && method_exists($module, 'getIsEnabled') && $module->getIsEnabled()
                    && class_exists(\humhub\modules\thiscoveryTranslate\models\ModuleSettings::class)) {
                    $settings = \humhub\modules\thiscoveryTranslate\models\ModuleSettings::loadSettings();
                    if ($settings->formsTranslateEnabled && $settings->availableLanguages) {
                        $codes = array_values(array_unique(array_map('strval', $settings->availableLanguages)));
                    }
                }
            }
        } catch (\Throwable $e) {
            $codes = null;
        }

        if ($codes === null) {
            return $all;
        }

        if ($form !== null) {
            foreach ($form->getEnabledLanguages() as $code) {
                if (!in_array($code, $codes, true)) {
                    $codes[] = $code;
                }
            }
        }

        $out = [];
        foreach ($codes as $code) {
            $out[$code] = $all[$code] ?? $code;
        }
        return $out;
    }

    /**
     * @return string[] Language bases that must render fill UI right-to-left
     */
    public static function rtlLanguageBases(): array
    {
        return ['ar', 'ur', 'fa', 'he', 'pnb', 'ps', 'sd'];
    }

    public static function isRtl(string $code): bool
    {
        try {
            if (class_exists(\humhub\modules\thiscoveryTranslate\services\LocaleMap::class)) {
                return \humhub\modules\thiscoveryTranslate\services\LocaleMap::isRtl($code);
            }
        } catch (\Throwable $e) {
            // fall through
        }
        $base = strtolower(explode('-', str_replace('_', '-', trim($code)))[0] ?? '');
        return $base !== '' && in_array($base, self::rtlLanguageBases(), true);
    }

    public static function normalizeLanguage(string $code): ?string
    {
        $code = str_replace('_', '-', trim($code));
        if ($code === '') {
            return null;
        }
        $known = array_keys(self::languageLabels());
        foreach ($known as $id) {
            if (strcasecmp($id, $code) === 0) {
                return $id;
            }
        }
        $base = strtolower(explode('-', $code)[0] ?? '');
        foreach ($known as $id) {
            if (strtolower($id) === $base) {
                return $id;
            }
        }
        return null;
    }

    public function resolve(CustomForm $form): string
    {
        $enabled = $form->getEnabledLanguages();
        $source = $form->getSourceLanguage();
        if (count($enabled) < 2) {
            return $source;
        }

        $requested = self::normalizeLanguage((string)Yii::$app->request->get('lang', ''));
        if ($requested !== null && in_array($requested, $enabled, true)) {
            Yii::$app->session->set($this->sessionKey($form), $requested);
            return $requested;
        }

        $sessionLang = self::normalizeLanguage((string)Yii::$app->session->get($this->sessionKey($form), ''));
        if ($sessionLang !== null && in_array($sessionLang, $enabled, true)) {
            return $sessionLang;
        }

        $user = Yii::$app->user->getIdentity();
        if ($user && !empty($user->language)) {
            $userLang = self::normalizeLanguage((string)$user->language);
            if ($userLang !== null && in_array($userLang, $enabled, true)) {
                return $userLang;
            }
        }

        $preferred = Yii::$app->request->getPreferredLanguage($enabled);
        if ($preferred && in_array($preferred, $enabled, true)) {
            return $preferred;
        }

        return $source;
    }

    /**
     * Apply translations in memory for fill (does not persist).
     */
    public function overlay(CustomForm $form, string $lang): void
    {
        $source = $form->getSourceLanguage();
        if ($lang === '' || $lang === $source) {
            return;
        }

        $formI18n = FormI18n::findOne(['form_id' => $form->id, 'language' => $lang]);
        if ($formI18n) {
            if (trim((string)$formI18n->title) !== '') {
                $form->title = $formI18n->title;
            }
            if (trim((string)$formI18n->description) !== '') {
                $form->description = $formI18n->description;
            }
            if (trim((string)$formI18n->thank_you_content) !== '') {
                $form->thank_you_content = $formI18n->thank_you_content;
            }
        }

        $ids = [];
        foreach ($form->fields as $field) {
            $ids[] = (int)$field->id;
        }
        if (!$ids) {
            return;
        }

        $rows = FormFieldI18n::find()
            ->where(['field_id' => $ids, 'language' => $lang])
            ->indexBy('field_id')
            ->all();

        foreach ($form->fields as $field) {
            $row = $rows[$field->id] ?? null;
            if (!$row instanceof FormFieldI18n) {
                continue;
            }
            if (trim((string)$row->label) !== '') {
                $field->label = $row->label;
            }
            if (trim((string)$row->help_text) !== '') {
                $field->help_text = $row->help_text;
            }
            $this->mergeOptions($field, $row);
        }

        // Participant fill must not call Amazon — overlays are pre-generated / manually edited.
    }

    /**
     * @deprecated Machine translation at fill time removed; use Thiscovery Translate FormTranslateAdapter jobs.
     * @param array<int, FormFieldI18n> $rows
     */
    private function applyMachineFallback(CustomForm $form, string $source, string $lang, ?FormI18n $formI18n, array $rows): void
    {
        // Intentionally empty — AWS must not run during participant fill.
    }

    public function completeness(CustomForm $form, string $lang): int
    {
        $total = 2;
        $done = 0;
        $formI18n = FormI18n::findOne(['form_id' => $form->id, 'language' => $lang]);
        if ($formI18n && trim((string)$formI18n->title) !== '') {
            $done++;
        }
        if ($formI18n && trim((string)$formI18n->description) !== '') {
            $done++;
        }
        foreach ($form->fields as $field) {
            $total++;
            $row = FormFieldI18n::findOne(['field_id' => $field->id, 'language' => $lang]);
            if ($row && trim((string)$row->label) !== '') {
                $done++;
            }
        }
        return $total > 0 ? (int)round(($done / $total) * 100) : 0;
    }

    public function saveFormStrings(CustomForm $form, string $lang, array $data): void
    {
        $row = FormI18n::findOne(['form_id' => $form->id, 'language' => $lang]) ?: new FormI18n();
        $row->form_id = $form->id;
        $row->language = $lang;
        $row->title = trim((string)($data['title'] ?? ''));
        $row->description = (string)($data['description'] ?? '');
        $row->thank_you_content = (string)($data['thank_you_content'] ?? '');
        if ($row->isEmpty()) {
            if (!$row->isNewRecord) {
                $row->delete();
            }
            return;
        }
        $row->save(false);
    }

    public function saveFieldStrings(FormField $field, string $lang, array $data): void
    {
        $row = FormFieldI18n::findOne(['field_id' => $field->id, 'language' => $lang]) ?: new FormFieldI18n();
        $row->field_id = $field->id;
        $row->language = $lang;
        $row->label = trim((string)($data['label'] ?? ''));
        $row->help_text = trim((string)($data['help_text'] ?? ''));
        $extra = $row->isNewRecord ? [] : $row->getOptionsOverlay();
        $posted = $this->extraFromData($data, $field);
        foreach ($posted as $key => $value) {
            $extra[$key] = $value;
        }
        if (array_key_exists('options', $data) && !isset($posted['options'])) {
            unset($extra['options']);
        }
        $row->options_json = $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null;
        if ($row->isEmpty()) {
            if (!$row->isNewRecord) {
                $row->delete();
            }
            return;
        }
        $row->save(false);
    }

    public function copyOnto(CustomForm $source, CustomForm $target): void
    {
        foreach (FormI18n::find()->where(['form_id' => $source->id])->all() as $row) {
            $copy = new FormI18n();
            $copy->form_id = $target->id;
            $copy->language = $row->language;
            $copy->title = $row->title;
            $copy->description = $row->description;
            $copy->thank_you_content = $row->thank_you_content;
            $copy->save(false);
        }

        $sourceFields = $source->fields;
        $targetFields = $target->fields;
        $count = min(count($sourceFields), count($targetFields));
        for ($i = 0; $i < $count; $i++) {
            $from = $sourceFields[$i];
            $to = $targetFields[$i];
            foreach (FormFieldI18n::find()->where(['field_id' => $from->id])->all() as $row) {
                $copy = new FormFieldI18n();
                $copy->field_id = $to->id;
                $copy->language = $row->language;
                $copy->label = $row->label;
                $copy->help_text = $row->help_text;
                $copy->options_json = $row->options_json;
                $copy->save(false);
            }
        }
    }

    private function mergeOptions(FormField $field, FormFieldI18n $row): void
    {
        $overlay = $row->getOptionsOverlay();
        if (!$overlay) {
            return;
        }
        $decoded = json_decode((string)$field->options_json, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        if (isset($overlay['options']) && is_array($overlay['options'])) {
            $source = [];
            if (isset($decoded['options']) && is_array($decoded['options'])) {
                $source = $decoded['options'];
            } elseif ($this->isList($decoded)) {
                $source = $decoded;
            }
            $relabelled = $this->relabelChoices($source, $overlay['options'], 'options field ' . (int)$field->id);
            if (isset($decoded['options']) || isset($decoded['__type'])) {
                $decoded['options'] = $relabelled;
            } elseif ($this->isList($decoded) || !$decoded) {
                $decoded = $relabelled;
            } else {
                $decoded['options'] = $relabelled;
            }
        }
        if (isset($overlay['page_title']) && isset($decoded['title'])) {
            $decoded['title'] = $overlay['page_title'];
        }
        if (isset($overlay['rich_content']) && isset($decoded['content'])) {
            $decoded['content'] = $overlay['rich_content'];
        }
        if (isset($overlay['html_content']) && isset($decoded['html'])) {
            $decoded['html'] = $overlay['html_content'];
        }
        if (isset($overlay['html_instructions']) && array_key_exists('instructions', $decoded)) {
            $decoded['instructions'] = $overlay['html_instructions'];
        }
        if (isset($overlay['rows']) && is_array($overlay['rows']) && array_key_exists('rows', $decoded)) {
            $decoded['rows'] = $this->relabelGrid($decoded['rows'], $overlay['rows'], 'rows field ' . (int)$field->id);
        }
        if (isset($overlay['columns']) && is_array($overlay['columns']) && array_key_exists('columns', $decoded)) {
            $decoded['columns'] = $this->relabelGrid($decoded['columns'], $overlay['columns'], 'columns field ' . (int)$field->id);
        }
        if (isset($overlay['items']) && is_array($overlay['items']) && array_key_exists('items', $decoded)) {
            $this->applyItemLabels($field, $overlay['items']);
        }
        if (isset($overlay['loop_items']) && is_array($overlay['loop_items']) && is_array($decoded['loop']['items'] ?? null)) {
            // Fixed loop items, relabelled by code (V3-46).
            foreach ($decoded['loop']['items'] as $i => $item) {
                $code = is_array($item) ? (string)($item['code'] ?? '') : '';
                $label = trim((string)($overlay['loop_items'][$code] ?? ''));
                if ($code !== '' && $label !== '') {
                    $decoded['loop']['items'][$i]['label'] = $label;
                }
            }
        }
        if (isset($overlay['lowLabel']) && array_key_exists('lowLabel', $decoded)) {
            $decoded['lowLabel'] = $overlay['lowLabel'];
        }
        if (isset($overlay['highLabel']) && array_key_exists('highLabel', $decoded)) {
            $decoded['highLabel'] = $overlay['highLabel'];
        }
        $field->options_json = json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function extraFromData(array $data, ?FormField $field = null): array
    {
        $extra = [];
        if (!empty($data['option_map']) && is_array($data['option_map'])) {
            $extra['options'] = $this->stringMap($data['option_map']);
        } else {
            $options = $data['options'] ?? '';
            $mapped = $this->choiceMapFromPosted($options, $field ? $field->getChoicePairs() : [], 'options field ' . (int)($field->id ?? 0));
            if ($mapped) {
                $extra['options'] = $mapped;
            }
        }
        foreach (['page_title', 'rich_content', 'html_content', 'html_instructions'] as $key) {
            if (isset($data[$key]) && trim((string)$data[$key]) !== '') {
                $extra[$key] = (string)$data[$key];
            }
        }
        if (isset($data['rating_low_label']) && trim((string)$data['rating_low_label']) !== '') {
            $extra['lowLabel'] = (string)$data['rating_low_label'];
        }
        if (isset($data['rating_high_label']) && trim((string)$data['rating_high_label']) !== '') {
            $extra['highLabel'] = (string)$data['rating_high_label'];
        }
        if (!empty($data['loop_items']) && is_array($data['loop_items'])) {
            $extra['loop_items'] = $this->stringMap($data['loop_items']);
        }
        foreach (['rows', 'columns', 'items'] as $key) {
            if (!isset($data[$key]) || !$field) {
                continue;
            }
            if ($key === 'items') {
                $pairs = $field->getItemsConfig()['items'] ?? [];
            } else {
                $pairs = $field->getGridConfig()[$key] ?? [];
            }
            $mapped = $this->choiceMapFromPosted($data[$key], is_array($pairs) ? $pairs : [], $key . ' field ' . (int)$field->id);
            if ($mapped) {
                $extra[$key] = $mapped;
            }
        }
        return $extra;
    }

    /**
     * Replace grid labels. The posted value stays the source code or, when there
     * is no code, the source label.
     *
     * @param mixed $source
     * @param mixed $overlay
     * @return array<int, array{code:string,label:string,value?:string}|string>
     */
    private function relabelGrid($source, $overlay, string $context): array
    {
        $pairs = FormField::gridPairs($source);
        if (!$pairs) {
            return is_array($source) ? $source : [];
        }
        $labels = $this->gridLabelMap($pairs, $overlay, $context);
        $out = [];
        foreach ($pairs as $pair) {
            $key = FormField::gridPairKey($pair);
            $label = $labels[$key] ?? (string)$pair['label'];
            if ((string)$pair['code'] === '') {
                $out[] = [
                    'code' => '',
                    'label' => $label,
                    'value' => (string)$pair['value'],
                ];
            } else {
                $out[] = ['code' => (string)$pair['code'], 'label' => $label];
            }
        }
        return $out;
    }

    /**
     * @param array<int, array{code?:string,label?:string,value?:string}> $pairs
     * @param mixed $overlay
     * @return array<string, string>
     */
    private function gridLabelMap(array $pairs, $overlay, string $context): array
    {
        if (!is_array($overlay) || !$overlay) {
            return [];
        }
        if (!array_is_list($overlay)) {
            return $this->stringMap($overlay);
        }
        if (count($overlay) !== count($pairs)) {
            Yii::warning(
                'Thiscovery Forms translation count mismatch (' . $context . '): stored '
                . count($overlay) . ', source ' . count($pairs) . '. Source labels kept.',
                'thiscovery-forms'
            );
            return [];
        }
        $map = [];
        foreach ($pairs as $i => $pair) {
            $key = FormField::gridPairKey($pair);
            if ($key === '') {
                continue;
            }
            $item = $overlay[$i];
            $text = is_array($item) ? trim((string)($item['label'] ?? '')) : trim((string)$item);
            if ($text !== '') {
                $map[$key] = $text;
            }
        }
        return $map;
    }

    /**
     * Best/Worst and MaxDiff stay positional. The translation is only a display label.
     *
     * @param array<int|string, mixed> $overlay
     */
    private function applyItemLabels(FormField $field, array $overlay): void
    {
        $items = $field->getItemsConfig()['items'];
        $map = [];
        if (!array_is_list($overlay)) {
            foreach ($overlay as $key => $label) {
                $text = is_array($label) ? trim((string)($label['label'] ?? '')) : trim((string)$label);
                if ($text !== '') {
                    $map[(string)$key] = $this->plainItemLabel($text);
                }
            }
        } elseif (count($overlay) === count($items)) {
            foreach ($items as $i => $item) {
                $raw = $overlay[$i];
                $text = is_array($raw) ? trim((string)($raw['label'] ?? '')) : trim((string)$raw);
                if ($text !== '') {
                    $map[(string)$item] = $this->plainItemLabel($text);
                }
            }
        }
        $field->itemLabelOverlay = $map;
    }

    private function plainItemLabel(string $label): string
    {
        if (preg_match('/^(.+?)\s+\|\s+(.+)$/u', trim($label), $m)) {
            return trim($m[2]);
        }
        return trim($label);
    }

    /**
     * Keep choice codes and replace only labels. A positional list is mapped by
     * index; a count mismatch keeps the source label and logs a warning.
     *
     * @param mixed $source
     * @param mixed $overlay
     * @return array<int, string>
     */
    private function relabelChoices($source, $overlay, string $context): array
    {
        if (!is_array($source)) {
            return [];
        }
        $pairs = ChoiceOptions::itemsFromDecoded(['options' => $source]);
        $labels = $this->translationLabelMap($pairs, $overlay, $context);
        if (!$labels) {
            return $source;
        }
        $out = [];
        foreach ($pairs as $pair) {
            $code = (string)$pair['code'];
            $label = $labels[$code] ?? (string)$pair['label'];
            $out[] = $code . ' | ' . $label;
        }
        return $out;
    }

    /**
     * @param array<int, array{code?:string,label?:string}> $pairs
     * @param mixed $overlay
     * @return array<string, string>
     */
    private function translationLabelMap(array $pairs, $overlay, string $context): array
    {
        if (!is_array($overlay) || !$overlay) {
            return [];
        }
        if (!array_is_list($overlay)) {
            return $this->stringMap($overlay);
        }
        if (count($overlay) !== count($pairs)) {
            Yii::warning(
                'Thiscovery Forms translation count mismatch (' . $context . '): stored '
                . count($overlay) . ', source ' . count($pairs) . '. Source labels kept.',
                'thiscovery-forms'
            );
            return [];
        }
        $map = [];
        foreach ($pairs as $i => $pair) {
            $code = (string)($pair['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $item = $overlay[$i];
            if (is_array($item)) {
                $map[$code] = trim((string)($item['label'] ?? $pair['label'] ?? $code));
            } else {
                $parsed = ChoiceOptions::parseLine(trim((string)$item));
                $map[$code] = $parsed['label'] !== '' ? $parsed['label'] : trim((string)$item);
            }
        }
        return $map;
    }

    /**
     * @param array<int, array{code?:string,label?:string}> $pairs
     * @param mixed $posted
     * @return array<string, string>
     */
    private function choiceMapFromPosted($posted, array $pairs, string $context): array
    {
        if (is_string($posted)) {
            $posted = preg_split('/\r\n|\r|\n/', $posted) ?: [];
        }
        if (!is_array($posted) || !$posted) {
            return [];
        }
        if (!array_is_list($posted)) {
            return $this->stringMap($posted);
        }
        if ($pairs && is_string(reset($pairs))) {
            $lines = [];
            foreach ($posted as $item) {
                $text = trim((string)$item);
                if ($text !== '') {
                    $lines[] = $text;
                }
            }
            if (count($lines) !== count($pairs)) {
                Yii::warning(
                    'Thiscovery Forms translation count mismatch (' . $context . '): posted '
                    . count($lines) . ', source ' . count($pairs) . '.',
                    'thiscovery-forms'
                );
                return [];
            }
            return $lines;
        }
        $lines = [];
        $map = [];
        foreach ($posted as $item) {
            if (is_array($item)) {
                $code = trim((string)($item['code'] ?? ''));
                $label = trim((string)($item['label'] ?? ''));
                if ($code !== '') {
                    $map[$code] = $label !== '' ? $label : $code;
                }
                continue;
            }
            $text = trim((string)$item);
            if ($text !== '') {
                $lines[] = $text;
            }
        }
        if ($map && !$lines) {
            return $map;
        }
        if (!$pairs || count($lines) !== count($pairs)) {
            if ($lines) {
                Yii::warning(
                    'Thiscovery Forms translation count mismatch (' . $context . '): posted '
                    . count($lines) . ', source ' . count($pairs) . '.',
                    'thiscovery-forms'
                );
            }
            return $map;
        }
        foreach ($pairs as $i => $pair) {
            $code = is_array($pair) ? FormField::gridPairKey($pair) : trim((string)($pair['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $parsed = ChoiceOptions::parseLine($lines[$i]);
            $map[$code] = $parsed['label'] !== '' ? $parsed['label'] : $lines[$i];
        }
        return $map;
    }

    /**
     * @param array<mixed> $map
     * @return array<string, string>
     */
    private function stringMap(array $map): array
    {
        $out = [];
        foreach ($map as $code => $label) {
            if (is_array($label)) {
                $code = (string)($label['code'] ?? $code);
                $label = (string)($label['label'] ?? '');
            }
            $code = trim((string)$code);
            $label = trim((string)$label);
            if ($code === '' || $label === '') {
                continue;
            }
            $out[$code] = $label;
        }
        return $out;
    }

    /**
     * Shape an i18n row for saveFieldStrings / merge-on-import.
     */
    public function fieldRowToData(FormFieldI18n $row): array
    {
        $overlay = $row->getOptionsOverlay();
        $options = $overlay['options'] ?? [];
        return [
            'label' => (string)$row->label,
            'help_text' => (string)$row->help_text,
            'options' => is_array($options) ? implode("\n", $options) : '',
            'page_title' => (string)($overlay['page_title'] ?? ''),
            'rich_content' => (string)($overlay['rich_content'] ?? ''),
            'html_content' => (string)($overlay['html_content'] ?? ''),
            'html_instructions' => (string)($overlay['html_instructions'] ?? ''),
            'rating_low_label' => (string)($overlay['lowLabel'] ?? ''),
            'rating_high_label' => (string)($overlay['highLabel'] ?? ''),
            'rows' => is_array($overlay['rows'] ?? null) ? $overlay['rows'] : [],
            'columns' => is_array($overlay['columns'] ?? null) ? $overlay['columns'] : [],
            'items' => is_array($overlay['items'] ?? null) ? $overlay['items'] : [],
            'loop_items' => is_array($overlay['loop_items'] ?? null) ? $overlay['loop_items'] : [],
        ];
    }

    private function isList(array $arr): bool
    {
        return $arr === [] || array_keys($arr) === range(0, count($arr) - 1);
    }

    private function sessionKey(CustomForm $form): string
    {
        return 'cf_lang_' . (int)$form->id;
    }
}
