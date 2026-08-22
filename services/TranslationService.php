<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormFieldI18n;
use humhub\modules\thiscoveryForms\models\FormI18n;
use Yii;

class TranslationService
{
    public static function languageLabels(): array
    {
        return [
            'en-GB' => Yii::t('ThiscoveryFormsModule.base', 'English (UK)'),
            'en-US' => Yii::t('ThiscoveryFormsModule.base', 'English (US)'),
            'cy' => Yii::t('ThiscoveryFormsModule.base', 'Welsh'),
            'gd' => Yii::t('ThiscoveryFormsModule.base', 'Scottish Gaelic'),
            'fr' => Yii::t('ThiscoveryFormsModule.base', 'French'),
            'de' => Yii::t('ThiscoveryFormsModule.base', 'German'),
            'es' => Yii::t('ThiscoveryFormsModule.base', 'Spanish'),
            'ar' => Yii::t('ThiscoveryFormsModule.base', 'Arabic'),
            'ur' => Yii::t('ThiscoveryFormsModule.base', 'Urdu'),
        ];
    }

    /**
     * @return string[] Language bases that must render fill UI right-to-left
     */
    public static function rtlLanguageBases(): array
    {
        return ['ar', 'ur', 'fa', 'he', 'pnb'];
    }

    public static function isRtl(string $code): bool
    {
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
        $posted = $this->extraFromData($data);
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
            if ($decoded && (isset($decoded['options']) || isset($decoded['__type']))) {
                $decoded['options'] = $overlay['options'];
            } elseif ($this->isList($decoded) || !$decoded) {
                $decoded = $overlay['options'];
            } else {
                $decoded['options'] = $overlay['options'];
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
            $decoded['rows'] = $overlay['rows'];
        }
        if (isset($overlay['columns']) && is_array($overlay['columns']) && array_key_exists('columns', $decoded)) {
            $decoded['columns'] = $overlay['columns'];
        }
        if (isset($overlay['items']) && is_array($overlay['items']) && array_key_exists('items', $decoded)) {
            $decoded['items'] = $overlay['items'];
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
    public function extraFromData(array $data): array
    {
        $extra = [];
        $options = $data['options'] ?? '';
        if (is_array($options)) {
            $list = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $options), 'strlen'));
            if ($list) {
                $extra['options'] = $list;
            }
        } else {
            $options = trim((string)$options);
            if ($options !== '') {
                $lines = preg_split('/\r\n|\r|\n/', $options) ?: [];
                $extra['options'] = array_values(array_filter(array_map('trim', $lines), 'strlen'));
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
        foreach (['rows', 'columns', 'items'] as $key) {
            $val = $data[$key] ?? null;
            if (is_array($val)) {
                $list = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $val), 'strlen'));
                if ($list) {
                    $extra[$key] = $list;
                }
            } elseif (is_string($val) && trim($val) !== '') {
                $lines = preg_split('/\r\n|\r|\n/', $val) ?: [];
                $list = array_values(array_filter(array_map('trim', $lines), 'strlen'));
                if ($list) {
                    $extra[$key] = $list;
                }
            }
        }
        return $extra;
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
