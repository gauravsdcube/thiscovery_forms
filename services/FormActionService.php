<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormEmailSend;
use humhub\modules\thiscoveryForms\models\FormEmailTemplate;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use Yii;

/**
 * Standard and custom actions that run when a field, page, or form is completed.
 */
class FormActionService
{
    public const FN_NONE = 'none';
    public const FN_SEND_EMAIL = 'send_email';
    public const FN_SET_VARIABLE = 'set_variable';
    public const FN_GOTO_PAGE = 'goto_page';
    public const FN_GOTO_END = 'goto_end';
    public const FN_CUSTOM = 'custom';

    public static function functionLabels(): array
    {
        return [
            self::FN_NONE => Yii::t('ThiscoveryFormsModule.base', 'None'),
            self::FN_SEND_EMAIL => Yii::t('ThiscoveryFormsModule.base', 'Send email'),
            self::FN_SET_VARIABLE => Yii::t('ThiscoveryFormsModule.base', 'Set variable'),
            self::FN_GOTO_PAGE => Yii::t('ThiscoveryFormsModule.base', 'Go to page'),
            self::FN_GOTO_END => Yii::t('ThiscoveryFormsModule.base', 'Go to end'),
            self::FN_CUSTOM => Yii::t('ThiscoveryFormsModule.base', 'Custom function'),
        ];
    }

    public static function emptyAction(): array
    {
        return [
            'fn' => self::FN_NONE,
            'template_id' => 0,
            'page_key' => '',
            'name' => '',
            'value' => '',
        ];
    }

    /**
     * @param mixed $list
     * @return array<int, array{fn:string,template_id:int,page_key:string,name:string,value:string}>
     */
    public static function normalizeList($list): array
    {
        if (!is_array($list)) {
            return [];
        }
        $out = [];
        $allowed = array_keys(self::functionLabels());
        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }
            $fn = (string)($row['fn'] ?? '');
            if ($fn === '' || $fn === self::FN_NONE || !in_array($fn, $allowed, true)) {
                continue;
            }
            $action = [
                'fn' => $fn,
                'template_id' => (int)($row['template_id'] ?? 0),
                'page_key' => trim((string)($row['page_key'] ?? '')),
                'name' => self::sanitizeName((string)($row['name'] ?? '')),
                'value' => (string)($row['value'] ?? ''),
            ];
            if ($fn === self::FN_SEND_EMAIL && $action['template_id'] < 1) {
                continue;
            }
            if ($fn === self::FN_GOTO_PAGE && $action['page_key'] === '') {
                continue;
            }
            if (in_array($fn, [self::FN_SET_VARIABLE, self::FN_CUSTOM], true) && $action['name'] === '') {
                continue;
            }
            $out[] = $action;
        }
        return $out;
    }

    /**
     * @param mixed $list
     * @return array<int, array{name:string,value:string}>
     */
    public static function normalizeFunctions($list): array
    {
        if (!is_array($list)) {
            return [];
        }
        $out = [];
        $seen = [];
        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = self::sanitizeName((string)($row['name'] ?? ''));
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $out[] = [
                'name' => $name,
                'value' => (string)($row['value'] ?? ''),
            ];
        }
        return $out;
    }

    public static function sanitizeName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', trim($name)) ?? '';
        return substr($name, 0, 64);
    }

    /**
     * @param array<int,array> $actions
     * @param array<int|string,mixed> $values
     * @param array<string,string> $vars
     * @return array{vars: array<string,string>, gotoPageKey: string, gotoEnd: bool}
     */
    public function run(
        CustomForm $form,
        array $actions,
        array $values,
        array $vars = [],
        ?FormAnswer $answer = null,
        ?FormPanelMember $member = null,
        ?FormField $sourceField = null,
        bool $preview = false
    ): array {
        $actions = self::normalizeList($actions);
        $functions = self::normalizeFunctions($form->custom_functions ?? $form->getSetting('custom_functions', []));
        $fnMap = [];
        foreach ($functions as $fn) {
            $fnMap[$fn['name']] = $fn['value'];
            if (!isset($vars[$fn['name']]) || $vars[$fn['name']] === '') {
                $vars[$fn['name']] = $fn['value'];
            }
        }

        $gotoPageKey = '';
        $gotoEnd = false;
        $emails = new EmailTemplateService();
        $pipe = new VariableSubstitutor();

        foreach ($actions as $action) {
            $fn = $action['fn'];
            if ($fn === self::FN_GOTO_PAGE) {
                $gotoPageKey = $action['page_key'];
                $gotoEnd = false;
                continue;
            }
            if ($fn === self::FN_GOTO_END) {
                $gotoEnd = true;
                $gotoPageKey = '';
                continue;
            }

            $name = $action['name'];
            $rawValue = $action['value'];
            if ($fn === self::FN_CUSTOM && $rawValue === '' && $name !== '' && isset($fnMap[$name])) {
                $rawValue = $fnMap[$name];
            }
            $resolved = $this->resolveValue($pipe, $emails, $form, $member, $values, $vars, $rawValue);

            if ($fn === self::FN_SET_VARIABLE || $fn === self::FN_CUSTOM) {
                if ($name !== '') {
                    $vars[$name] = $resolved;
                }
                continue;
            }

            if ($fn === self::FN_SEND_EMAIL) {
                if ($preview) {
                    continue;
                }
                $this->sendEmail($emails, $form, $action['template_id'], $values, $vars, $answer, $member, $sourceField);
            }
        }

        if ($answer) {
            $answer->setVars($vars);
            if (!$answer->isNewRecord) {
                $answer->save(false, ['vars_json', 'updated_at']);
            }
        }

        return [
            'vars' => $vars,
            'gotoPageKey' => $gotoPageKey,
            'gotoEnd' => $gotoEnd,
        ];
    }

    /**
     * @param array<int|string,mixed> $values
     * @param array<string,string> $vars
     */
    protected function resolveValue(
        VariableSubstitutor $pipe,
        EmailTemplateService $emails,
        CustomForm $form,
        ?FormPanelMember $member,
        array $values,
        array $vars,
        string $raw
    ): string {
        if ($raw === '') {
            return '';
        }
        $user = Yii::$app->user->isGuest ? null : Yii::$app->user->identity;
        $text = $pipe->substitutePlain($raw, $user, $form, $values, $form->fields, $vars);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $map = $emails->varsFor($form, $member, null, null, $vars);
        foreach ($vars as $key => $value) {
            $map[$key] = (string)$value;
            $map['var.' . $key] = (string)$value;
        }
        return $emails->replaceVars($text, $map);
    }

    /**
     * @param array<int|string,mixed> $values
     * @param array<string,string> $vars
     */
    protected function sendEmail(
        EmailTemplateService $emails,
        CustomForm $form,
        int $templateId,
        array $values,
        array $vars,
        ?FormAnswer $answer,
        ?FormPanelMember $member,
        ?FormField $sourceField
    ): void {
        $template = FormEmailTemplate::findOne($templateId);
        if (!$template) {
            return;
        }
        $where = [
            'form_id' => $form->id,
            'answer_id' => $answer->id ?? null,
            'field_id' => $sourceField->id ?? null,
            'template_id' => $templateId,
        ];
        if ($emails->hasSent(FormEmailSend::KIND_ACTION, $where)) {
            return;
        }
        $to = $emails->emailFromAnswer($form, $answer, $member, $values, false);
        if ($to === '') {
            return;
        }
        $extra = $vars;
        foreach ($vars as $key => $value) {
            $extra['var.' . $key] = (string)$value;
        }
        $emails->sendTemplate(
            $template,
            $to,
            $emails->varsFor($form, $member, $answer?->wave, null, $extra),
            [
                'form_id' => $form->id,
                'member_id' => $member->id ?? null,
                'answer_id' => $answer->id ?? null,
                'field_id' => $sourceField->id ?? null,
                'template_id' => $templateId,
                'kind' => FormEmailSend::KIND_ACTION,
            ]
        );
    }
}
