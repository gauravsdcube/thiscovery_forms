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
            'condition' => '',
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
                // Optional formula; the action only runs when it is true (LOG-11).
                'condition' => trim((string)($row['condition'] ?? '')),
                'when' => null,
            ];
            if ($action['condition'] !== '') {
                try {
                    $action['when'] = (new \humhub\modules\thiscoveryForms\services\formula\Parser())->parse($action['condition']);
                } catch (\humhub\modules\thiscoveryForms\services\formula\FormulaException $e) {
                    // Refused at save (conditionErrors). A stored one that no longer parses
                    // never runs, rather than running unconditionally.
                    $action['when'] = false;
                }
            }
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

    public static function actorKey(?FormAnswer $answer, ?FormPanelMember $member): string
    {
        if ($answer && !$answer->isNewRecord) {
            return 'answer:' . (int)$answer->id;
        }
        if ($member && !$member->isNewRecord) {
            return 'member:' . (int)$member->id;
        }
        $session = (string)(Yii::$app->session->id ?: 'none');
        return 'session:' . substr(hash('sha256', $session), 0, 32);
    }

    /**
     * Duplicate-check key for a fully anonymous form. It names neither the answer nor the member.
     */
    public static function anonymousActorKey(?FormPanelMember $member): string
    {
        if ($member && !$member->isNewRecord) {
            return 'anon:' . substr(hash('sha256', 'cf-anon-member:' . (int)$member->id), 0, 32);
        }
        $session = (string)(Yii::$app->session->id ?: 'none');
        return 'session:' . substr(hash('sha256', $session), 0, 32);
    }

    public static function sanitizeName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', trim($name)) ?? '';
        return substr($name, 0, 64);
    }

    /**
     * Why a list of actions cannot be saved: a condition that is not a valid formula.
     *
     * @param mixed $list
     * @return string[]
     */
    public static function conditionErrors($list): array
    {
        $errors = [];
        foreach (is_array($list) ? $list : [] as $row) {
            $condition = is_array($row) ? trim((string)($row['condition'] ?? '')) : '';
            if ($condition === '') {
                continue;
            }
            try {
                (new \humhub\modules\thiscoveryForms\services\formula\Parser())->parse($condition);
            } catch (\humhub\modules\thiscoveryForms\services\formula\FormulaException $e) {
                $errors[] = Yii::t('ThiscoveryFormsModule.base', 'Action condition “{condition}”: {error}', [
                    'condition' => $condition,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        return $errors;
    }

    /**
     * Whether an action's condition holds for these answers. No condition means always.
     *
     * @param array<int|string,mixed> $values
     */
    public static function conditionMet(CustomForm $form, array $action, array $values): bool
    {
        $when = $action['when'] ?? null;
        if ($when === null) {
            return true;
        }
        if (!is_array($when)) {
            return false;
        }
        return (new LogicEngine())->rulesMet(['when' => $when], $values, $form->fields);
    }

    /** When true, email actions are collected in $deferred instead of sent. */
    public bool $deferEmails = false;
    /** @var array<int, array{action: array, field_id: int}> */
    public array $deferred = [];

    private static function deferredKey(int $formId): string
    {
        return 'cf-deferred-emails-' . $formId;
    }

    /** Keep a guest's page-exit emails until the response is submitted (SEC-5). */
    public static function rememberDeferred(int $formId, array $deferred): void
    {
        if ($deferred === [] || !Yii::$app->has('session')) {
            return;
        }
        $key = self::deferredKey($formId);
        $all = Yii::$app->session->get($key, []);
        foreach ($deferred as $row) {
            // One entry per action and question: leaving a page twice queues it once.
            $all[md5(json_encode($row['action']) . ':' . $row['field_id'])] = $row;
        }
        Yii::$app->session->set($key, array_slice($all, -50, null, true));
    }

    /**
     * Send the emails a guest's pages queued, now that the response is complete. Each is
     * checked again against the final answers, and the usual caps and duplicate log apply.
     */
    public function sendDeferred(CustomForm $form, FormAnswer $answer, array $vars = [], ?FormPanelMember $member = null): void
    {
        if (!Yii::$app->has('session')) {
            return;
        }
        $key = self::deferredKey((int)$form->id);
        $rows = Yii::$app->session->get($key, []);
        Yii::$app->session->remove($key);
        $values = $answer->getValuesMap();
        foreach (is_array($rows) ? $rows : [] as $row) {
            $source = null;
            foreach ($form->fields as $field) {
                if ((int)$field->id === (int)($row['field_id'] ?? 0)) {
                    $source = $field;
                    break;
                }
            }
            $this->run($form, [$row['action'] ?? []], $values, $vars, $answer, $member, $source, false, true);
        }
    }

    public static function acceptsRunTrigger(string $trigger): bool
    {
        return in_array($trigger, ['field', 'page'], true);
    }

    /**
     * Names a respondent is allowed to post. Built-in mail tokens are never included.
     *
     * @return array<string, true>
     */
    public static function declaredVarNames(CustomForm $form): array
    {
        $names = [];
        foreach ($form->fields as $field) {
            $name = self::sanitizeName((string)$field->variable);
            if ($name !== '') {
                $names[$name] = true;
            }
        }
        $functions = self::normalizeFunctions($form->custom_functions ?? $form->getSetting('custom_functions', []));
        foreach ($functions as $fn) {
            $names[$fn['name']] = true;
        }
        foreach (self::builtinVarNames() as $reserved) {
            unset($names[$reserved]);
        }
        return $names;
    }

    /**
     * @return string[]
     */
    public static function builtinVarNames(): array
    {
        return [
            'firstName', 'lastName', 'first_name', 'last_name', 'email', 'displayName',
            'formTitle', 'formUrl', 'panelName', 'waveTitle', 'response_language',
        ];
    }

    /** The type a stored variable's value has: number, date or text. */
    public static function leafType(string $value): string
    {
        if ($value !== '' && preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/', $value)) {
            return 'number';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            return 'date';
        }
        return 'text';
    }

    /**
     * A template may be used only by forms in its own space, or anywhere when it is global
     * (V3-50): a template id from another space is ignored.
     */
    public static function templateAllowed(CustomForm $form, FormEmailTemplate $template): bool
    {
        if ($template->contentcontainer_id === null || (int)$template->contentcontainer_id === 0) {
            return true;
        }
        if ($form->isGlobal()) {
            return false;
        }
        return (int)$template->contentcontainer_id === (int)($form->content->contentcontainer_id ?? 0);
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $posted
     * @return array<string, string>
     */
    public static function filterPostedVars(CustomForm $form, array $base, array $posted): array
    {
        $vars = [];
        foreach ($base as $key => $value) {
            $vars[(string)$key] = is_scalar($value) ? (string)$value : '';
        }
        $allowed = self::declaredVarNames($form);
        foreach ($posted as $key => $value) {
            $name = self::sanitizeName((string)$key);
            if ($name === '' || !isset($allowed[$name])) {
                continue;
            }
            $vars[$name] = is_scalar($value) ? (string)$value : '';
        }
        return $vars;
    }

    /**
     * Per browser session (the configured 60 per 10 minutes) and five times that per address,
     * so people sharing a hospital NAT are not throttled; an IPv6 address counts by its /64,
     * so rotating addresses within one connection does not reset it. Counted under a mutex
     * so parallel calls cannot both read the same count (V3-40).
     */
    public static function tooManyRuns(int $formId, string $ip, int $limit = 60, int $window = 600): bool
    {
        $sessionId = Yii::$app->has('session') ? (string)Yii::$app->session->id : '';
        $keys = ['cf-run-actions-ip-' . $formId . '-' . hash('sha256', self::networkOf($ip)) => $limit * 5];
        if ($sessionId !== '') {
            $keys['cf-run-actions-s-' . $formId . '-' . hash('sha256', $sessionId)] = $limit;
        }
        return self::countAndCheck($keys, $window);
    }

    /**
     * Action emails are capped per recipient (3 a day per form) and per network (20 an hour),
     * so the form cannot be used to send mail to arbitrary addresses (V3-40, SEC-5).
     */
    public static function emailAllowed(int $formId, string $to, string $ip): bool
    {
        return !self::countAndCheck([
            'cf-action-mail-to-' . $formId . '-' . hash('sha256', strtolower(trim($to))) => 3,
        ], 86400) && !self::countAndCheck([
            'cf-action-mail-ip-' . $formId . '-' . hash('sha256', self::networkOf($ip)) => 20,
        ], 3600);
    }

    /** An IPv4 address, or the /64 prefix of an IPv6 one. */
    public static function networkOf(string $ip): string
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return $ip;
        }
        if (strlen($packed) === 16) {
            return bin2hex(substr($packed, 0, 8)) . '::/64';
        }
        return $ip;
    }

    /**
     * Increment every counter; true when any is now over its limit.
     *
     * @param array<string,int> $limits cache key => limit
     */
    private static function countAndCheck(array $limits, int $window): bool
    {
        $mutex = Yii::$app->has('mutex') ? Yii::$app->mutex : null;
        $lock = 'cf-rate-' . md5(implode('|', array_keys($limits)));
        $locked = $mutex ? $mutex->acquire($lock, 2) : false;
        try {
            $over = false;
            foreach ($limits as $key => $limit) {
                $count = (int)Yii::$app->cache->get($key);
                if ($count >= $limit) {
                    $over = true;
                    continue;
                }
                Yii::$app->cache->set($key, $count + 1, $window);
            }
            return $over;
        } finally {
            if ($locked) {
                $mutex->release($lock);
            }
        }
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
        bool $preview = false,
        bool $sideEffects = true
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
        $written = [];
        $emails = new EmailTemplateService();
        $pipe = new VariableSubstitutor();

        foreach ($actions as $action) {
            $fn = $action['fn'];
            if (!self::conditionMet($form, $action, $values)) {
                continue;
            }
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
                    $vars[$name] = $this->evaluateStoredValue($form, $values, $resolved, $rawValue, $vars);
                    $written[$name] = $vars[$name];
                }
                continue;
            }

            if ($fn === self::FN_SEND_EMAIL) {
                // Emails go at page exit and on submit, never while an answer is still being
                // changed (LOG-11).
                if ($preview || !$sideEffects) {
                    continue;
                }
                if ($this->deferEmails) {
                    // A guest's page-exit email waits for a real submission (SEC-5).
                    $this->deferred[] = ['action' => $action, 'field_id' => $sourceField ? (int)$sourceField->id : 0];
                    continue;
                }
                $this->sendEmail($emails, $form, $action['template_id'], $values, $vars, $answer, $member, $sourceField);
            }
        }

        if ($answer) {
            $this->storeActionVars($answer, $vars, $written ?? []);
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
        if (!$template || !self::templateAllowed($form, $template)) {
            return;
        }
        $anonymous = EmailTemplateService::isAnonymousForm($form);
        $actorKey = $anonymous ? self::anonymousActorKey($member) : self::actorKey($answer, $member);
        $where = [
            'form_id' => $form->id,
            'field_id' => $sourceField->id ?? null,
            'template_id' => $templateId,
            'actor_key' => $actorKey,
        ];
        if (!$anonymous && $answer && !$answer->isNewRecord) {
            $where['answer_id'] = (int)$answer->id;
        }
        if ($emails->hasSent(FormEmailSend::KIND_ACTION, $where)) {
            return;
        }
        $to = $emails->emailFromAnswer($form, $answer, $member, $values, false);
        if ($to === '') {
            return;
        }
        if (!self::emailAllowed((int)$form->id, $to, (string)(Yii::$app->request->userIP ?? ''))) {
            Yii::warning('Thiscovery Forms action email skipped: send limit reached for form #' . (int)$form->id, 'thiscovery-forms');
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
                'answer_id' => $anonymous ? null : ($answer->id ?? null),
                'field_id' => $sourceField->id ?? null,
                'template_id' => $templateId,
                'kind' => FormEmailSend::KIND_ACTION,
                'actor_key' => $actorKey,
                'anonymous' => $anonymous,
            ]
        );
    }

    /**
     * A formula is calculated. Other text is stored as written.
     *
     * @param array<int|string,mixed> $values
     */
    private function evaluateStoredValue(CustomForm $form, array $values, string $resolved, string $raw = '', array $vars = []): string
    {
        // Answers are referenced, not pasted into the formula text: {{answer:x}} becomes [x] and
        // {{var:x}} becomes [var:x] before parsing, so a typed answer can never be read as
        // formula syntax (V3-38). Text that is not a formula is stored as resolved.
        $formula = $raw !== '' ? $raw : $resolved;
        $formula = preg_replace('/\{\{\s*(?:answer|field):\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/', '[$1]', $formula) ?? $formula;
        $formula = preg_replace('/\{\{\s*var:\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/', '[var:$1]', $formula) ?? $formula;
        foreach ($vars as $key => $value) {
            $values['var:' . $key] = $value;
        }
        try {
            $tree = (new \humhub\modules\thiscoveryForms\services\formula\Parser())->parse($formula);
            $values['__loops'] = array_keys((new LoopService())->loopFieldIds($form));
            $context = \humhub\modules\thiscoveryForms\services\formula\Context::fromValues($values, array_values($form->fields));
            $value = (new \humhub\modules\thiscoveryForms\services\formula\Evaluator($context))->evaluate($tree);
            if (!$value->isEmpty()) {
                return (string)$value->data;
            }
        } catch (\Throwable $e) {
            return $resolved;
        }
        return $resolved;
    }

    /**
     * @param array<string,string> $vars
     * @param array<string,string> $written
     */
    private function storeActionVars(FormAnswer $answer, array $vars, array $written): void
    {
        $schema = $answer::getTableSchema();
        $plain = $vars;
        $formula = [];
        if ($schema && isset($schema->columns['variables_json'])) {
            foreach ($written as $name => $value) {
                unset($plain[$name]);
                // Stored with its type (V3-54): it was always "text".
                $formula[$name] = ['t' => self::leafType((string)$value), 'v' => (string)$value];
            }
        }
        $columns = ['updated_at'];
        $answer->setVars($plain);
        $columns[] = 'vars_json';
        if ($formula) {
            $existing = json_decode((string)$answer->variables_json, true);
            if (!is_array($existing)) {
                $existing = [];
            }
            foreach ($formula as $name => $leaf) {
                $existing[$name] = $leaf;
            }
            $answer->variables_json = json_encode($existing, JSON_UNESCAPED_UNICODE);
            $columns[] = 'variables_json';
        }
        if (!$answer->isNewRecord) {
            $answer->save(false, $columns);
        }
    }
}
