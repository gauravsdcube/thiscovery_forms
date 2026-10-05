<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormEmailTemplate;
use humhub\modules\thiscoveryForms\models\FormEmailTemplateI18n;
use humhub\modules\thiscoveryForms\models\FormI18n;
use humhub\modules\thiscoveryForms\models\FormMessageI18n;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use Yii;
use yii\db\Query;

/**
 * Participant buttons, checks, endings, and the form's own messages.
 * Questions stay on the existing overlay.
 */
class ParticipantMessages
{
    private static ?CustomForm $form = null;
    private static string $language = '';
    private static bool $active = false;

    /** @var array<string, string> */
    private static array $formTexts = [];

    /** @var array<string, string> */
    private static array $siteTexts = [];

    private static bool $installed = false;

    public static function install(): void
    {
        if (self::$installed || !Yii::$app->has('i18n')) {
            return;
        }
        try {
            $source = Yii::$app->i18n->getMessageSource('ThiscoveryFormsModule.base');
            if ($source instanceof FillMessageSource) {
                self::$installed = true;
                return;
            }
            Yii::$app->i18n->translations['ThiscoveryFormsModule.base'] = new FillMessageSource($source);
            self::$installed = true;
        } catch (\Throwable $e) {
            Yii::warning('Participant message source was not installed: ' . $e->getMessage(), 'thiscovery-forms');
        }
    }

    public static function bind(CustomForm $form, string $language): void
    {
        self::install();
        self::$form = $form;
        self::$language = $language;
        self::$active = $language !== '';
        self::$formTexts = [];
        self::$siteTexts = [];
        if (!self::tablesReady()) {
            (new TranslationService())->overlay($form, $language);
            return;
        }
        self::$formTexts = self::mapFor((int)$form->id, $language);
        self::$siteTexts = self::mapFor(FormMessageI18n::SITE_FORM_ID, $language);
        (new TranslationService())->overlay($form, $language);
        self::applyAuthored($form);
    }

    public static function isActive(): bool
    {
        return self::$active && self::$language !== '';
    }

    public static function clear(): void
    {
        self::$active = false;
        self::$form = null;
        self::$language = '';
        self::$formTexts = [];
        self::$siteTexts = [];
    }

    public static function activeLanguage(): ?string
    {
        return self::isActive() ? self::$language : null;
    }

    public static function activeForm(): ?CustomForm
    {
        return self::$form;
    }

    /**
     * Translated catalogue sentence, or null when this English sentence is not in the catalogue.
     */
    public static function catalogText(string $source): ?string
    {
        $key = ParticipantMessageCatalog::keyFor($source);
        if ($key === null) {
            return null;
        }
        $formText = trim(self::$formTexts[$key] ?? '');
        if ($formText !== '') {
            return $formText;
        }
        $siteText = trim(self::$siteTexts[$key] ?? '');
        if ($siteText !== '') {
            return $siteText;
        }
        return $source;
    }

    public static function authored(string $key, string $fallback): string
    {
        if (!self::isActive()) {
            return $fallback;
        }
        $text = trim(self::$formTexts[$key] ?? '');
        return $text !== '' ? $text : $fallback;
    }

    public static function stored(CustomForm $form, string $language, string $key): string
    {
        if (!self::tablesReady()) {
            return '';
        }
        $row = FormMessageI18n::findOne([
            'form_id' => (int)$form->id,
            'language' => $language,
            'message_key' => $key,
        ]);
        return $row ? (string)$row->text : '';
    }

    public static function isLocked(int $formId, string $language, string $key): bool
    {
        if (!self::tablesReady()) {
            return false;
        }
        $row = FormMessageI18n::findOne([
            'form_id' => $formId,
            'language' => $language,
            'message_key' => $key,
        ]);
        return $row !== null && (int)$row->locked === 1 && trim((string)$row->text) !== '';
    }

    public static function applyAuthored(CustomForm $form): void
    {
        $thank = trim(self::$formTexts['author.thank_you'] ?? '');
        if ($thank !== '') {
            $form->thank_you_content = $thank;
        }
    }

    /**
     * @param array<string, string> $posted
     */
    public static function savePosted(CustomForm $form, string $language, array $posted): void
    {
        if (!self::tablesReady()) {
            return;
        }
        $allowed = array_keys(ParticipantMessageCatalog::entries());
        foreach (self::authorSources($form) as $key => $meta) {
            $allowed[] = $key;
        }
        $allowed[] = 'author.thank_you';
        foreach ($posted as $key => $value) {
            $key = (string)$key;
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            $text = trim((string)$value);
            $existing = FormMessageI18n::findOne([
                'form_id' => (int)$form->id,
                'language' => $language,
                'message_key' => $key,
            ]);
            if ($existing && (string)$existing->text === $text) {
                continue;
            }
            self::writeRow((int)$form->id, $language, $key, $text, true, self::sourceFor($form, $key));
        }
    }

    public static function saveThankYou(CustomForm $form, string $language, string $text): void
    {
        $text = trim($text);
        $existing = self::tablesReady() ? FormMessageI18n::findOne([
            'form_id' => (int)$form->id,
            'language' => $language,
            'message_key' => 'author.thank_you',
        ]) : null;
        if ($existing && (string)$existing->text === $text) {
            return;
        }
        self::writeRow((int)$form->id, $language, 'author.thank_you', $text, true, (string)$form->thank_you_content);
    }

    /**
     * @param array<int|string, array<string, string>> $posted
     */
    public static function saveEmailPosted(string $language, array $posted): void
    {
        if (!self::tablesReady()) {
            return;
        }
        foreach ($posted as $templateId => $fields) {
            $templateId = (int)$templateId;
            if ($templateId < 1 || !is_array($fields)) {
                continue;
            }
            if (!FormEmailTemplate::findOne($templateId)) {
                continue;
            }
            $fields = [
                'subject' => trim((string)($fields['subject'] ?? '')),
                'header_html' => (string)($fields['header_html'] ?? ''),
                'body_html' => (string)($fields['body_html'] ?? ''),
                'footer_html' => (string)($fields['footer_html'] ?? ''),
            ];
            $existing = FormEmailTemplateI18n::findOne(['template_id' => $templateId, 'language' => $language]);
            if ($existing
                && (string)$existing->subject === $fields['subject']
                && (string)$existing->header_html === $fields['header_html']
                && (string)$existing->body_html === $fields['body_html']
                && (string)$existing->footer_html === $fields['footer_html']) {
                continue;
            }
            self::writeEmail($templateId, $language, $fields, true);
        }
    }

    public static function saveEmailPart(int $templateId, string $language, string $part, string $value): void
    {
        if (!self::tablesReady() || !in_array($part, ['subject', 'header_html', 'body_html', 'footer_html'], true)) {
            return;
        }
        if (!FormEmailTemplate::findOne($templateId)) {
            return;
        }
        $existing = FormEmailTemplateI18n::findOne(['template_id' => $templateId, 'language' => $language]);
        $fields = [
            'subject' => $existing ? (string)$existing->subject : '',
            'header_html' => $existing ? (string)$existing->header_html : '',
            'body_html' => $existing ? (string)$existing->body_html : '',
            'footer_html' => $existing ? (string)$existing->footer_html : '',
        ];
        $fields[$part] = $part === 'subject' ? trim($value) : $value;
        self::writeEmail($templateId, $language, $fields, true);
    }

    /**
     * Copy the site wording onto this form. Missing site lines are translated once and kept for every form.
     *
     * @return array{translated: int, skipped: int, failed: int}
     */
    public static function generate(CustomForm $form, string $source, string $target, ?object $translator = null): array
    {
        $stats = ['translated' => 0, 'skipped' => 0, 'failed' => 0];
        if (!self::tablesReady() || $target === '' || strcasecmp($source, $target) === 0) {
            return $stats;
        }
        $translator = $translator ?: self::translator();
        $siteRows = self::rowsFor(FormMessageI18n::SITE_FORM_ID, $target);
        $formRows = self::rowsFor((int)$form->id, $target);
        foreach (ParticipantMessageCatalog::entries() as $key => $entry) {
            $result = self::generateChrome(
                $form,
                $target,
                $key,
                (string)$entry['source'],
                $source,
                $translator,
                $siteRows[$key] ?? null,
                $formRows[$key] ?? null
            );
            $stats[$result]++;
        }
        foreach (self::authorSources($form) as $key => $meta) {
            $result = self::generateAuthor($form, $target, $key, (string)$meta['source'], $source, $translator, $formRows[$key] ?? null);
            $stats[$result]++;
        }
        $thank = trim((string)$form->thank_you_content);
        if ($thank !== '') {
            $result = self::generateAuthor($form, $target, 'author.thank_you', $thank, $source, $translator, $formRows['author.thank_you'] ?? null);
            $stats[$result]++;
            $stored = self::stored($form, $target, 'author.thank_you');
            if ($stored !== '') {
                $row = FormI18n::findOne(['form_id' => $form->id, 'language' => $target]) ?: new FormI18n([
                    'form_id' => $form->id,
                    'language' => $target,
                ]);
                $locked = isset($formRows['author.thank_you'])
                    && (int)$formRows['author.thank_you']->locked === 1
                    && trim((string)$formRows['author.thank_you']->text) !== '';
                if (!$locked || trim((string)$row->thank_you_content) === '') {
                    $row->thank_you_content = $stored;
                    $row->save(false);
                }
            }
        }
        foreach (self::emailTemplates($form) as $template) {
            $result = self::generateEmail($template, $target, $source, $translator);
            $stats[$result]++;
        }
        return $stats;
    }

    public static function messageCompleteness(CustomForm $form, string $language): int
    {
        if ($language === '' || !self::tablesReady()) {
            return 0;
        }
        $formMap = self::mapFor((int)$form->id, $language);
        $siteMap = self::mapFor(FormMessageI18n::SITE_FORM_ID, $language);
        $total = 0;
        $done = 0;
        foreach (ParticipantMessageCatalog::entries() as $key => $entry) {
            $total++;
            $text = trim($formMap[$key] ?? '');
            if ($text === '') {
                $text = trim($siteMap[$key] ?? '');
            }
            if ($text !== '') {
                $done++;
            }
        }
        foreach (self::authorSources($form) as $key => $meta) {
            if (trim((string)$meta['source']) === '') {
                continue;
            }
            $total++;
            if (trim($formMap[$key] ?? '') !== '') {
                $done++;
            }
        }
        if (trim((string)$form->thank_you_content) !== '') {
            $total++;
            $thank = trim($formMap['author.thank_you'] ?? '');
            if ($thank === '') {
                $row = FormI18n::findOne(['form_id' => $form->id, 'language' => $language]);
                $thank = $row ? trim((string)$row->thank_you_content) : '';
            }
            if ($thank !== '') {
                $done++;
            }
        }
        foreach (self::emailTemplates($form) as $template) {
            $total++;
            $row = FormEmailTemplateI18n::findOne(['template_id' => $template->id, 'language' => $language]);
            if ($row && (trim((string)$row->subject) !== '' || trim((string)$row->body_html) !== '')) {
                $done++;
            }
        }
        return $total > 0 ? (int)round(($done / $total) * 100) : 0;
    }

    /**
     * @return array<string, array{label: string, source: string, long: bool}>
     */
    public static function authorSources(CustomForm $form): array
    {
        $screen = trim((string)$form->getSetting('screen_out_message', ''));
        $consent = trim((string)$form->getSetting('not_consented_message', ''));
        $out = [];
        $add = static function (array &$out, string $key, string $label, string $source, bool $long) {
            if (trim($source) === '') {
                return;
            }
            $out[$key] = ['label' => $label, 'source' => $source, 'long' => $long];
        };
        $add($out, 'author.already_submitted', 'Already-submitted message', (string)$form->already_submitted_message, true);
        $add($out, 'author.already_submitted_button', 'Already-submitted button', (string)$form->already_submitted_button_label, false);
        $add($out, 'author.completion_button', 'Thank-you button', (string)$form->completion_button_label, false);
        $add($out, 'author.screen_out', 'Screen-out message', $screen, true);
        $add($out, 'author.not_consented', 'Not consented message', $consent, true);
        return $out;
    }

    /**
     * @return FormEmailTemplate[]
     */
    public static function emailTemplates(CustomForm $form): array
    {
        $ids = [];
        foreach (['invite_email_template_id', 'wave_email_template_id', 'reminder_email_template_id', 'completion_email_template_id'] as $setting) {
            $id = (int)$form->getSetting($setting, 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $actions = $form->getSetting('submit_actions', []);
        if (is_array($actions)) {
            foreach ($actions as $action) {
                if (is_array($action) && (int)($action['template_id'] ?? 0) > 0) {
                    $ids[(int)$action['template_id']] = (int)$action['template_id'];
                }
            }
        }
        if (!$ids) {
            return [];
        }
        return FormEmailTemplate::find()->where(['id' => array_values($ids)])->all();
    }

    public static function accountLanguage(?FormPanelMember $member, CustomForm $form): string
    {
        $raw = '';
        try {
            if ($member && $member->user) {
                $raw = (string)($member->user->language ?? '');
            }
        } catch (\Throwable $e) {
            $raw = '';
        }
        return TranslationService::normalizeLanguage($raw) ?: $form->getSourceLanguage();
    }

    public static function localizeTemplate(FormEmailTemplate $template, string $language): FormEmailTemplate
    {
        $language = TranslationService::normalizeLanguage($language) ?? '';
        if ($language === '' || !self::tablesReady() || (int)$template->id < 1) {
            return $template;
        }
        $row = FormEmailTemplateI18n::findOne(['template_id' => (int)$template->id, 'language' => $language]);
        if (!$row) {
            return $template;
        }
        $copy = clone $template;
        if (trim((string)$row->subject) !== '') {
            $copy->subject = (string)$row->subject;
        }
        if (trim((string)$row->header_html) !== '') {
            $copy->header_html = (string)$row->header_html;
        }
        if (trim((string)$row->body_html) !== '') {
            $copy->body_html = (string)$row->body_html;
        }
        if (trim((string)$row->footer_html) !== '') {
            $copy->footer_html = (string)$row->footer_html;
        }
        return $copy;
    }

    /**
     * @return array<string, string>
     */
    public static function textsFor(CustomForm $form, string $language): array
    {
        if (!self::tablesReady()) {
            return [];
        }
        return self::mapFor((int)$form->id, $language);
    }

    /**
     * @return array<string, string>
     */
    public static function siteTexts(string $language): array
    {
        if (!self::tablesReady()) {
            return [];
        }
        return self::mapFor(FormMessageI18n::SITE_FORM_ID, $language);
    }

    public static function tablesReady(): bool
    {
        try {
            return Yii::$app->db->schema->getTableSchema(FormMessageI18n::tableName(), true) !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<string, string>
     */
    private static function mapFor(int $formId, string $language): array
    {
        if ($language === '') {
            return [];
        }
        $rows = (new Query())
            ->select(['message_key', 'text'])
            ->from(FormMessageI18n::tableName())
            ->where(['form_id' => $formId, 'language' => $language])
            ->all();
        $map = [];
        foreach ($rows as $row) {
            $map[(string)$row['message_key']] = (string)$row['text'];
        }
        return $map;
    }

    private static function sourceFor(CustomForm $form, string $key): string
    {
        $entry = ParticipantMessageCatalog::entries()[$key] ?? null;
        if ($entry) {
            return (string)$entry['source'];
        }
        if ($key === 'author.thank_you') {
            return (string)$form->thank_you_content;
        }
        return (string)(self::authorSources($form)[$key]['source'] ?? '');
    }

    /**
     * @return array<string, FormMessageI18n>
     */
    private static function rowsFor(int $formId, string $language): array
    {
        $rows = FormMessageI18n::find()->where(['form_id' => $formId, 'language' => $language])->all();
        $byKey = [];
        foreach ($rows as $row) {
            $byKey[(string)$row->message_key] = $row;
        }
        return $byKey;
    }

    private static function generateChrome(
        CustomForm $form,
        string $target,
        string $key,
        string $sourceText,
        string $sourceLanguage,
        ?object $translator,
        ?FormMessageI18n $site,
        ?FormMessageI18n $formRow
    ): string {
        if ($formRow && (int)$formRow->locked === 1 && trim((string)$formRow->text) !== '') {
            return 'skipped';
        }
        $hash = self::hash($sourceText);
        $siteText = $site ? trim((string)$site->text) : '';
        if ($site && $siteText !== '' && (string)$site->source_hash === $hash) {
            self::writeRow((int)$form->id, $target, $key, $siteText, false, $sourceText);
            return 'skipped';
        }
        $translated = self::translate($translator, 'form_chrome', 'site', $key, $sourceText, $target, $sourceLanguage);
        if ($translated === null) {
            return 'failed';
        }
        self::writeRow(FormMessageI18n::SITE_FORM_ID, $target, $key, $translated, false, $sourceText);
        self::writeRow((int)$form->id, $target, $key, $translated, false, $sourceText);
        return 'translated';
    }

    private static function generateAuthor(
        CustomForm $form,
        string $target,
        string $key,
        string $sourceText,
        string $sourceLanguage,
        ?object $translator,
        ?FormMessageI18n $existing
    ): string {
        if (trim($sourceText) === '') {
            return 'skipped';
        }
        if ($existing && (int)$existing->locked === 1 && trim((string)$existing->text) !== '') {
            return 'skipped';
        }
        $hash = self::hash($sourceText);
        if ($existing && trim((string)$existing->text) !== '' && (string)$existing->source_hash === $hash) {
            return 'skipped';
        }
        $translated = self::translate($translator, 'form_author', (string)$form->id, $key, $sourceText, $target, $sourceLanguage);
        if ($translated === null) {
            return 'failed';
        }
        self::writeRow((int)$form->id, $target, $key, $translated, false, $sourceText);
        return 'translated';
    }

    private static function generateEmail(FormEmailTemplate $template, string $target, string $sourceLanguage, ?object $translator): string
    {
        $existing = FormEmailTemplateI18n::findOne(['template_id' => (int)$template->id, 'language' => $target]);
        if ($existing && (int)$existing->locked === 1) {
            return 'skipped';
        }
        $fields = [
            'subject' => (string)$template->subject,
            'header_html' => (string)$template->header_html,
            'body_html' => (string)$template->body_html,
            'footer_html' => (string)$template->footer_html,
        ];
        $translated = [];
        $any = false;
        foreach ($fields as $field => $text) {
            if (trim($text) === '') {
                $translated[$field] = '';
                continue;
            }
            $value = self::translate($translator, 'email_template', (string)$template->id, $field, $text, $target, $sourceLanguage);
            if ($value === null) {
                return 'failed';
            }
            $translated[$field] = $value;
            $any = true;
        }
        if (!$any) {
            return 'skipped';
        }
        self::writeEmail((int)$template->id, $target, $translated, false);
        return 'translated';
    }

    private static function translate(?object $translator, string $type, string $id, string $field, string $text, string $target, string $source): ?string
    {
        if (!$translator || !method_exists($translator, 'getTranslation')) {
            return null;
        }
        try {
            $translated = (string)$translator->getTranslation(
                $type,
                $id,
                $field,
                $text,
                $target,
                $source,
                'form',
                true,
                'thiscovery-forms'
            );
        } catch (\Throwable $e) {
            return null;
        }
        if ($translated === '') {
            return null;
        }
        return self::swapLeakedTokens($translated, $text);
    }

    private static function translator(): ?object
    {
        try {
            if (!Yii::$app->hasModule('thiscovery-translate') || !Yii::$app->getModule('thiscovery-translate')->getIsEnabled()) {
                return null;
            }
            if (!class_exists(\humhub\modules\thiscoveryTranslate\services\TranslationService::class)) {
                return null;
            }
            $service = new \humhub\modules\thiscoveryTranslate\services\TranslationService();
            return $service->isEnabled() ? $service : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Put {placeholders} back when a translator split the temporary %%0%% marker.
     */
    public static function swapLeakedTokens(string $text, string $source): string
    {
        if (!preg_match('/%\s*%/', $text)) {
            return $text;
        }
        preg_match_all('/\{[a-zA-Z_][a-zA-Z0-9_ ]*\}/', $source, $holders);
        $holders = $holders[0] ?? [];
        if ($holders === []) {
            return $text;
        }
        $text = preg_replace_callback('/%\s*%\s*(\d+)\s*%\s*%/', static function (array $match) use ($holders): string {
            $index = (int)$match[1];
            return $holders[$index] ?? $match[0];
        }, $text) ?? $text;
        $text = preg_replace_callback('/%(\d+)%%/', static function (array $match) use ($holders): string {
            $index = (int)$match[1];
            return $holders[$index] ?? $match[0];
        }, $text) ?? $text;
        $text = preg_replace('/(\{[a-zA-Z_][a-zA-Z0-9_ ]*\})%+/', '$1', $text) ?? $text;
        if (!str_contains($source, '%')) {
            $text = str_replace('%', '', $text);
        }
        return self::spacePlaceholders($text);
    }

    public static function spacePlaceholders(string $text): string
    {
        $text = preg_replace('/([\p{L}\p{M}])\{/u', '$1 {', $text) ?? $text;
        $text = preg_replace('/\}([\p{L}\p{M}])/u', '} $1', $text) ?? $text;
        $text = preg_replace('/:\{/', ': {', $text) ?? $text;
        return preg_replace('/[ \t]{2,}/', ' ', $text) ?? $text;
    }

    /**
     * Correct sentences already stored with a split %% marker.
     */
    public static function repairStoredPlaceholders(): int
    {
        if (!self::tablesReady()) {
            return 0;
        }
        $fixed = 0;
        $rows = FormMessageI18n::find()->where(['or', ['like', 'text', '%%'], ['like', 'text', '{']])->all();
        foreach ($rows as $row) {
            $source = self::sourceTextForStoredKey((int)$row->form_id, (string)$row->message_key);
            $next = self::swapLeakedTokens((string)$row->text, $source);
            $next = self::spacePlaceholders($next);
            if ($next === (string)$row->text) {
                continue;
            }
            $row->text = $next;
            $row->save(false);
            $fixed++;
        }
        return $fixed;
    }

    private static function sourceTextForStoredKey(int $formId, string $key): string
    {
        $entry = ParticipantMessageCatalog::entries()[$key] ?? null;
        if ($entry) {
            return (string)$entry['source'];
        }
        if ($formId < 1) {
            return '';
        }
        $form = CustomForm::findOne($formId);
        return $form ? self::sourceFor($form, $key) : '';
    }

    private static function writeRow(int $formId, string $language, string $key, string $text, bool $locked, string $source): void
    {
        if (!self::tablesReady() || $language === '' || $key === '') {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $existing = FormMessageI18n::findOne([
            'form_id' => $formId,
            'language' => $language,
            'message_key' => $key,
        ]);
        if ($text === '') {
            if ($existing && !$existing->isNewRecord) {
                $existing->delete();
            }
            return;
        }
        $row = $existing ?: new FormMessageI18n([
            'form_id' => $formId,
            'language' => $language,
            'message_key' => $key,
        ]);
        $row->text = $text;
        $row->locked = $locked ? 1 : 0;
        $row->source_hash = self::hash($source);
        $row->updated_at = $now;
        $row->save(false);
    }

    /**
     * @param array{subject?: string, header_html?: string, body_html?: string, footer_html?: string} $fields
     */
    private static function writeEmail(int $templateId, string $language, array $fields, bool $locked): void
    {
        if (!self::tablesReady()) {
            return;
        }
        $blank = trim((string)($fields['subject'] ?? '')) === ''
            && trim((string)($fields['header_html'] ?? '')) === ''
            && trim((string)($fields['body_html'] ?? '')) === ''
            && trim((string)($fields['footer_html'] ?? '')) === '';
        $existing = FormEmailTemplateI18n::findOne(['template_id' => $templateId, 'language' => $language]);
        if ($blank) {
            if ($existing) {
                $existing->delete();
            }
            return;
        }
        $row = $existing ?: new FormEmailTemplateI18n([
            'template_id' => $templateId,
            'language' => $language,
        ]);
        $row->subject = (string)($fields['subject'] ?? '');
        $row->header_html = (string)($fields['header_html'] ?? '');
        $row->body_html = (string)($fields['body_html'] ?? '');
        $row->footer_html = (string)($fields['footer_html'] ?? '');
        $row->locked = $locked ? 1 : 0;
        $row->updated_at = date('Y-m-d H:i:s');
        $row->save(false);
    }

    private static function hash(string $source): string
    {
        return hash('sha256', $source);
    }
}
