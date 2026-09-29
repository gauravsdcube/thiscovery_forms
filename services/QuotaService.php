<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\thiscoveryForms\Module;
use Yii;
use yii\db\Query;

/**
 * Exact quota counts. The counter row is locked in the submit transaction.
 * Reservations are off unless the quota turns them on. quota.full is not sent:
 * webhooks are deferred. The audit row is still written when the target is reached.
 */
class QuotaService
{
    /** @var array<string,mixed>|null */
    public static $armBlock = null;

    public static function active(CustomForm $form): bool
    {
        return Module::quotasEnabled() && self::formEnabled($form) && self::tablesReady();
    }

    public static function formEnabled(CustomForm $form): bool
    {
        $value = (string)$form->getSetting('quotas_enabled', '0');
        return in_array($value, ['1', 'true', 'on'], true);
    }

    public static function tablesReady(): bool
    {
        return Yii::$app->db->schema->getTableSchema('{{%custom_form_quota}}', true) !== null
            && Yii::$app->db->schema->getTableSchema('{{%custom_form_quota_counter}}', true) !== null;
    }

    public function saveFormSettings(CustomForm $form, array $posted): void
    {
        $enabled = $posted['enabled'] ?? '0';
        $form->setSetting('quotas_enabled', in_array((string)$enabled, ['1', 'true', 'on'], true) ? '1' : '0');
        $arm = $posted['assign_arm'] ?? '0';
        $form->setSetting('quota_assign_arm', in_array((string)$arm, ['1', 'true', 'on'], true) ? '1' : '0');
        $notify = trim((string)($posted['full_email'] ?? ''));
        if ($notify !== '' && !str_ends_with(strtolower($notify), '@example.test')) {
            $notify = '';
        }
        $form->setSetting('quota_full_email', $notify);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function saveQuota(CustomForm $form, ?int $id, array $payload): ?array
    {
        if (!self::tablesReady()) {
            return null;
        }
        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') {
            return null;
        }
        $now = gmdate('Y-m-d H:i:s');
        $row = [
            'form_id' => (int)$form->id,
            'wave_id' => (int)($payload['wave_id'] ?? 0) > 0 ? (int)$payload['wave_id'] : null,
            'name' => $name,
            'target' => max(0, (int)($payload['target'] ?? 0)),
            'parent_id' => (int)($payload['parent_id'] ?? 0) > 0 ? (int)$payload['parent_id'] : null,
            'rules_json' => json_encode($this->normalizeRules($payload['rules'] ?? []), JSON_UNESCAPED_UNICODE),
            'count_policy' => (string)($payload['count_policy'] ?? 'complete') === 'complete_excluding_integrity'
                ? 'complete_excluding_integrity' : 'complete',
            'reserve' => !empty($payload['reserve']) ? 1 : 0,
            'reserve_minutes' => max(1, (int)($payload['reserve_minutes'] ?? 60)),
            'action' => $this->action((string)($payload['action'] ?? 'end')),
            'action_message' => trim((string)($payload['action_message'] ?? '')),
            'action_url' => trim((string)($payload['action_url'] ?? '')),
            'action_page_key' => trim((string)($payload['action_page_key'] ?? '')),
            'check_page_key' => trim((string)($payload['check_page_key'] ?? '')),
            'status' => (string)($payload['status'] ?? 'open') === 'closed' ? 'closed' : 'open',
            'edition_id' => $form->current_edition_id ? (int)$form->current_edition_id : null,
            'sort_order' => (int)($payload['sort_order'] ?? 0),
        ];
        $db = Yii::$app->db;
        if ($id) {
            $existing = $this->quota($id, (int)$form->id);
            if (!$existing) {
                return null;
            }
            if ((int)$existing['target'] !== (int)$row['target']) {
                $reason = trim((string)($payload['reason'] ?? ''));
                if ($reason === '') {
                    return null;
                }
                $this->audit($id, [
                    'old_target' => (int)$existing['target'],
                    'new_target' => (int)$row['target'],
                    'reason' => $reason,
                ], isset($payload['actor_id']) ? (int)$payload['actor_id'] : null);
            }
            $db->createCommand()->update('{{%custom_form_quota}}', $row, ['id' => $id, 'form_id' => (int)$form->id])->execute();
            return $this->quota($id, (int)$form->id);
        }
        $row['created_at'] = $now;
        $db->createCommand()->insert('{{%custom_form_quota}}', $row)->execute();
        $newId = (int)$db->getLastInsertID();
        $db->createCommand()->insert('{{%custom_form_quota_counter}}', [
            'quota_id' => $newId,
            'accepted' => 0,
            'reserved' => 0,
            'reconciled_at' => null,
        ])->execute();
        return $this->quota($newId, (int)$form->id);
    }

    public function setStatus(CustomForm $form, int $id, string $status): bool
    {
        $quota = $this->quota($id, (int)$form->id);
        if (!$quota) {
            return false;
        }
        Yii::$app->db->createCommand()->update('{{%custom_form_quota}}', [
            'status' => $status === 'closed' ? 'closed' : 'open',
        ], ['id' => $id])->execute();
        return true;
    }

    public function addHost(CustomForm $form, string $host): void
    {
        $host = strtolower(trim($host));
        if ($host === '' || !self::tablesReady()) {
            return;
        }
        $exists = (new Query())->from('{{%custom_form_quota_allowhost}}')->where([
            'form_id' => (int)$form->id,
            'host' => $host,
        ])->exists();
        if (!$exists) {
            Yii::$app->db->createCommand()->insert('{{%custom_form_quota_allowhost}}', [
                'form_id' => (int)$form->id,
                'host' => $host,
            ])->execute();
        }
    }

    /**
     * @return array<int, string>
     */
    public function authoringErrors(CustomForm $form): array
    {
        if (!self::formEnabled($form) || !self::tablesReady()) {
            return [];
        }
        $errors = [];
        $fields = $form->getFields()->all();
        $pages = array_fill_keys(array_map(
            static fn($page) => (string)$page['pageKey'],
            (new FormPager())->buildPages($fields)['pages']
        ), true);
        $arms = [];
        foreach ((new RandomisationService())->config($form)['arms'] as $arm) {
            $arms[(string)$arm['code']] = true;
        }
        $blocks = (new RandomisationService())->blocks($fields);
        $hosts = $this->hosts((int)$form->id);
        $byId = [];
        foreach ($this->quotas((int)$form->id) as $quota) {
            $byId[(int)$quota['id']] = $quota;
        }
        foreach ($byId as $quota) {
            $label = (string)$quota['name'];
            foreach ($this->leaves($this->rulesOf($quota)) as $leaf) {
                $source = (string)($leaf['source'] ?? 'field');
                $key = (string)($leaf['fieldKey'] ?? '');
                if ($source === 'panel' || str_starts_with($key, 'panel.')) {
                    if ($form->hidesIdentityFromManagers()) {
                        $errors[] = Yii::t('ThiscoveryFormsModule.base', 'Quota “{name}” cannot use a panel attribute on a fully anonymous form.', [
                            'name' => $label,
                        ]);
                    }
                    continue;
                }
                if ($source === 'arm' || $key === 'arm') {
                    $code = trim((string)($leaf['value'] ?? ''));
                    if ($code !== '' && !isset($arms[$code])) {
                        $errors[] = Yii::t('ThiscoveryFormsModule.base', 'Quota “{name}” uses arm “{code}”, which is not on this form.', [
                            'name' => $label,
                            'code' => $code,
                        ]);
                    }
                    continue;
                }
                if ($key !== '' && !$this->fieldByKey($fields, $key)) {
                    $errors[] = Yii::t('ThiscoveryFormsModule.base', 'Quota “{name}” uses question “{key}”, which is not on this form.', [
                        'name' => $label,
                        'key' => $key,
                    ]);
                }
            }
            $check = trim((string)($quota['check_page_key'] ?? ''));
            if ($check !== '' && !isset($pages[$check])) {
                $errors[] = Yii::t('ThiscoveryFormsModule.base', 'Quota “{name}” checks a page that is not on this form.', [
                    'name' => $label,
                ]);
            }
            if ((string)$quota['action'] === 'redirect') {
                $urlError = $this->redirectError((string)$quota['action_url'], $hosts);
                if ($urlError !== null) {
                    $errors[] = Yii::t('ThiscoveryFormsModule.base', 'Quota “{name}” redirect was refused: {reason}', [
                        'name' => $label,
                        'reason' => $urlError,
                    ]);
                }
            }
            if ((string)$quota['action'] === 'goto') {
                $target = trim((string)$quota['action_page_key']);
                if ($target === '' || !isset($pages[$target])) {
                    $errors[] = Yii::t('ThiscoveryFormsModule.base', 'Quota “{name}” goes to a page that is not on this form.', [
                        'name' => $label,
                    ]);
                }
                foreach ($blocks as $block) {
                    if (!empty($block['open'])) {
                        continue;
                    }
                    $inside = in_array($check !== '' ? $check : 'start', $block['pages'], true);
                    if ($inside && !in_array($target, $block['pages'], true)) {
                        $errors[] = Yii::t('ThiscoveryFormsModule.base', 'Quota “{name}” goes to “{target}”, which is outside block “{key}”.', [
                            'name' => $label,
                            'target' => $target,
                            'key' => $block['key'],
                        ]);
                    }
                }
            }
            if ($this->parentCycle($quota, $byId)) {
                $errors[] = Yii::t('ThiscoveryFormsModule.base', 'Quota “{name}” is nested inside itself.', [
                    'name' => $label,
                ]);
            }
        }
        return $errors;
    }

    /**
     * @param array<string,mixed> $values
     * @param FormField[] $fields
     */
    public function rulesMatch(array $rules, array $values, array $fields = []): bool
    {
        if ($rules === []) {
            return true;
        }
        return (new LogicEngine())->evaluateRule($rules, $values, $fields);
    }

    /**
     * @param array<string,mixed> $values
     * @param FormField[] $fields
     */
    public function cellKnown(array $rules, array $values, array $fields = []): bool
    {
        foreach ($this->leaves($rules) as $leaf) {
            $raw = $this->leafValue($leaf, $values, $fields);
            if ($raw === null || $raw === '' || $raw === []) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<int, array{code:string,name:string,weight:int}> $arms
     * @return string|false|null arm code, false when every qualifying arm is full, null when quotas do not assign
     */
    public function chooseArm(CustomForm $form, FormAnswer $answer, array $values, array $arms, int $seed)
    {
        self::$armBlock = null;
        if (!self::active($form) || (string)$form->getSetting('quota_assign_arm', '0') !== '1' || $answer->isTest()) {
            return null;
        }
        $candidates = [];
        foreach ($this->quotas((int)$form->id) as $quota) {
            if (!$this->waveMatches($quota, $answer)) {
                continue;
            }
            $armCode = $this->singleArmCode($this->rulesOf($quota));
            if ($armCode === null) {
                continue;
            }
            $probe = $values;
            $probe['arm'] = $armCode;
            $probe = $this->withPanel($probe, $form, $answer);
            if (!$this->rulesMatch($this->rulesOf($quota), $probe, $form->fields) || !$this->cellKnown($this->rulesOf($quota), $probe, $form->fields)) {
                continue;
            }
            $candidates[$armCode][] = $quota;
        }
        if ($candidates === []) {
            return null;
        }
        $open = [];
        $counts = [];
        $fullest = null;
        $fullestRatio = -1.0;
        foreach ($arms as $arm) {
            $code = (string)$arm['code'];
            if (!isset($candidates[$code])) {
                continue;
            }
            $blocked = false;
            $accepted = 0;
            foreach ($candidates[$code] as $quota) {
                $counter = $this->readCounter((int)$quota['id']);
                $accepted = max($accepted, (int)$counter['accepted']);
                $target = max(1, (int)$quota['target']);
                $ratio = ((int)$counter['accepted'] + (int)$counter['reserved']) / $target;
                if ($ratio > $fullestRatio) {
                    $fullestRatio = $ratio;
                    $fullest = $quota;
                }
                if ((string)$quota['status'] === 'closed' || (int)$counter['accepted'] + (int)$counter['reserved'] >= (int)$quota['target'] || $this->parentFull($quota)) {
                    $blocked = true;
                }
            }
            if (!$blocked) {
                $open[] = $code;
                $counts[$code] = $accepted;
            }
        }
        if ($open === []) {
            self::$armBlock = $fullest;
            return false;
        }
        return (new RandomisationEngine())->leastFilled($counts, $open, $seed);
    }

    public function apply(SubmitForm $submit, FormAnswer $answer, bool $asDraft, ?int $postedPage): void
    {
        $submit->quotaHalt = '';
        $submit->quotaMessage = '';
        $submit->quotaUrl = '';
        $submit->quotaPage = null;
        $form = $submit->form;
        if (!$form || !self::active($form) || $answer->isTest()) {
            self::$armBlock = null;
            return;
        }
        if (!$answer->isNewRecord && $answer->isComplete() && (string)$answer->outcome !== '') {
            self::$armBlock = null;
            return;
        }
        if (self::$armBlock) {
            $this->halt($submit, $form, $answer, self::$armBlock);
            self::$armBlock = null;
            return;
        }
        $values = $this->withPanel($submit->values, $form, $answer);
        $mode = $asDraft ? 'leave' : 'submit';
        $qualifying = [];
        foreach ($this->quotas((int)$form->id) as $quota) {
            if (!$this->waveMatches($quota, $answer)) {
                continue;
            }
            if ($mode === 'leave' && !$this->leaving($form, $quota, $postedPage)) {
                continue;
            }
            $rules = $this->rulesOf($quota);
            if (!$this->cellKnown($rules, $values, $form->fields) || !$this->rulesMatch($rules, $values, $form->fields)) {
                continue;
            }
            $qualifying[] = $quota;
        }
        if ($qualifying === []) {
            return;
        }
        $ids = [];
        foreach ($qualifying as $quota) {
            foreach ($this->ancestors($quota) as $item) {
                $ids[(int)$item['id']] = $item;
            }
        }
        ksort($ids);
        $db = Yii::$app->db;
        $own = $db->getTransaction() === null;
        $tx = $own ? $db->beginTransaction() : null;
        try {
            $locked = [];
            foreach ($ids as $id => $quota) {
                $locked[$id] = $this->lockCounter($id);
                $this->dropExpired($id, $locked[$id]);
            }
            $full = [];
            $holds = [];
            foreach ($this->parentFirst($ids) as $quota) {
                $id = (int)$quota['id'];
                $parentId = (int)($quota['parent_id'] ?? 0);
                $counter = $locked[$id];
                $reservation = $this->reservationId($id, (int)$answer->id);
                $holds[$id] = $reservation;
                $parentBlocked = $parentId > 0 && !empty($full[$parentId]);
                $occupied = (int)$counter['accepted'] + (int)$counter['reserved'];
                $full[$id] = (string)$quota['status'] === 'closed'
                    || $parentBlocked
                    || ($occupied >= (int)$quota['target'] && !$reservation);
            }
            $closing = null;
            $goto = null;
            foreach ($this->parentFirst($ids) as $quota) {
                $id = (int)$quota['id'];
                if (empty($full[$id]) || !$this->isQualifying($qualifying, $id)) {
                    continue;
                }
                if (in_array((string)$quota['action'], ['end', 'redirect'], true) && $closing === null) {
                    $closing = $quota;
                } elseif ((string)$quota['action'] === 'goto' && $goto === null) {
                    $goto = $quota;
                }
            }
            if ($closing || $goto) {
                foreach ($holds as $id => $reservation) {
                    if ($reservation && !empty($full[$id])) {
                        $counter = $locked[$id];
                        $this->releaseReservation((int)$id, (int)$reservation, $counter);
                        $locked[$id] = $counter;
                    }
                }
            } else {
                foreach ($this->parentFirst($ids) as $quota) {
                    $id = (int)$quota['id'];
                    if (!$this->isQualifying($qualifying, $id)) {
                        continue;
                    }
                    if ((string)$quota['action'] === 'continue') {
                        $this->markContinue($answer, $id);
                        continue;
                    }
                    if (!empty($full[$id])) {
                        continue;
                    }
                    $counter = $locked[$id];
                    if ($mode === 'leave' && (int)$quota['reserve'] === 1 && empty($holds[$id])) {
                        $this->reserve($id, (int)$answer->id, (int)$quota['reserve_minutes'], $counter);
                        $locked[$id] = $counter;
                        continue;
                    }
                    if ($mode === 'submit') {
                        if (!empty($holds[$id])) {
                            $this->convertReservation($id, (int)$holds[$id], $counter);
                        }
                        $this->accept($id, (int)$answer->id, $counter, $quota);
                        $locked[$id] = $counter;
                    }
                }
            }
            if ($own && $tx) {
                $tx->commit();
            }
        } catch (\Throwable $e) {
            if ($own && $tx) {
                $tx->rollBack();
            }
            throw $e;
        }
        if ($closing) {
            $this->halt($submit, $form, $answer, $closing);
        } elseif ($goto) {
            $built = (new FormPager())->buildPages($form->fields);
            $index = $built['pageKeyIndex'][(string)$goto['action_page_key']] ?? null;
            $submit->quotaHalt = 'goto';
            $submit->quotaPage = $index === null ? 0 : (int)$index;
            $submit->quotaMessage = $this->message($form, $goto);
        }
    }

    public function blocksInvite(CustomForm $form, FormPanelMember $member, $wave): bool
    {
        if (!self::active($form)) {
            return false;
        }
        $waveId = $wave ? (int)$wave->id : 0;
        $values = [];
        foreach ($member->getDemographics() as $key => $value) {
            if (is_scalar($value)) {
                $values['panel.' . $key] = (string)$value;
            }
        }
        $knownFull = 0;
        $knownOpen = 0;
        foreach ($this->quotas((int)$form->id) as $quota) {
            $quotaWave = (int)($quota['wave_id'] ?? 0);
            if ($quotaWave !== 0 && $quotaWave !== $waveId) {
                continue;
            }
            $rules = $this->rulesOf($quota);
            if (!$this->panelOnly($rules) || !$this->cellKnown($rules, $values) || !$this->rulesMatch($rules, $values)) {
                continue;
            }
            $counter = $this->readCounter((int)$quota['id']);
            $full = (string)$quota['status'] === 'closed'
                || (int)$counter['accepted'] + (int)$counter['reserved'] >= (int)$quota['target'];
            if ($full) {
                $knownFull++;
            } else {
                $knownOpen++;
            }
        }
        return $knownOpen === 0 && $knownFull > 0;
    }

    public function releaseExcluded(CustomForm $form, FormAnswer $answer): void
    {
        if (!self::tablesReady() || $answer->isTest()) {
            return;
        }
        $rows = (new Query())->from('{{%custom_form_quota_accept}}')->where([
            'answer_id' => (int)$answer->id,
            'released' => 0,
        ])->all();
        if (!$rows) {
            return;
        }
        $db = Yii::$app->db;
        $own = $db->getTransaction() === null;
        $tx = $own ? $db->beginTransaction() : null;
        try {
            foreach ($rows as $row) {
                $quota = $this->quota((int)$row['quota_id'], (int)$form->id);
                if (!$quota || (string)$quota['count_policy'] !== 'complete_excluding_integrity') {
                    continue;
                }
                $counter = $this->lockCounter((int)$row['quota_id']);
                $counter['accepted'] = max(0, (int)$counter['accepted'] - 1);
                $db->createCommand()->update('{{%custom_form_quota_counter}}', [
                    'accepted' => $counter['accepted'],
                ], ['quota_id' => (int)$row['quota_id']])->execute();
                $db->createCommand()->update('{{%custom_form_quota_accept}}', [
                    'released' => 1,
                ], ['quota_id' => (int)$row['quota_id'], 'answer_id' => (int)$answer->id])->execute();
                $this->audit((int)$row['quota_id'], [
                    'released_answer' => (int)$answer->id,
                    'accepted' => $counter['accepted'],
                ], null);
            }
            if ($own && $tx) {
                $tx->commit();
            }
        } catch (\Throwable $e) {
            if ($own && $tx) {
                $tx->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array<int, array{quota_id:int,name:string,stored:int,recount:int}>
     */
    public function reconcile(CustomForm $form, bool $apply): array
    {
        $drift = [];
        if (!self::tablesReady()) {
            return $drift;
        }
        foreach ($this->quotas((int)$form->id) as $quota) {
            $stored = (int)$this->readCounter((int)$quota['id'])['accepted'];
            $recount = $this->recount($form, $quota);
            if ($stored === $recount) {
                continue;
            }
            $drift[] = [
                'quota_id' => (int)$quota['id'],
                'name' => (string)$quota['name'],
                'stored' => $stored,
                'recount' => $recount,
            ];
            if (!$apply) {
                continue;
            }
            $db = Yii::$app->db;
            $tx = $db->beginTransaction();
            try {
                $this->lockCounter((int)$quota['id']);
                $db->createCommand()->update('{{%custom_form_quota_counter}}', [
                    'accepted' => $recount,
                    'reconciled_at' => gmdate('Y-m-d H:i:s'),
                ], ['quota_id' => (int)$quota['id']])->execute();
                $this->audit((int)$quota['id'], [
                    'reconcile_from' => $stored,
                    'reconcile_to' => $recount,
                ], null);
                $tx->commit();
            } catch (\Throwable $e) {
                $tx->rollBack();
                throw $e;
            }
        }
        return $drift;
    }

    public function expire(): int
    {
        if (!self::tablesReady()) {
            return 0;
        }
        $rows = (new Query())
            ->from('{{%custom_form_quota_reservation}}')
            ->where(['<=', 'expires_at', gmdate('Y-m-d H:i:s')])
            ->all();
        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        try {
            $byQuota = [];
            foreach ($rows as $row) {
                $byQuota[(int)$row['quota_id']][] = (int)$row['id'];
            }
            ksort($byQuota);
            foreach ($byQuota as $quotaId => $ids) {
                $counter = $this->lockCounter($quotaId);
                $db->createCommand()->delete('{{%custom_form_quota_reservation}}', ['id' => $ids])->execute();
                $counter['reserved'] = max(0, (int)$counter['reserved'] - count($ids));
                $db->createCommand()->update('{{%custom_form_quota_counter}}', [
                    'reserved' => $counter['reserved'],
                ], ['quota_id' => $quotaId])->execute();
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        return count($rows);
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function summary(CustomForm $form): array
    {
        if (!self::tablesReady()) {
            return [];
        }
        $out = [];
        foreach ($this->quotas((int)$form->id) as $quota) {
            $counter = $this->readCounter((int)$quota['id']);
            $target = (int)$quota['target'];
            $accepted = (int)$counter['accepted'];
            $reserved = (int)$counter['reserved'];
            $out[] = [
                'id' => (int)$quota['id'],
                'name' => (string)$quota['name'],
                'target' => $target,
                'accepted' => $accepted,
                'reserved' => $reserved,
                'remaining' => $target - $accepted - $reserved,
                'fill_percent' => $target > 0 ? (int)round(($accepted / $target) * 100) : 0,
                'status' => (string)$quota['status'],
                'reconciled_at' => (string)($counter['reconciled_at'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * @return array<int, string>
     */
    public function acceptedIds(FormAnswer $answer): array
    {
        if (!self::tablesReady() || !$answer->id) {
            return [];
        }
        return array_map('strval', (new Query())
            ->select('quota_id')
            ->from('{{%custom_form_quota_accept}}')
            ->where(['answer_id' => (int)$answer->id, 'released' => 0])
            ->orderBy(['quota_id' => SORT_ASC])
            ->column());
    }

    public function copyOnto(CustomForm $source, CustomForm $target): void
    {
        if (!self::tablesReady()) {
            return;
        }
        $map = [];
        foreach ($source->fields as $field) {
            $copy = null;
            foreach ($target->fields as $candidate) {
                if (trim((string)$candidate->variable) === trim((string)$field->variable) && (string)$candidate->type === (string)$field->type) {
                    $copy = $candidate;
                    break;
                }
            }
            if ($copy) {
                $map[(string)$field->id] = (string)$copy->id;
            }
        }
        $idMap = [];
        foreach ($this->quotas((int)$source->id) as $quota) {
            $rules = $this->remapRules($this->rulesOf($quota), $map);
            $created = $this->saveQuota($target, null, [
                'name' => $quota['name'],
                'target' => $quota['target'],
                'wave_id' => $quota['wave_id'],
                'rules' => $rules,
                'count_policy' => $quota['count_policy'],
                'reserve' => $quota['reserve'],
                'reserve_minutes' => $quota['reserve_minutes'],
                'action' => $quota['action'],
                'action_message' => $quota['action_message'],
                'action_url' => $quota['action_url'],
                'action_page_key' => $quota['action_page_key'],
                'check_page_key' => $quota['check_page_key'],
                'status' => $quota['status'],
                'sort_order' => $quota['sort_order'],
            ]);
            if ($created) {
                $idMap[(int)$quota['id']] = (int)$created['id'];
            }
        }
        foreach ($this->quotas((int)$source->id) as $quota) {
            $parent = (int)($quota['parent_id'] ?? 0);
            if ($parent && isset($idMap[(int)$quota['id']], $idMap[$parent])) {
                Yii::$app->db->createCommand()->update('{{%custom_form_quota}}', [
                    'parent_id' => $idMap[$parent],
                ], ['id' => $idMap[(int)$quota['id']]])->execute();
            }
        }
        foreach ($this->hosts((int)$source->id) as $host) {
            $this->addHost($target, $host);
        }
    }

    /**
     * @param array<int, array<string,mixed>> $rows
     * @return array{imported:int,failed:array<int,string>}
     */
    public function importPayload(CustomForm $form, array $rows): array
    {
        $imported = 0;
        $failed = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $created = $this->saveQuota($form, null, $row);
            if (!$created) {
                $failed[] = (string)($row['name'] ?? '');
                continue;
            }
            $errors = $this->authoringErrors($form);
            $mine = array_values(array_filter($errors, static fn($message) => str_contains($message, (string)$created['name'])));
            if ($mine !== []) {
                Yii::$app->db->createCommand()->delete('{{%custom_form_quota_counter}}', ['quota_id' => (int)$created['id']])->execute();
                Yii::$app->db->createCommand()->delete('{{%custom_form_quota}}', ['id' => (int)$created['id']])->execute();
                $failed[] = (string)$created['name'];
                continue;
            }
            $imported++;
        }
        return ['imported' => $imported, 'failed' => $failed];
    }

    /**
     * @return array<int, array{key:string,part:string,source:string}>
     */
    public function translationUnits(CustomForm $form): array
    {
        if (!self::tablesReady()) {
            return [];
        }
        $units = [];
        foreach ($this->quotas((int)$form->id) as $quota) {
            $id = (int)$quota['id'];
            $units[] = ['key' => 'quota.' . $id . '.name', 'part' => 'name', 'source' => (string)$quota['name']];
            $units[] = ['key' => 'quota.' . $id . '.message', 'part' => 'message', 'source' => (string)$quota['action_message']];
        }
        return $units;
    }

    public function saveTranslation(int $quotaId, string $part, string $language, string $value): bool
    {
        if (!self::tablesReady() || !in_array($part, ['name', 'message'], true)) {
            return false;
        }
        $language = trim($language);
        $existing = (new Query())->from('{{%custom_form_quota_i18n}}')->where([
            'quota_id' => $quotaId,
            'language' => $language,
        ])->one();
        $column = $part === 'name' ? 'name' : 'message';
        if ($existing) {
            Yii::$app->db->createCommand()->update('{{%custom_form_quota_i18n}}', [
                $column => $value,
            ], ['quota_id' => $quotaId, 'language' => $language])->execute();
            return true;
        }
        Yii::$app->db->createCommand()->insert('{{%custom_form_quota_i18n}}', [
            'quota_id' => $quotaId,
            'language' => $language,
            'name' => $part === 'name' ? $value : null,
            'message' => $part === 'message' ? $value : null,
        ])->execute();
        return true;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function quotas(int $formId): array
    {
        if (!self::tablesReady()) {
            return [];
        }
        return (new Query())->from('{{%custom_form_quota}}')->where(['form_id' => $formId])->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])->all();
    }

    public function quota(int $id, int $formId): ?array
    {
        $row = (new Query())->from('{{%custom_form_quota}}')->where(['id' => $id, 'form_id' => $formId])->one();
        return $row ?: null;
    }

    /**
     * @param array<string,mixed> $rules
     * @return array<int, array<string,mixed>>
     */
    public function leaves(array $rules): array
    {
        if (isset($rules['all']) && is_array($rules['all'])) {
            $out = [];
            foreach ($rules['all'] as $sub) {
                if (is_array($sub)) {
                    $out = array_merge($out, $this->leaves($sub));
                }
            }
            return $out;
        }
        if (isset($rules['any']) && is_array($rules['any'])) {
            $out = [];
            foreach ($rules['any'] as $sub) {
                if (is_array($sub)) {
                    $out = array_merge($out, $this->leaves($sub));
                }
            }
            return $out;
        }
        if (trim((string)($rules['fieldKey'] ?? '')) === '' && trim((string)($rules['source'] ?? '')) === '') {
            return [];
        }
        return [$rules];
    }

    /**
     * @param array<string,mixed> $quota
     * @param array<string,mixed> $counter
     */
    private function accept(int $quotaId, int $answerId, array &$counter, array $quota): void
    {
        $before = (int)$counter['accepted'];
        $counter['accepted'] = $before + 1;
        $db = Yii::$app->db;
        $db->createCommand()->update('{{%custom_form_quota_counter}}', [
            'accepted' => $counter['accepted'],
        ], ['quota_id' => $quotaId])->execute();
        $exists = (new Query())->from('{{%custom_form_quota_accept}}')->where([
            'quota_id' => $quotaId,
            'answer_id' => $answerId,
        ])->exists();
        if (!$exists) {
            $db->createCommand()->insert('{{%custom_form_quota_accept}}', [
                'quota_id' => $quotaId,
                'answer_id' => $answerId,
                'released' => 0,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ])->execute();
        }
        $target = (int)$quota['target'];
        if ($before === $target - 1 && $counter['accepted'] === $target) {
            $this->audit($quotaId, ['event' => 'quota.full', 'accepted' => $target], null);
            $this->notifyFull($quota, $target);
        }
    }

    /**
     * @param array<string,mixed> $quota
     */
    private function notifyFull(array $quota, int $target): void
    {
        $form = CustomForm::findOne((int)$quota['form_id']);
        if (!$form) {
            return;
        }
        $to = strtolower(trim((string)$form->getSetting('quota_full_email', '')));
        if ($to === '' || !str_ends_with($to, '@example.test')) {
            return;
        }
        try {
            Yii::$app->mailer->compose()
                ->setTo($to)
                ->setSubject('Quota full: ' . (string)$quota['name'])
                ->setTextBody('Quota ' . (string)$quota['name'] . ' reached ' . $target . '.')
                ->send();
        } catch (\Throwable $e) {
            Yii::warning('Quota full mail was not sent: ' . $e->getMessage(), 'thiscovery-forms');
        }
    }

    /**
     * @param array<string,mixed> $counter
     */
    private function reserve(int $quotaId, int $answerId, int $minutes, array &$counter): void
    {
        $counter['reserved'] = (int)$counter['reserved'] + 1;
        Yii::$app->db->createCommand()->insert('{{%custom_form_quota_reservation}}', [
            'quota_id' => $quotaId,
            'answer_id' => $answerId,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + ($minutes * 60)),
        ])->execute();
        Yii::$app->db->createCommand()->update('{{%custom_form_quota_counter}}', [
            'reserved' => $counter['reserved'],
        ], ['quota_id' => $quotaId])->execute();
    }

    /**
     * @param array<string,mixed> $counter
     */
    private function convertReservation(int $quotaId, int $reservationId, array &$counter): void
    {
        Yii::$app->db->createCommand()->delete('{{%custom_form_quota_reservation}}', ['id' => $reservationId])->execute();
        $counter['reserved'] = max(0, (int)$counter['reserved'] - 1);
        Yii::$app->db->createCommand()->update('{{%custom_form_quota_counter}}', [
            'reserved' => $counter['reserved'],
        ], ['quota_id' => $quotaId])->execute();
    }

    /**
     * @param array<string,mixed> $counter
     */
    private function releaseReservation(int $quotaId, int $reservationId, array &$counter): void
    {
        $this->convertReservation($quotaId, $reservationId, $counter);
    }

    /**
     * @param array<string,mixed> $counter
     */
    private function dropExpired(int $quotaId, array &$counter): void
    {
        $ids = (new Query())
            ->select('id')
            ->from('{{%custom_form_quota_reservation}}')
            ->where(['quota_id' => $quotaId])
            ->andWhere(['<=', 'expires_at', gmdate('Y-m-d H:i:s')])
            ->column();
        if (!$ids) {
            return;
        }
        Yii::$app->db->createCommand()->delete('{{%custom_form_quota_reservation}}', ['id' => $ids])->execute();
        $counter['reserved'] = max(0, (int)$counter['reserved'] - count($ids));
        Yii::$app->db->createCommand()->update('{{%custom_form_quota_counter}}', [
            'reserved' => $counter['reserved'],
        ], ['quota_id' => $quotaId])->execute();
    }

    /**
     * @return array{accepted:int,reserved:int,reconciled_at:?string}
     */
    private function lockCounter(int $quotaId): array
    {
        $row = Yii::$app->db->createCommand(
            'SELECT accepted, reserved, reconciled_at FROM {{%custom_form_quota_counter}} WHERE quota_id = :id FOR UPDATE',
            [':id' => $quotaId]
        )->queryOne();
        if (!$row) {
            Yii::$app->db->createCommand()->insert('{{%custom_form_quota_counter}}', [
                'quota_id' => $quotaId,
                'accepted' => 0,
                'reserved' => 0,
                'reconciled_at' => null,
            ])->execute();
            $row = Yii::$app->db->createCommand(
                'SELECT accepted, reserved, reconciled_at FROM {{%custom_form_quota_counter}} WHERE quota_id = :id FOR UPDATE',
                [':id' => $quotaId]
            )->queryOne();
        }
        return [
            'accepted' => (int)($row['accepted'] ?? 0),
            'reserved' => (int)($row['reserved'] ?? 0),
            'reconciled_at' => $row['reconciled_at'] ?? null,
        ];
    }

    /**
     * @return array{accepted:int,reserved:int,reconciled_at:?string}
     */
    private function readCounter(int $quotaId): array
    {
        $row = (new Query())->from('{{%custom_form_quota_counter}}')->where(['quota_id' => $quotaId])->one();
        return [
            'accepted' => (int)($row['accepted'] ?? 0),
            'reserved' => (int)($row['reserved'] ?? 0),
            'reconciled_at' => $row['reconciled_at'] ?? null,
        ];
    }

    private function reservationId(int $quotaId, int $answerId): ?int
    {
        $id = (new Query())
            ->select('id')
            ->from('{{%custom_form_quota_reservation}}')
            ->where(['quota_id' => $quotaId, 'answer_id' => $answerId])
            ->andWhere(['>', 'expires_at', gmdate('Y-m-d H:i:s')])
            ->scalar();
        return $id ? (int)$id : null;
    }

    /**
     * @param array<string,mixed> $quota
     */
    private function parentFull(array $quota): bool
    {
        $parentId = (int)($quota['parent_id'] ?? 0);
        if ($parentId < 1) {
            return false;
        }
        $parent = (new Query())->from('{{%custom_form_quota}}')->where(['id' => $parentId])->one();
        if (!$parent) {
            return false;
        }
        if ((string)$parent['status'] === 'closed') {
            return true;
        }
        $counter = $this->readCounter($parentId);
        return (int)$counter['accepted'] + (int)$counter['reserved'] >= (int)$parent['target'];
    }

    /**
     * @param array<string,mixed> $quota
     */
    private function halt(SubmitForm $submit, CustomForm $form, FormAnswer $answer, array $quota): void
    {
        $action = (string)$quota['action'];
        if ($action === 'goto') {
            $built = (new FormPager())->buildPages($form->fields);
            $index = $built['pageKeyIndex'][(string)$quota['action_page_key']] ?? 0;
            $submit->quotaHalt = 'goto';
            $submit->quotaPage = (int)$index;
            $submit->quotaMessage = $this->message($form, $quota);
            return;
        }
        if ($action === 'continue') {
            $this->markContinue($answer, (int)$quota['id']);
            return;
        }
        $submit->quotaHalt = $action === 'redirect' ? 'redirect' : 'end';
        $submit->quotaMessage = $this->message($form, $quota);
        if ($submit->quotaHalt === 'redirect') {
            $submit->quotaUrl = $this->redirectUrl($form, $answer, $quota);
        }
        $answer->outcome = FormAnswer::OUTCOME_OVER_QUOTA;
        $answer->save(false, ['outcome', 'updated_at']);
        if ($this->singleArmCode($this->rulesOf($quota)) && Yii::$app->db->schema->getTableSchema('{{%custom_form_arm_assignment}}', true)) {
            Yii::$app->db->createCommand()->delete('{{%custom_form_arm_assignment}}', ['answer_id' => (int)$answer->id])->execute();
        }
    }

    /**
     * @param array<string,mixed> $quota
     */
    private function message(CustomForm $form, array $quota): string
    {
        $language = trim((string)($form->getSetting('response_language', '') ?? ''));
        if ($language !== '') {
            $row = (new Query())->from('{{%custom_form_quota_i18n}}')->where([
                'quota_id' => (int)$quota['id'],
                'language' => $language,
            ])->one();
            if ($row && trim((string)($row['message'] ?? '')) !== '') {
                return (string)$row['message'];
            }
        }
        $text = trim((string)($quota['action_message'] ?? ''));
        return $text !== '' ? $text : Yii::t('ThiscoveryFormsModule.base', 'This group is full.');
    }

    /**
     * @param array<string,mixed> $quota
     */
    private function redirectUrl(CustomForm $form, FormAnswer $answer, array $quota): string
    {
        $url = (string)$quota['action_url'];
        $anonymous = $form->hidesIdentityFromManagers() && Module::identityEnforced();
        $answerToken = $anonymous ? '' : (string)$answer->id;
        return strtr($url, [
            '{status}' => 'over_quota',
            '{quota}' => (string)$quota['id'],
            '{answer}' => $answerToken,
        ]);
    }

    private function markContinue(FormAnswer $answer, int $quotaId): void
    {
        if ((int)$answer->quota_marker > 0) {
            return;
        }
        $answer->quota_marker = $quotaId;
        $answer->save(false, ['quota_marker', 'updated_at']);
    }

    /**
     * @param array<int, array<string,mixed>> $qualifying
     */
    private function isQualifying(array $qualifying, int $id): bool
    {
        foreach ($qualifying as $quota) {
            if ((int)$quota['id'] === $id) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $quota
     */
    private function waveMatches(array $quota, FormAnswer $answer): bool
    {
        $waveId = (int)($quota['wave_id'] ?? 0);
        return $waveId === 0 || $waveId === (int)$answer->wave_id;
    }

    private function leaving(CustomForm $form, array $quota, ?int $postedPage): bool
    {
        $key = trim((string)($quota['check_page_key'] ?? ''));
        if ($key === '') {
            return false;
        }
        $built = (new FormPager())->buildPages($form->fields);
        $need = $built['pageKeyIndex'][$key] ?? null;
        if ($need === null) {
            return false;
        }
        return (int)$postedPage > (int)$need;
    }

    /**
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    private function withPanel(array $values, CustomForm $form, FormAnswer $answer): array
    {
        if ($form->hidesIdentityFromManagers() && Module::identityEnforced()) {
            return $values;
        }
        $member = $answer->panel_member_id ? FormPanelMember::findOne((int)$answer->panel_member_id) : null;
        if (!$member) {
            return $values;
        }
        foreach ($member->getDemographics() as $key => $value) {
            if (is_scalar($value)) {
                $values['panel.' . $key] = (string)$value;
            }
        }
        return $values;
    }

    /**
     * @param array<string,mixed> $rules
     */
    private function panelOnly(array $rules): bool
    {
        $leaves = $this->leaves($rules);
        if ($leaves === []) {
            return false;
        }
        foreach ($leaves as $leaf) {
            $source = (string)($leaf['source'] ?? 'field');
            $key = (string)($leaf['fieldKey'] ?? '');
            if ($source !== 'panel' && !str_starts_with($key, 'panel.')) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string,mixed> $rules
     */
    private function singleArmCode(array $rules): ?string
    {
        $code = null;
        foreach ($this->leaves($rules) as $leaf) {
            $source = (string)($leaf['source'] ?? 'field');
            $key = (string)($leaf['fieldKey'] ?? '');
            if ($source !== 'arm' && $key !== 'arm') {
                continue;
            }
            if ((string)($leaf['operator'] ?? 'equals') !== 'equals') {
                return null;
            }
            $value = trim((string)($leaf['value'] ?? ''));
            if ($code !== null && $code !== $value) {
                return null;
            }
            $code = $value;
        }
        return $code !== '' ? $code : null;
    }

    /**
     * @param array<string,mixed> $quota
     * @return array<int, array<string,mixed>>
     */
    private function ancestors(array $quota): array
    {
        $out = [$quota];
        $guard = 0;
        $current = $quota;
        while ($guard < 20 && (int)($current['parent_id'] ?? 0) > 0) {
            $parent = (new Query())->from('{{%custom_form_quota}}')->where(['id' => (int)$current['parent_id']])->one();
            if (!$parent) {
                break;
            }
            array_unshift($out, $parent);
            $current = $parent;
            $guard++;
        }
        return $out;
    }

    /**
     * @param array<int, array<string,mixed>> $byId
     * @return array<int, array<string,mixed>>
     */
    private function parentFirst(array $byId): array
    {
        $ordered = [];
        $seen = [];
        $walk = function (array $quota) use (&$walk, &$ordered, &$seen, $byId): void {
            $id = (int)$quota['id'];
            if (isset($seen[$id])) {
                return;
            }
            $parentId = (int)($quota['parent_id'] ?? 0);
            if ($parentId > 0 && isset($byId[$parentId])) {
                $walk($byId[$parentId]);
            }
            $seen[$id] = true;
            $ordered[] = $quota;
        };
        foreach ($byId as $quota) {
            $walk($quota);
        }
        return $ordered;
    }

    /**
     * @param array<string,mixed> $quota
     * @return array<string,mixed>
     */
    private function rulesOf(array $quota): array
    {
        $decoded = json_decode((string)($quota['rules_json'] ?? ''), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param mixed $rules
     * @return array<string,mixed>
     */
    private function normalizeRules($rules): array
    {
        if (is_string($rules)) {
            $decoded = json_decode($rules, true);
            $rules = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($rules)) {
            return [];
        }
        $normalized = LogicEngine::normalizeRule($rules);
        return $normalized ?: [];
    }

    /**
     * @param array<string,mixed> $rules
     * @param array<string,string> $map
     * @return array<string,mixed>
     */
    private function remapRules(array $rules, array $map): array
    {
        if (isset($rules['all']) && is_array($rules['all'])) {
            $rules['all'] = array_map(fn($sub) => is_array($sub) ? $this->remapRules($sub, $map) : $sub, $rules['all']);
            return $rules;
        }
        if (isset($rules['any']) && is_array($rules['any'])) {
            $rules['any'] = array_map(fn($sub) => is_array($sub) ? $this->remapRules($sub, $map) : $sub, $rules['any']);
            return $rules;
        }
        $key = (string)($rules['fieldKey'] ?? '');
        if ($key !== '' && isset($map[$key])) {
            $rules['fieldKey'] = $map[$key];
        }
        return $rules;
    }

    /**
     * @param array<string,mixed> $leaf
     * @param array<string,mixed> $values
     * @param FormField[] $fields
     * @return mixed
     */
    private function leafValue(array $leaf, array $values, array $fields)
    {
        $source = (string)($leaf['source'] ?? 'field');
        $key = (string)($leaf['fieldKey'] ?? '');
        if ($source === 'panel' || str_starts_with($key, 'panel.')) {
            $attr = str_starts_with($key, 'panel.') ? $key : 'panel.' . $key;
            return $values[$attr] ?? null;
        }
        if ($source === 'arm' || $key === 'arm') {
            return $values['arm'] ?? null;
        }
        if (array_key_exists($key, $values)) {
            return $values[$key];
        }
        $field = $this->fieldByKey($fields, $key);
        if ($field && array_key_exists((int)$field->id, $values)) {
            return $values[(int)$field->id];
        }
        if ($field && array_key_exists((string)$field->variable, $values)) {
            return $values[(string)$field->variable];
        }
        return null;
    }

    /**
     * @param FormField[] $fields
     */
    private function fieldByKey(array $fields, string $key): ?FormField
    {
        foreach ($fields as $field) {
            if (!$field instanceof FormField) {
                continue;
            }
            if ((string)$field->id === $key || strcasecmp(trim((string)$field->variable), $key) === 0) {
                return $field;
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $quota
     * @param array<int, array<string,mixed>> $byId
     */
    private function parentCycle(array $quota, array $byId): bool
    {
        $seen = [];
        $current = $quota;
        while ((int)($current['parent_id'] ?? 0) > 0) {
            $parentId = (int)$current['parent_id'];
            if (isset($seen[$parentId]) || $parentId === (int)$quota['id']) {
                return true;
            }
            $seen[$parentId] = true;
            if (!isset($byId[$parentId])) {
                return false;
            }
            $current = $byId[$parentId];
        }
        return false;
    }

    /**
     * @return array<int, string>
     */
    private function hosts(int $formId): array
    {
        if (Yii::$app->db->schema->getTableSchema('{{%custom_form_quota_allowhost}}', true) === null) {
            return [];
        }
        return (new Query())->select('host')->from('{{%custom_form_quota_allowhost}}')->where(['form_id' => $formId])->column();
    }

    private function redirectError(string $url, array $hosts): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            return Yii::t('ThiscoveryFormsModule.base', 'the URL is not valid');
        }
        if (strtolower((string)$parts['scheme']) !== 'https') {
            return Yii::t('ThiscoveryFormsModule.base', 'the URL is not HTTPS');
        }
        if (!empty($parts['user']) || !empty($parts['pass'])) {
            return Yii::t('ThiscoveryFormsModule.base', 'the URL contains a username');
        }
        $host = strtolower((string)$parts['host']);
        $allowed = false;
        foreach ($hosts as $item) {
            if (strtolower((string)$item) === $host) {
                $allowed = true;
            }
        }
        if (!$allowed) {
            return Yii::t('ThiscoveryFormsModule.base', 'the host is not allowlisted');
        }
        return $this->hostIsPrivate($host);
    }

    private function hostIsPrivate(string $host): ?string
    {
        $allowLocal = false;
        $module = Yii::$app->getModule('thiscovery-forms');
        if ($module instanceof Module) {
            $flag = (string)$module->settings->get('webhook_allow_localhost', '0');
            $allowLocal = in_array($flag, ['1', 'true', 'on'], true);
        }
        $loopback = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($loopback) {
            return $allowLocal ? null : Yii::t('ThiscoveryFormsModule.base', 'localhost is not allowed');
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            return Yii::t('ThiscoveryFormsModule.base', 'the host did not resolve');
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return Yii::t('ThiscoveryFormsModule.base', 'the host is a private address');
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $quota
     */
    private function recount(CustomForm $form, array $quota): int
    {
        $count = 0;
        $answers = FormAnswer::find()->where([
            'form_id' => (int)$form->id,
            'is_test' => 0,
            'status' => FormAnswer::STATUS_COMPLETE,
        ])->andWhere(['outcome' => ['', FormAnswer::OUTCOME_COMPLETE]])->all();
        foreach ($answers as $answer) {
            if ((int)$answer->quota_marker === (int)$quota['id']) {
                continue;
            }
            if (!$this->waveMatches($quota, $answer)) {
                continue;
            }
            if ((string)$quota['count_policy'] === 'complete_excluding_integrity') {
                $excluded = (new Query())->from('{{%custom_form_integrity_meta}}')->where([
                    'answer_id' => (int)$answer->id,
                    'analysis_status' => 'excluded',
                ])->exists();
                if ($excluded) {
                    continue;
                }
            }
            $values = $answer->getValuesMap();
            $values = $this->withPanel($values, $form, $answer);
            $assigned = (new RandomisationService())->assignment($answer);
            if ($assigned) {
                $values['arm'] = (string)$assigned['arm_code'];
            }
            $rules = $this->rulesOf($quota);
            if ($this->rulesMatch($rules, $values, $form->fields)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param array<string,mixed> $change
     */
    private function audit(int $quotaId, array $change, ?int $actorId): void
    {
        Yii::$app->db->createCommand()->insert('{{%custom_form_quota_audit}}', [
            'quota_id' => $quotaId,
            'actor_id' => $actorId,
            'change_json' => json_encode($change, JSON_UNESCAPED_UNICODE),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ])->execute();
    }

    private function action(string $action): string
    {
        return in_array($action, ['end', 'redirect', 'goto', 'continue'], true) ? $action : 'end';
    }
}
