<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use Yii;

/**
 * Per-form answers-CSV column exclusions and PII scrub flag (settings_json.export).
 */
class ExportSettings
{
    public const KEY_ANSWER_ID = 'meta.answer_id';
    public const KEY_STATUS = 'meta.status';
    public const KEY_USER = 'meta.user';
    public const KEY_SUBMITTED_AT = 'meta.submitted_at';
    public const KEY_UPDATED_AT = 'meta.updated_at';
    public const KEY_QUALITY_SCORE = 'meta.quality_score';
    public const KEY_INTEGRITY_STATUS = 'meta.integrity_status';
    public const KEY_ANALYSIS_STATUS = 'meta.analysis_status';
    public const KEY_QUALITY_FLAGS = 'meta.quality_flags';
    public const KEY_WAVE = 'meta.wave';
    public const KEY_EQ5D_PROFILE = 'meta.eq5d_profile';
    public const KEY_EQ5D_VAS = 'meta.eq5d_vas';
    public const KEY_ROUND = 'meta.round';
    public const KEY_WEIGHT = 'meta.weight';

    public const GROUP_META = 'meta';
    public const GROUP_QUESTION = 'question';

    /**
     * @return array{exclude_columns: string[], pii_scrub: bool}
     */
    public static function get(CustomForm $form): array
    {
        $export = $form->getSetting('export', []);
        if (!is_array($export)) {
            $export = [];
        }
        $exclude = $export['exclude_columns'] ?? [];
        if (!is_array($exclude)) {
            $exclude = [];
        }
        $clean = [];
        foreach ($exclude as $key) {
            $key = trim((string)$key);
            if ($key !== '') {
                $clean[] = $key;
            }
        }

        return [
            'exclude_columns' => array_values(array_unique($clean)),
            'pii_scrub' => !empty($export['pii_scrub']),
        ];
    }

    public static function isPiiScrub(CustomForm $form): bool
    {
        return self::get($form)['pii_scrub'];
    }

    /**
     * @return string[]
     */
    public static function excludeColumns(CustomForm $form): array
    {
        return self::get($form)['exclude_columns'];
    }

    /**
     * @param string[] $exclude
     */
    public static function persist(CustomForm $form, array $exclude, bool $piiScrub): void
    {
        $clean = [];
        foreach ($exclude as $key) {
            $key = trim((string)$key);
            if ($key !== '') {
                $clean[] = $key;
            }
        }
        $form->setSetting('export', [
            'exclude_columns' => array_values(array_unique($clean)),
            'pii_scrub' => $piiScrub,
        ]);
    }

    public static function persistFromRequest(CustomForm $form): void
    {
        $request = Yii::$app->request;
        if (!$request->isPost || $request->post('export_known_columns') === null) {
            return;
        }
        $known = $request->post('export_known_columns', []);
        $include = $request->post('export_include', []);
        if (!is_array($known)) {
            $known = [];
        }
        if (!is_array($include)) {
            $include = [];
        }
        $known = array_map('strval', $known);
        $include = array_map('strval', $include);
        $exclude = array_values(array_diff($known, $include));
        self::persist($form, $exclude, !empty($request->post('export_pii_scrub')));
    }

    public static function downloadFilename(CustomForm $form): string
    {
        $suffix = self::isPiiScrub($form) ? '-scrubbed' : '';
        return 'form-' . $form->id . '-' . date('Ymd-His') . $suffix . '.csv';
    }

    public static function fieldColumnKey(FormField $field): string
    {
        $variable = trim((string)$field->variable);
        if ($variable !== '') {
            return 'var.' . $variable;
        }
        return 'field.' . (int)$field->id;
    }

    public static function commentColumnKey(FormField $field): string
    {
        return self::fieldColumnKey($field) . '.comment';
    }

    /**
     * Identity / PII columns dropped when Scrub PII is on, even if left ticked.
     */
    public static function fieldDropsWhenScrub(FormField $field): bool
    {
        if ($field->type === FormField::TYPE_EMAIL) {
            return true;
        }
        if ($field->type === FormField::TYPE_RESPONDENT_META) {
            $key = $field->getRespondentMetaKey();
            return $key === RespondentMetaService::KEY_IP || $key === RespondentMetaService::KEY_USER_AGENT;
        }
        if ($field->type === FormField::TYPE_PANEL_ATTR && self::isPanelIdentityKey($field->getPanelAttrKey())) {
            return true;
        }
        return $field->isContainsPii();
    }

    public static function isPanelIdentityKey(string $key): bool
    {
        return in_array($key, [
            PanelFieldService::KEY_FIRST,
            PanelFieldService::KEY_LAST,
            PanelFieldService::KEY_EMAIL,
            PanelFieldService::KEY_DISPLAY,
        ], true);
    }

    /**
     * Full column catalogue for this form (before exclude / scrub filters).
     *
     * @param FormField[]|null $fields
     * @return array<int, array{key:string,header:string,group:string,lock:bool,field:?FormField,comment:bool}>
     */
    public static function catalogue(CustomForm $form, ?array $fields = null, string $headerMode = ExportService::HEADER_LABEL): array
    {
        $fields = $fields ?? array_values(array_filter($form->fields, static fn($f) => $f->collectsAnswer()));
        $cols = [];

        $pushMeta = static function (string $key, string $header, bool $lock = false) use (&$cols) {
            $cols[] = [
                'key' => $key,
                'header' => $header,
                'group' => self::GROUP_META,
                'lock' => $lock,
                'field' => null,
                'comment' => false,
            ];
        };

        $pushMeta(self::KEY_ANSWER_ID, Yii::t('ThiscoveryFormsModule.base', 'Answer ID'));
        $pushMeta(self::KEY_STATUS, Yii::t('ThiscoveryFormsModule.base', 'Status'));
        $pushMeta(self::KEY_USER, Yii::t('ThiscoveryFormsModule.base', 'User'), true);
        $pushMeta(self::KEY_SUBMITTED_AT, Yii::t('ThiscoveryFormsModule.base', 'Submitted at'));
        $pushMeta(self::KEY_UPDATED_AT, Yii::t('ThiscoveryFormsModule.base', 'Updated at'));
        $pushMeta(self::KEY_QUALITY_SCORE, Yii::t('ThiscoveryFormsModule.base', 'Quality score'));
        $pushMeta(self::KEY_INTEGRITY_STATUS, Yii::t('ThiscoveryFormsModule.base', 'Integrity status'));
        $pushMeta(self::KEY_ANALYSIS_STATUS, Yii::t('ThiscoveryFormsModule.base', 'Analysis status'));
        $pushMeta(self::KEY_QUALITY_FLAGS, Yii::t('ThiscoveryFormsModule.base', 'Quality flags'));
        if ($form->usesWaves()) {
            $pushMeta(self::KEY_WAVE, Yii::t('ThiscoveryFormsModule.base', 'Wave'));
        }
        if ($form->isEq5d()) {
            $pushMeta(self::KEY_EQ5D_PROFILE, Yii::t('ThiscoveryFormsModule.base', 'Health profile'));
            $pushMeta(self::KEY_EQ5D_VAS, Yii::t('ThiscoveryFormsModule.base', 'VAS (blank = 999)'));
        }
        if ($form->isConsensus()) {
            $pushMeta(self::KEY_ROUND, Yii::t('ThiscoveryFormsModule.base', 'Round'));
            $pushMeta(self::KEY_WEIGHT, Yii::t('ThiscoveryFormsModule.base', 'Weight'));
        }

        $svc = new ExportService();
        foreach ($fields as $field) {
            if (!$field->collectsAnswer()) {
                continue;
            }
            $lock = self::fieldDropsWhenScrub($field);
            $cols[] = [
                'key' => self::fieldColumnKey($field),
                'header' => $svc->fieldHeader($field, $headerMode),
                'group' => self::GROUP_QUESTION,
                'lock' => $lock,
                'field' => $field,
                'comment' => false,
            ];
            if ($field->supportsJustification()) {
                $cols[] = [
                    'key' => self::commentColumnKey($field),
                    'header' => $svc->fieldHeader($field, $headerMode) . ' — ' . Yii::t('ThiscoveryFormsModule.base', 'Comment'),
                    'group' => self::GROUP_QUESTION,
                    'lock' => false,
                    'field' => $field,
                    'comment' => true,
                ];
            }
        }

        return $cols;
    }

    /**
     * Columns actually written to the answers CSV.
     *
     * @param FormField[]|null $fields
     * @return array<int, array{key:string,header:string,group:string,lock:bool,field:?FormField,comment:bool}>
     */
    public static function resolvedColumns(CustomForm $form, ?array $fields = null, string $headerMode = ExportService::HEADER_LABEL): array
    {
        $exclude = array_fill_keys(self::excludeColumns($form), true);
        $scrub = self::isPiiScrub($form);
        $out = [];
        foreach (self::catalogue($form, $fields, $headerMode) as $col) {
            if (isset($exclude[$col['key']])) {
                continue;
            }
            if ($scrub && $col['lock']) {
                continue;
            }
            $out[] = $col;
        }
        return $out;
    }
}
