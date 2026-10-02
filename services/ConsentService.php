<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\file\models\File;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\thiscoveryForms\Module;
use Yii;
use yii\db\Query;

/**
 * Versioned consent. The record is the legal object. A fully anonymous record is not linked to the answer.
 */
class ConsentService
{
    public static function active(CustomForm $form): bool
    {
        return Module::econsentEnabled() && self::formEnabled($form);
    }

    public static function formEnabled(CustomForm $form): bool
    {
        return in_array((string)$form->getSetting('econsent_enabled', '0'), ['1', 'true', 'on'], true);
    }

    public function tablesReady(): bool
    {
        return Yii::$app->db->schema->getTableSchema('{{%custom_form_consent_document}}', true) !== null;
    }

    /**
     * @param array<string,mixed> $posted
     */
    public function saveFormSettings(CustomForm $form, array $posted): void
    {
        $enabled = in_array((string)($posted['enabled'] ?? '0'), ['1', 'true', 'on'], true) ? '1' : '0';
        $form->setSetting('econsent_enabled', $enabled);
        $reconsent = (string)($posted['reconsent'] ?? 'off');
        if (!in_array($reconsent, ['off', 'next_visit', 'email'], true)) {
            $reconsent = 'off';
        }
        $form->setSetting('reconsent', $reconsent);
        $hashes = in_array((string)($posted['store_client_hashes'] ?? '0'), ['1', 'true', 'on'], true) ? '1' : '0';
        $form->setSetting('consent_store_client_hashes', $hashes);
        $form->setSetting('not_consented_message', trim((string)($posted['not_consented_message'] ?? '')));
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    public function saveDraft(CustomForm $form, ?int $documentId, array $payload): ?array
    {
        if (!$this->tablesReady()) {
            return null;
        }
        $title = trim((string)($payload['title'] ?? ''));
        if ($title === '') {
            return null;
        }
        $body = (new HtmlSanitizer())->sanitize((string)($payload['body_html'] ?? ''));
        $now = gmdate('Y-m-d H:i:s');
        if ($documentId) {
            $row = $this->document($documentId, (int)$form->id);
            if (!$row || (string)$row['status'] !== 'draft') {
                return null;
            }
            Yii::$app->db->createCommand()->update('{{%custom_form_consent_document}}', [
                'title' => $title,
                'body_html' => $body,
                'approval_reference' => trim((string)($payload['approval_reference'] ?? '')),
                'effective_on' => $this->dateOrNull($payload['effective_on'] ?? ''),
            ], ['id' => $documentId])->execute();
        } else {
            $version = (int)(new Query())->from('{{%custom_form_consent_document}}')->where(['form_id' => (int)$form->id])->max('version');
            $version = max(0, $version) + 1;
            Yii::$app->db->createCommand()->insert('{{%custom_form_consent_document}}', [
                'form_id' => (int)$form->id,
                'version' => $version,
                'title' => $title,
                'body_html' => $body,
                'approval_reference' => trim((string)($payload['approval_reference'] ?? '')),
                'effective_on' => $this->dateOrNull($payload['effective_on'] ?? ''),
                'status' => 'draft',
                'created_at' => $now,
            ])->execute();
            $documentId = (int)Yii::$app->db->getLastInsertID();
        }
        $this->replaceItems($documentId, is_array($payload['items'] ?? null) ? $payload['items'] : []);
        return $this->document($documentId, (int)$form->id);
    }

    /**
     * @return string[]
     */
    public function publish(CustomForm $form, int $documentId): array
    {
        $row = $this->document($documentId, (int)$form->id);
        if (!$row) {
            return [Yii::t('ThiscoveryFormsModule.base', 'That consent document is not on this form.')];
        }
        if ((string)$row['status'] === 'published') {
            return [Yii::t('ThiscoveryFormsModule.base', 'A published consent version cannot be changed. Start the next version.')];
        }
        $items = $this->items($documentId);
        if (!$items) {
            return [Yii::t('ThiscoveryFormsModule.base', 'Add at least one consent statement before publishing.')];
        }
        foreach ($items as $item) {
            if (!empty($item['required']) && trim((string)$item['label']) === '') {
                return [Yii::t('ThiscoveryFormsModule.base', 'A required consent statement needs a label.')];
            }
        }
        $hash = $this->contentHash((string)$row['body_html'], $items, (string)$row['title']);
        Yii::$app->db->createCommand()->update('{{%custom_form_consent_document}}', [
            'status' => 'published',
            'content_hash' => $hash,
            'published_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $documentId, 'status' => 'draft'])->execute();
        if ((string)$form->getSetting('reconsent', 'off') === 'email') {
            $this->queueReconsent($form, $documentId);
        }
        return [];
    }

    public function newVersion(CustomForm $form, int $documentId): ?int
    {
        $row = $this->document($documentId, (int)$form->id);
        if (!$row || (string)$row['status'] !== 'published' || (int)$row['version'] < 1) {
            return null;
        }
        $draft = $this->saveDraft($form, null, [
            'title' => $row['title'],
            'body_html' => $row['body_html'],
            'approval_reference' => $row['approval_reference'],
            'effective_on' => $row['effective_on'],
            'items' => $this->items($documentId),
        ]);
        if (!$draft) {
            return null;
        }
        // Published translations are locked, so the next version starts from them.
        foreach ((new Query())->from('{{%custom_form_consent_i18n}}')->where(['document_id' => $documentId])->all() as $row) {
            Yii::$app->db->createCommand()->delete('{{%custom_form_consent_i18n}}', [
                'document_id' => (int)$draft['id'],
                'language' => $row['language'],
            ])->execute();
            Yii::$app->db->createCommand()->insert('{{%custom_form_consent_i18n}}', [
                'document_id' => (int)$draft['id'],
                'language' => $row['language'],
                'body_html' => $row['body_html'],
                'items_json' => $row['items_json'],
            ])->execute();
        }
        return (int)$draft['id'];
    }

    /**
     * @param array<int,array<string,mixed>> $items
     */
    public function contentHash(string $body, array $items, string $title = ''): string
    {
        $lines = [];
        foreach ($items as $item) {
            $lines[] = trim((string)($item['code'] ?? ''))
                . "\t" . trim((string)($item['label'] ?? ''))
                . "\t" . (!empty($item['required']) ? '1' : '0')
                . "\t" . ((($item['input'] ?? '') === 'checkbox') ? 'checkbox' : 'yes_no');
        }
        $head = trim($title) !== '' ? trim($title) . "\n" : '';
        return hash('sha256', $head . trim($body) . "\n" . implode("\n", $lines));
    }

    /**
     * Exactly what the participant is shown for a document: the page renders this and the
     * record hashes it, so the stored hash is always a hash of the displayed text (V3-27).
     *
     * @param array<string,mixed> $doc
     * @return array{language:string,title:string,body:string,items:array<int,array<string,mixed>>,hash:string}
     */
    public function presentation(CustomForm $form, array $doc, ?string $language = null): array
    {
        $language = $language ?? (new TranslationService())->resolve($form);
        if ($language === $form->getSourceLanguage()) {
            $language = '';
        }
        $items = $this->presentedItems($doc, $language);
        $body = $this->presentedBody($doc, $language);
        if ($language !== '' && $body === (string)$doc['body_html'] && $items === $this->items((int)$doc['id'])) {
            // No translation exists, so the base text is what was shown.
            $language = '';
        }
        $title = (string)$doc['title'];
        return [
            'language' => $language,
            'title' => $title,
            'body' => $body,
            'items' => $items,
            'hash' => $this->contentHash($body, $items, $title),
        ];
    }

    /**
     * The signature method that applies: fully anonymous forms only take checkbox attestation,
     * so no name or drawing is ever stored next to an unlinkable record (V3-26).
     */
    public function effectiveSignature(CustomForm $form, string $configured): string
    {
        if ($this->unlink($form)) {
            return 'checkbox';
        }
        return in_array($configured, ['typed', 'checkbox', 'drawn'], true) ? $configured : 'typed';
    }

    /** Witness details are identifying, so they are not taken on fully anonymous forms. */
    public function effectiveWitness(CustomForm $form, bool $configured): bool
    {
        return $configured && !$this->unlink($form);
    }

    /**
     * @return string[]
     */
    public function authoringErrors(CustomForm $form): array
    {
        if (!self::formEnabled($form) || !$this->tablesReady()) {
            return [];
        }
        $errors = [];
        $known = [];
        foreach ($this->documents((int)$form->id) as $doc) {
            $known[(int)$doc['id']] = true;
        }
        foreach ($form->getFields()->all() as $field) {
            if ($field->type !== FormField::TYPE_CONSENT) {
                continue;
            }
            $id = (int)$field->getConsentConfig()['document_id'];
            if ($id > 0 && !isset($known[$id])) {
                $errors[] = Yii::t('ThiscoveryFormsModule.base', 'The consent question points at a document that is not on this form.');
            }
            if ($id < 1 && !$this->latestPublished($form)) {
                $errors[] = Yii::t('ThiscoveryFormsModule.base', 'Publish a consent document before using the consent question.');
            }
        }
        return $errors;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function resolveDocument(CustomForm $form, FormField $field, ?FormAnswer $answer): ?array
    {
        if (!$this->tablesReady()) {
            return null;
        }
        $pinned = 0;
        if ($answer && !$this->unlink($form)) {
            $vars = $answer->getVars();
            $pinned = (int)($vars['consent_document_id'] ?? 0);
        }
        $reconsent = (string)$form->getSetting('reconsent', 'off');
        if ($pinned > 0 && $reconsent !== 'next_visit') {
            $row = $this->document($pinned, (int)$form->id);
            if ($row && (string)$row['status'] === 'published') {
                return $row;
            }
        }
        $configured = (int)$field->getConsentConfig()['document_id'];
        if ($configured > 0) {
            $row = $this->document($configured, (int)$form->id);
            if ($row && (string)$row['status'] === 'published' && ($reconsent !== 'next_visit' || !$this->newerPublished($form, $row))) {
                return $row;
            }
        }
        return $this->latestPublished($form);
    }

    public function validateSubmit(SubmitForm $submit): void
    {
        $form = $submit->form;
        if (!$form || !self::active($form) || !$this->tablesReady() || $submit->scenario === SubmitForm::SCENARIO_DRAFT) {
            return;
        }
        $posted = Yii::$app->request->post('consent', []);
        $posted = is_array($posted) ? $posted : [];
        $refusedAt = null;
        foreach ($form->fields as $field) {
            if ($field->type !== FormField::TYPE_CONSENT) {
                continue;
            }
            if ($refusedAt !== null && (int)$field->sort_order > $refusedAt) {
                continue;
            }
            $doc = $this->resolveDocument($form, $field, $submit->editingAnswer);
            if (!$doc) {
                $submit->addError('values', Yii::t('ThiscoveryFormsModule.base', 'This form has no published consent document.'));
                continue;
            }
            $bag = is_array($posted[$field->id] ?? null) ? $posted[$field->id] : (is_array($posted[(string)$field->id] ?? null) ? $posted[(string)$field->id] : []);
            $shown = $this->presentation($form, $doc);
            if (!$this->assertShown($submit, $shown, $bag)) {
                continue;
            }
            $decisions = $this->decisions($doc, $bag);
            foreach ($shown['items'] as $item) {
                if (empty($item['required'])) {
                    continue;
                }
                $value = $decisions[$item['code']] ?? '';
                if ($value === '') {
                    $submit->addError('values', Yii::t('ThiscoveryFormsModule.base', '“{label}” needs an answer.', ['label' => $item['label']]));
                } elseif ($value === 'no' && $refusedAt === null) {
                    $refusedAt = (int)$field->sort_order;
                }
            }
            if ($refusedAt === null) {
                $this->assertSignature($submit, $form, $field, $bag);
            }
        }
        if ($refusedAt === null) {
            $standalone = $this->standaloneDocument($form);
            if ($standalone) {
                $bag = is_array($posted['form'] ?? null) ? $posted['form'] : [];
                if ($this->validateDocumentBag($submit, $standalone, $bag)) {
                    $refusedAt = -1;
                }
            }
        }
        $submit->consentRefusedSort = $refusedAt;
    }

    public function applySubmit(SubmitForm $submit, FormAnswer $answer, bool $asDraft, bool $anonymous): void
    {
        $form = $submit->form;
        if (!$form || !self::active($form) || !$this->tablesReady()) {
            return;
        }
        $unlink = $anonymous || $this->unlink($form);
        $posted = Yii::$app->request->post('consent', []);
        $posted = is_array($posted) ? $posted : [];
        $sawField = false;
        foreach ($form->fields as $field) {
            if ($field->type !== FormField::TYPE_CONSENT) {
                continue;
            }
            $sawField = true;
            if ($submit->consentRefusedSort !== null && (int)$field->sort_order > (int)$submit->consentRefusedSort) {
                continue;
            }
            $doc = $this->resolveDocument($form, $field, $answer);
            if (!$doc) {
                continue;
            }
            if (!$unlink) {
                $vars = $answer->getVars();
                $vars['consent_document_id'] = (int)$doc['id'];
                $answer->setVars($vars);
                $answer->save(false, ['vars_json', 'updated_at']);
            }
            if ($asDraft) {
                continue;
            }
            $bag = is_array($posted[$field->id] ?? null) ? $posted[$field->id] : (is_array($posted[(string)$field->id] ?? null) ? $posted[(string)$field->id] : []);
            $shown = $this->presentation($form, $doc);
            $presented = $shown['items'];
            $decisions = $this->decisions($doc, $bag);
            $cfg = $field->getConsentConfig();
            $method = $this->effectiveSignature($form, (string)$cfg['signature']);
            foreach ($decisions as $code => $value) {
                $submit->values['consent.' . $code] = $value;
            }
            $refused = false;
            foreach ($presented as $item) {
                if (!empty($item['required']) && ($decisions[$item['code']] ?? '') === 'no') {
                    $refused = true;
                }
            }
            if ($refused) {
                $answer->outcome = FormAnswer::OUTCOME_NOT_CONSENTED;
                $message = trim((string)$form->getSetting('not_consented_message', ''));
                if ($message !== '') {
                    Yii::$app->session->setFlash('cf-not-consented', $message);
                }
            }
            if (!$unlink && !$refused) {
                $answer->consent_version = (int)$doc['version'];
            } else {
                $answer->consent_version = null;
            }
            $this->insertRecord($form, $answer, $doc, $shown, $decisions, $bag, $method, $unlink, $refused);
            $answer->save(false, ['consent_version', 'outcome', 'updated_at']);
        }
        if ($sawField || $asDraft) {
            return;
        }
        $doc = $this->standaloneDocument($form);
        if (!$doc) {
            return;
        }
        $bag = is_array($posted['form'] ?? null) ? $posted['form'] : [];
        if (!$unlink) {
            $vars = $answer->getVars();
            $vars['consent_document_id'] = (int)$doc['id'];
            $answer->setVars($vars);
            $answer->save(false, ['vars_json', 'updated_at']);
        }
        $shown = $this->presentation($form, $doc);
        $presented = $shown['items'];
        $decisions = $this->decisions($doc, $bag);
        $refused = false;
        foreach ($presented as $item) {
            if (!empty($item['required']) && ($decisions[$item['code']] ?? '') === 'no') {
                $refused = true;
            }
        }
        if ($refused) {
            $answer->outcome = FormAnswer::OUTCOME_NOT_CONSENTED;
            $message = trim((string)$form->getSetting('not_consented_message', ''));
            if ($message !== '') {
                Yii::$app->session->setFlash('cf-not-consented', $message);
            }
        }
        $answer->consent_version = (!$unlink && !$refused) ? (int)$doc['version'] : null;
        $this->insertRecord($form, $answer, $doc, $shown, $decisions, $bag, $this->effectiveSignature($form, 'typed'), $unlink, $refused);
        $answer->save(false, ['consent_version', 'outcome', 'updated_at']);
    }

    /**
     * A published sheet is asked at the start of the form when no Consent question was placed.
     *
     * @return array<string,mixed>|null
     */
    public function standaloneDocument(CustomForm $form): ?array
    {
        if (!self::active($form) || !$this->tablesReady()) {
            return null;
        }
        foreach ($form->fields as $field) {
            if ($field->type === FormField::TYPE_CONSENT) {
                return null;
            }
        }
        return $this->latestPublished($form);
    }

    /**
     * @param array<string,mixed> $doc
     * @param array<string,mixed> $bag
     */
    private function validateDocumentBag(SubmitForm $submit, array $doc, array $bag): bool
    {
        $form = $submit->form;
        $shown = $this->presentation($form, $doc);
        if (!$this->assertShown($submit, $shown, $bag)) {
            return false;
        }
        $decisions = $this->decisions($doc, $bag);
        $refused = false;
        foreach ($shown['items'] as $item) {
            if (empty($item['required'])) {
                continue;
            }
            $value = $decisions[$item['code']] ?? '';
            if ($value === '') {
                $submit->addError('values', Yii::t('ThiscoveryFormsModule.base', '“{label}” needs an answer.', ['label' => $item['label']]));
            } elseif ($value === 'no') {
                $refused = true;
            }
        }
        if (!$refused) {
            $this->assertSignatureMethod($submit, $this->effectiveSignature($form, 'typed'), false, false, $bag);
        }
        return $refused;
    }

    public function withdrawByToken(string $token, string $scope, string $reason, ?int $formId = null): bool
    {
        $token = trim($token);
        if ($token === '' || !$this->tablesReady()) {
            return false;
        }
        if (!in_array($scope, ['keep_data', 'stop_contact', 'delete_requested'], true)) {
            $scope = 'stop_contact';
        }
        $hash = hash('sha256', $token);
        $where = ['withdrawal_token_hash' => $hash];
        if ($formId !== null) {
            $where['form_id'] = $formId;
        }
        $record = (new Query())->from('{{%custom_form_consent_record}}')->where($where)->one();
        if (!$record) {
            return false;
        }
        // Claim the token first: only the request that clears it writes the withdrawal, so
        // two clicks cannot both withdraw (V3-43).
        $tx = Yii::$app->db->beginTransaction();
        try {
            $claimed = Yii::$app->db->createCommand()->update('{{%custom_form_consent_record}}', [
                'withdrawal_token_hash' => null,
            ], ['id' => (int)$record['id'], 'withdrawal_token_hash' => $hash])->execute();
            if ($claimed !== 1) {
                $tx->rollBack();
                return false;
            }
            $this->writeWithdrawal($record, $scope, $reason, null);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        return true;
    }

    /**
     * @param array<string,mixed> $record
     */
    public function withdrawRecord(array $record, string $scope, string $reason, ?int $actorId): void
    {
        if (!in_array($scope, ['keep_data', 'stop_contact', 'delete_requested'], true)) {
            $scope = 'stop_contact';
        }
        $this->writeWithdrawal($record, $scope, $reason, $actorId);
    }

    public function blocksContact(int $memberId): bool
    {
        if ($memberId < 1 || !$this->tablesReady()) {
            return false;
        }
        // A withdrawal by the person's account also stops contact through any panel (V3-43).
        $userId = (int)(new Query())->select('user_id')->from('{{%form_panel_member}}')->where(['id' => $memberId])->scalar();
        $who = ['or', ['panel_member_id' => $memberId]];
        if ($userId > 0) {
            $who[] = ['user_id' => $userId];
        }
        return (new Query())->from('{{%custom_form_consent_withdrawal}}')
            ->where(['scope' => ['stop_contact', 'delete_requested']])
            ->andWhere($who)
            ->exists();
    }

    public function importLegacyForForm(CustomForm $form): int
    {
        if (!$this->tablesReady()) {
            return 0;
        }
        $panelId = (int)$form->getSetting('panel_id', 0);
        if ($panelId < 1) {
            $panelId = (int)$form->enrol_panel_id;
        }
        if ($panelId < 1) {
            return 0;
        }
        $added = 0;
        $members = (new Query())->from('{{%form_panel_member}}')->where(['panel_id' => $panelId])->andWhere(['not', ['consent_at' => null]])->all();
        foreach ($members as $member) {
            $exists = (new Query())->from('{{%custom_form_consent_record}}')->where([
                'form_id' => (int)$form->id,
                'panel_member_id' => (int)$member['id'],
                'signature_method' => 'legacy',
            ])->exists();
            if ($exists) {
                continue;
            }
            $docId = $this->ensureLegacyDocument((int)$form->id);
            Yii::$app->db->createCommand()->insert('{{%custom_form_consent_record}}', [
                'form_id' => (int)$form->id,
                'panel_member_id' => (int)$member['id'],
                'user_id' => $member['user_id'] ?: null,
                'document_id' => $docId,
                'signature_method' => 'legacy',
                'signed_at' => $member['consent_at'],
                'language' => '',
                'channel' => 'self',
                'scrolled_to_end' => 0,
            ])->execute();
            $added++;
        }
        return $added;
    }

    public function legacySatisfies(int $recordId): bool
    {
        $row = (new Query())->from('{{%custom_form_consent_record}}')->where(['id' => $recordId])->one();
        if (!$row || (string)$row['signature_method'] === 'legacy') {
            return false;
        }
        return (int)($row['document_id'] ?? 0) > 0;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function document(int $id, int $formId): ?array
    {
        $row = (new Query())->from('{{%custom_form_consent_document}}')->where(['id' => $id, 'form_id' => $formId])->one();
        return $row ?: null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function documents(int $formId): array
    {
        if (!$this->tablesReady()) {
            return [];
        }
        return (new Query())->from('{{%custom_form_consent_document}}')->where(['form_id' => $formId])->orderBy(['version' => SORT_ASC])->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function items(int $documentId): array
    {
        return (new Query())->from('{{%custom_form_consent_item}}')->where(['document_id' => $documentId])->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function records(int $formId): array
    {
        if (!$this->tablesReady()) {
            return [];
        }
        return (new Query())->from('{{%custom_form_consent_record}}')->where(['form_id' => $formId])->orderBy(['id' => SORT_DESC])->all();
    }

    /**
     * @param array<string,mixed> $record
     */
    public function certificateHtml(CustomForm $form, array $record): string
    {
        // Labels as the participant saw them, the signer and the form, not bare codes (V3-43).
        $e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $t = static fn(string $s, array $p = []): string => Yii::t('ThiscoveryFormsModule.base', $s, $p);
        $doc = $this->document((int)$record['document_id'], (int)$form->id);
        $decisions = json_decode((string)($record['items_json'] ?? ''), true);
        $decisions = is_array($decisions) ? $decisions : [];
        $labels = [];
        if ($doc) {
            foreach ($this->presentedItems($doc, (string)($record['language'] ?? '')) as $item) {
                $labels[(string)$item['code']] = (string)$item['label'];
            }
        }
        $answer = static fn(string $v): string => $v === 'yes' ? $t('Yes') : ($v === 'no' ? $t('No') : $t('No answer'));
        $lines = '';
        foreach ($decisions as $code => $value) {
            $lines .= '<tr><td>' . $e($labels[(string)$code] ?? $code) . '</td><td>' . $e($answer((string)$value)) . '</td></tr>';
        }
        $methods = ['typed' => $t('Typed name'), 'drawn' => $t('Drawn signature'), 'checkbox' => $t('Tick-box attestation'), 'legacy' => $t('Recorded before eConsent')];
        $rows = [
            $t('Form') => (string)$form->title,
            $t('Information sheet') => $doc ? (string)$doc['title'] : '',
            $t('Version') => $doc ? (string)(int)$doc['version'] : '',
            $t('Ethics approval') => $doc ? (string)($doc['approval_reference'] ?? '') : '',
            $t('Signed by') => (string)($record['signature_name'] ?? ''),
            $t('Witness') => trim((string)($record['witness_name'] ?? '') . (($record['witness_role'] ?? '') ? ' (' . $record['witness_role'] . ')' : '')),
            $t('Signed on') => (string)($record['signed_at'] ?? ''),
            $t('Signature') => $methods[(string)($record['signature_method'] ?? '')] ?? (string)($record['signature_method'] ?? ''),
            $t('Language') => (string)($record['language'] ?? '') !== '' ? (string)$record['language'] : $form->getSourceLanguage(),
            $t('Read to the end') => !empty($record['scrolled_to_end']) ? $t('Yes') : $t('No'),
            $t('Text fingerprint (SHA-256)') => (string)($record['content_hash'] ?? ''),
        ];
        $meta = '';
        foreach ($rows as $label => $value) {
            if (trim($value) !== '') {
                $meta .= '<tr><th scope="row">' . $e($label) . '</th><td>' . $e($value) . '</td></tr>';
            }
        }
        return '<article class="cf-consent-certificate">'
            . '<h1>' . $e($t('Consent record')) . '</h1>'
            . '<table class="table cf-consent-certificate__meta"><tbody>' . $meta . '</tbody></table>'
            . '<h2>' . $e($t('Decisions')) . '</h2>'
            . '<table class="table cf-consent-certificate__items"><thead><tr><th scope="col">' . $e($t('Statement'))
            . '</th><th scope="col">' . $e($t('Answer')) . '</th></tr></thead><tbody>' . $lines . '</tbody></table>'
            . '</article>';
    }

    /**
     * @return array<int,array{key:string,source:string,version:int,part:string}>
     */
    public function translationUnits(CustomForm $form): array
    {
        if (!$this->tablesReady()) {
            return [];
        }
        $units = [];
        foreach ($this->documents((int)$form->id) as $doc) {
            if ((int)$doc['version'] < 1) {
                continue;
            }
            $version = (int)$doc['version'];
            $units[] = [
                'key' => 'consent.' . $version . '.body',
                'source' => (string)$doc['body_html'],
                'version' => $version,
                'part' => 'body',
            ];
            foreach ($this->items((int)$doc['id']) as $item) {
                $units[] = [
                    'key' => 'consent.' . $version . '.' . $item['code'],
                    'source' => (string)$item['label'],
                    'version' => $version,
                    'part' => 'item',
                ];
            }
        }
        return $units;
    }

    public function saveTranslation(CustomForm $form, int $version, string $part, string $language, string $value): bool
    {
        if (!$this->tablesReady()) {
            return false;
        }
        $doc = (new Query())->from('{{%custom_form_consent_document}}')->where([
            'form_id' => (int)$form->id,
            'version' => $version,
        ])->one();
        if (!$doc || (int)$doc['version'] < 1) {
            return false;
        }
        if ((string)$doc['status'] === 'published') {
            // A published sheet is frozen in every language (V3-27): start the next version.
            return false;
        }
        $row = (new Query())->from('{{%custom_form_consent_i18n}}')->where([
            'document_id' => (int)$doc['id'],
            'language' => $language,
        ])->one();
        $body = $row ? (string)$row['body_html'] : '';
        $items = $row ? json_decode((string)$row['items_json'], true) : [];
        $items = is_array($items) ? $items : [];
        if ($part === 'body') {
            $body = (new HtmlSanitizer())->sanitize($value);
        } else {
            $items[$part] = $value;
        }
        $payload = [
            'body_html' => $body,
            'items_json' => json_encode($items, JSON_UNESCAPED_UNICODE),
        ];
        if ($row) {
            Yii::$app->db->createCommand()->update('{{%custom_form_consent_i18n}}', $payload, [
                'document_id' => (int)$doc['id'],
                'language' => $language,
            ])->execute();
        } else {
            Yii::$app->db->createCommand()->insert('{{%custom_form_consent_i18n}}', $payload + [
                'document_id' => (int)$doc['id'],
                'language' => $language,
            ])->execute();
        }
        return true;
    }

    public function translated(CustomForm $form, int $version, string $language, string $part): string
    {
        $doc = (new Query())->from('{{%custom_form_consent_document}}')->where([
            'form_id' => (int)$form->id,
            'version' => $version,
        ])->one();
        if (!$doc) {
            return '';
        }
        $row = (new Query())->from('{{%custom_form_consent_i18n}}')->where([
            'document_id' => (int)$doc['id'],
            'language' => $language,
        ])->one();
        if (!$row) {
            return '';
        }
        if ($part === 'body') {
            return (string)$row['body_html'];
        }
        $items = json_decode((string)$row['items_json'], true);
        return is_array($items) ? (string)($items[$part] ?? '') : '';
    }

    /**
     * @return array<int,int> old document id => new document id
     */
    public function copyOnto(CustomForm $source, CustomForm $target): array
    {
        $map = [];
        if (!$this->tablesReady()) {
            return $map;
        }
        foreach ($this->documents((int)$source->id) as $doc) {
            if ((int)$doc['version'] < 1) {
                continue;
            }
            Yii::$app->db->createCommand()->insert('{{%custom_form_consent_document}}', [
                'form_id' => (int)$target->id,
                'version' => (int)$doc['version'],
                'title' => $doc['title'],
                'body_html' => $doc['body_html'],
                'approval_reference' => $doc['approval_reference'],
                'effective_on' => $doc['effective_on'],
                'content_hash' => $doc['content_hash'],
                'status' => $doc['status'],
                'published_at' => $doc['published_at'],
                'created_at' => gmdate('Y-m-d H:i:s'),
            ])->execute();
            $newId = (int)Yii::$app->db->getLastInsertID();
            $map[(int)$doc['id']] = $newId;
            foreach ($this->items((int)$doc['id']) as $item) {
                Yii::$app->db->createCommand()->insert('{{%custom_form_consent_item}}', [
                    'document_id' => $newId,
                    'code' => $item['code'],
                    'label' => $item['label'],
                    'required' => (int)$item['required'],
                    'input' => $item['input'],
                    'sort_order' => (int)$item['sort_order'],
                ])->execute();
            }
        }
        if (!$map) {
            return $map;
        }
        foreach (FormField::find()->where(['form_id' => (int)$target->id, 'type' => FormField::TYPE_CONSENT])->all() as $field) {
            $cfg = $field->getConsentConfig();
            $old = (int)$cfg['document_id'];
            if ($old > 0 && isset($map[$old])) {
                $cfg['document_id'] = $map[$old];
                $field->options_json = json_encode([
                    'document_id' => $cfg['document_id'],
                    'must_read' => $cfg['must_read'],
                    'signature' => $cfg['signature'],
                    'witness' => $cfg['witness'],
                ], JSON_UNESCAPED_UNICODE);
                $field->save(false, ['options_json']);
            }
        }
        return $map;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function latestPublished(CustomForm $form): ?array
    {
        if (!$this->tablesReady()) {
            return null;
        }
        $row = (new Query())->from('{{%custom_form_consent_document}}')
            ->where(['form_id' => (int)$form->id, 'status' => 'published'])
            ->andWhere(['>', 'version', 0])
            ->orderBy(['version' => SORT_DESC])
            ->one();
        return $row ?: null;
    }

    private function unlink(CustomForm $form): bool
    {
        return $form->hidesIdentityFromManagers() && Module::identityEnforced();
    }

    /**
     * @param array<string,mixed> $doc
     */
    private function newerPublished(CustomForm $form, array $doc): bool
    {
        $latest = $this->latestPublished($form);
        return $latest && (int)$latest['version'] > (int)$doc['version'];
    }

    /**
     * @param array<int,array<string,mixed>> $postedItems
     */
    private function replaceItems(int $documentId, array $postedItems): void
    {
        Yii::$app->db->createCommand()->delete('{{%custom_form_consent_item}}', ['document_id' => $documentId])->execute();
        $order = 0;
        $seen = [];
        foreach ($postedItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $code = strtolower(trim((string)($item['code'] ?? '')));
            $code = preg_replace('/[^a-z0-9_]/', '', $code) ?? '';
            if ($code === '' || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            Yii::$app->db->createCommand()->insert('{{%custom_form_consent_item}}', [
                'document_id' => $documentId,
                'code' => $code,
                'label' => trim((string)($item['label'] ?? '')),
                'required' => !empty($item['required']) ? 1 : 0,
                'input' => (($item['input'] ?? '') === 'checkbox') ? 'checkbox' : 'yes_no',
                'sort_order' => $order++,
            ])->execute();
        }
    }

    /**
     * @param array<string,mixed> $doc
     * @return array<int,array<string,mixed>>
     */
    private function presentedItems(array $doc, string $language): array
    {
        $items = $this->items((int)$doc['id']);
        if ($language === '') {
            return $items;
        }
        $row = (new Query())->from('{{%custom_form_consent_i18n}}')->where([
            'document_id' => (int)$doc['id'],
            'language' => $language,
        ])->one();
        $labels = $row ? json_decode((string)$row['items_json'], true) : [];
        if (!is_array($labels)) {
            return $items;
        }
        foreach ($items as $i => $item) {
            $label = trim((string)($labels[$item['code']] ?? ''));
            if ($label !== '') {
                $items[$i]['label'] = $label;
            }
        }
        return $items;
    }

    /**
     * @param array<string,mixed> $doc
     */
    private function presentedBody(array $doc, string $language): string
    {
        if ($language !== '') {
            $row = (new Query())->from('{{%custom_form_consent_i18n}}')->where([
                'document_id' => (int)$doc['id'],
                'language' => $language,
            ])->one();
            if ($row && trim((string)$row['body_html']) !== '') {
                return (string)$row['body_html'];
            }
        }
        return (string)$doc['body_html'];
    }

    /**
     * @param array<string,mixed> $doc
     * @param array<string,mixed> $bag
     * @return array<string,string>
     */
    private function decisions(array $doc, array $bag): array
    {
        $posted = is_array($bag['items'] ?? null) ? $bag['items'] : [];
        $out = [];
        foreach ($this->items((int)$doc['id']) as $item) {
            $raw = $posted[$item['code']] ?? '';
            if (is_array($raw)) {
                $raw = end($raw);
            }
            $raw = strtolower(trim((string)$raw));
            if (in_array($raw, ['1', 'yes', 'on', 'true'], true)) {
                $out[$item['code']] = 'yes';
            } elseif (in_array($raw, ['0', 'no', 'off', 'false'], true)) {
                $out[$item['code']] = 'no';
            } else {
                $out[$item['code']] = '';
            }
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $bag
     */
    private function assertSignature(SubmitForm $submit, CustomForm $form, FormField $field, array $bag): void
    {
        $cfg = $field->getConsentConfig();
        $this->assertSignatureMethod(
            $submit,
            $this->effectiveSignature($form, (string)$cfg['signature']),
            $this->effectiveWitness($form, (bool)$cfg['witness']),
            (bool)$cfg['must_read'],
            $bag
        );
    }

    /**
     * The page posts the hash of the text it rendered. If the document or its translation
     * changed since then, the participant is asked to read it again (V3-27).
     *
     * @param array{hash:string} $shown
     * @param array<string,mixed> $bag
     */
    private function assertShown(SubmitForm $submit, array $shown, array $bag): bool
    {
        $posted = (string)($bag['shown_hash'] ?? '');
        if ($posted !== '' && !hash_equals($shown['hash'], $posted)) {
            $submit->addError('values', Yii::t('ThiscoveryFormsModule.base', 'The consent information changed while this page was open. Please read it again and confirm.'));
            return false;
        }
        return true;
    }

    /**
     * @param array<string,mixed> $bag
     */
    /**
     * The configured method is enforced; the posted signature_method is ignored (V3-28).
     *
     * @param array<string,mixed> $bag
     */
    private function assertSignatureMethod(SubmitForm $submit, string $method, bool $witness, bool $mustRead, array $bag): void
    {
        $name = trim((string)($bag['signature_name'] ?? ''));
        if ($method === 'checkbox' && empty($bag['attestation'])) {
            $submit->addError('values', Yii::t('ThiscoveryFormsModule.base', 'Tick the consent attestation.'));
        }
        if ($method === 'typed' && $name === '') {
            $submit->addError('values', Yii::t('ThiscoveryFormsModule.base', 'Type your name to sign.'));
        }
        if ($method === 'drawn' && $name === '' && $this->signaturePng((string)($bag['signature_image'] ?? '')) === null) {
            $submit->addError('values', Yii::t('ThiscoveryFormsModule.base', 'Draw your signature, or type your name to sign.'));
        }
        if ($witness && (trim((string)($bag['witness_name'] ?? '')) === '' || trim((string)($bag['witness_role'] ?? '')) === '')) {
            $submit->addError('values', Yii::t('ThiscoveryFormsModule.base', 'Enter the witness name and role.'));
        }
        if ($mustRead && (string)($bag['scrolled_to_end'] ?? '') !== '1') {
            $submit->addError('values', Yii::t('ThiscoveryFormsModule.base', 'Read the information sheet to the end before you confirm.'));
        }
    }

    /**
     * @param array<string,mixed> $doc
     * @param array{language:string,title:string,body:string,items:array,hash:string} $shown
     * @param array<string,string> $decisions
     * @param array<string,mixed> $bag
     */
    private function insertRecord(
        CustomForm $form,
        FormAnswer $answer,
        array $doc,
        array $shown,
        array $decisions,
        array $bag,
        string $method,
        bool $unlink,
        bool $refused
    ): void {
        if (!$unlink && $answer->id) {
            $existing = (new Query())->from('{{%custom_form_consent_record}}')->where([
                'answer_id' => (int)$answer->id,
                'document_id' => (int)$doc['id'],
            ])->exists();
            if ($existing) {
                return;
            }
        }
        // Fully anonymous: nothing that identifies the person or times the submit (V3-26).
        // With no name, drawing, witness, client hash or time, the record adds nothing an
        // answer does not already hold, so its sequential id is not a link to a person.
        $strip = $this->unlink($form);
        $name = $strip || $method === 'checkbox' ? '' : trim((string)($bag['signature_name'] ?? ''));
        $fileId = !$strip && $method === 'drawn' ? $this->storeSignatureImage($form, (string)($bag['signature_image'] ?? '')) : null;
        $witnessName = $strip ? '' : trim((string)($bag['witness_name'] ?? ''));
        $witnessRole = $strip ? '' : trim((string)($bag['witness_role'] ?? ''));
        $token = bin2hex(random_bytes(16));
        $ipHash = null;
        $uaHash = null;
        if (!$strip && in_array((string)$form->getSetting('consent_store_client_hashes', '0'), ['1', 'true', 'on'], true)) {
            $salt = (string)$form->getSetting('consent_client_salt', '');
            if ($salt === '') {
                $salt = bin2hex(random_bytes(16));
                $form->setSetting('consent_client_salt', $salt);
                $form->save(false, ['settings_json']);
            }
            $ip = (string)Yii::$app->request->userIP;
            $ua = (string)Yii::$app->request->userAgent;
            $ipHash = $ip !== '' ? hash('sha256', $salt . '|' . $ip) : null;
            $uaHash = $ua !== '' ? hash('sha256', $salt . '|' . $ua) : null;
        }
        $memberId = $unlink ? null : ($answer->panel_member_id ? (int)$answer->panel_member_id : null);
        $userId = $unlink ? null : ($answer->created_by ? (int)$answer->created_by : null);
        Yii::$app->db->createCommand()->insert('{{%custom_form_consent_record}}', [
            'form_id' => (int)$form->id,
            'answer_id' => $unlink ? null : (int)$answer->id,
            'user_id' => $userId,
            'panel_member_id' => $memberId,
            'document_id' => (int)$doc['id'],
            'content_hash' => $shown['hash'],
            'items_json' => json_encode($decisions, JSON_UNESCAPED_UNICODE),
            'signature_method' => $method,
            'signature_name' => $name !== '' ? $name : null,
            'signature_file_id' => $fileId,
            'witness_name' => $witnessName !== '' ? $witnessName : null,
            'witness_role' => $witnessRole !== '' ? $witnessRole : null,
            'signed_at' => $strip ? gmdate('Y-m-d') . ' 00:00:00' : gmdate('Y-m-d H:i:s'),
            'language' => $shown['language'],
            'channel' => $witnessName !== '' ? 'assisted' : 'self',
            'ip_hash' => $ipHash,
            'ua_hash' => $uaHash,
            'withdrawal_token_hash' => hash('sha256', $token),
            'scrolled_to_end' => (string)($bag['scrolled_to_end'] ?? '') === '1' ? 1 : 0,
        ])->execute();
        $recordId = (int)Yii::$app->db->getLastInsertID();
        Yii::$app->session->set('cf-consent-withdraw', $token);
        $this->audit($recordId, $refused ? 'refused' : 'given', [
            'document_id' => (int)$doc['id'],
            'refused' => $refused,
            'items' => $decisions,
        ], $userId, $strip);
        if (!$unlink && !$refused && ($memberId || $userId)) {
            $this->satisfyRequirement($form, $doc, $recordId, $memberId, $userId);
            $member = $memberId ? FormPanelMember::findOne($memberId) : null;
            if ($member) {
                $member->markConsent($recordId);
            }
        }
    }

    /**
     * @param array<string,mixed> $doc
     */
    private function satisfyRequirement(CustomForm $form, array $doc, int $recordId, ?int $memberId, ?int $userId): void
    {
        $latest = $this->latestPublished($form);
        if (!$latest || (int)$latest['id'] !== (int)$doc['id']) {
            return;
        }
        $where = [
            'form_id' => (int)$form->id,
            'document_id' => (int)$doc['id'],
            'panel_member_id' => $memberId,
            'user_id' => $userId,
        ];
        $row = (new Query())->from('{{%custom_form_consent_requirement}}')->where($where)->one();
        if ($row) {
            Yii::$app->db->createCommand()->update('{{%custom_form_consent_requirement}}', [
                'satisfied_record_id' => $recordId,
            ], ['id' => (int)$row['id']])->execute();
            return;
        }
        Yii::$app->db->createCommand()->insert('{{%custom_form_consent_requirement}}', $where + [
            'satisfied_record_id' => $recordId,
        ])->execute();
    }

    /**
     * @param array<string,mixed> $record
     */
    private function writeWithdrawal(array $record, string $scope, string $reason, ?int $actorId): void
    {
        $memberId = $record['panel_member_id'] ? (int)$record['panel_member_id'] : null;
        Yii::$app->db->createCommand()->insert('{{%custom_form_consent_withdrawal}}', [
            'record_id' => (int)$record['id'],
            'panel_member_id' => $memberId,
            'user_id' => $record['user_id'] ? (int)$record['user_id'] : null,
            'scope' => $scope,
            'reason' => $reason,
            'actor_id' => $actorId,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ])->execute();
        $this->audit((int)$record['id'], 'withdrawn', ['scope' => $scope], $actorId);
        // A withdrawn record no longer satisfies a consent requirement.
        Yii::$app->db->createCommand()->update('{{%custom_form_consent_requirement}}', [
            'satisfied_record_id' => null,
        ], ['satisfied_record_id' => (int)$record['id']])->execute();
        if (in_array($scope, ['stop_contact', 'delete_requested'], true)) {
            // Reach the person however they are known: the record's panel member, and every
            // panel membership of the record's account (V3-43).
            $members = [];
            if ($memberId) {
                $members[] = $memberId;
            }
            if (!empty($record['user_id'])) {
                foreach (FormPanelMember::find()->select('id')->where(['user_id' => (int)$record['user_id']])->column() as $id) {
                    $members[] = (int)$id;
                }
            }
            foreach (FormPanelMember::find()->where(['id' => array_values(array_unique($members))])->all() as $member) {
                $member->status = FormPanelMember::STATUS_INACTIVE;
                $member->save(false, ['status', 'updated_at']);
            }
        }
        if ($scope === 'delete_requested') {
            Yii::$app->db->createCommand()->insert('{{%custom_form_admin_task}}', [
                'kind' => 'erasure',
                'form_id' => (int)$record['form_id'],
                'member_id' => $memberId,
                'status' => 'open',
                'created_at' => gmdate('Y-m-d H:i:s'),
            ])->execute();
        }
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function audit(int $recordId, string $event, array $payload, ?int $actorId, bool $dateOnly = false): void
    {
        unset($payload['signature_image']);
        Yii::$app->db->createCommand()->insert('{{%custom_form_consent_audit}}', [
            'record_id' => $recordId,
            'event' => $event,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'actor_id' => $actorId,
            'created_at' => $dateOnly ? gmdate('Y-m-d') . ' 00:00:00' : gmdate('Y-m-d H:i:s'),
        ])->execute();
    }

    /** A drawn signature: a real PNG, at most 200 KB, with the canvas's dimensions. */
    private function signaturePng(string $dataUrl): ?string
    {
        $prefix = 'data:image/png;base64,';
        if (!str_starts_with($dataUrl, $prefix) || strlen($dataUrl) > 280000) {
            return null;
        }
        $binary = base64_decode(substr($dataUrl, strlen($prefix)), true);
        if ($binary === false || strlen($binary) < 33 || strlen($binary) > 200000 || !str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            return null;
        }
        $size = @getimagesizefromstring($binary);
        if (!is_array($size) || $size[0] < 1 || $size[1] < 1 || $size[0] > 2000 || $size[1] > 1000) {
            return null;
        }
        return $binary;
    }

    private function storeSignatureImage(CustomForm $form, string $dataUrl): ?int
    {
        $binary = $this->signaturePng($dataUrl);
        if ($binary === null) {
            return null;
        }
        try {
            $file = new File();
            $file->file_name = 'consent-signature.png';
            $file->mime_type = 'image/png';
            $file->size = strlen($binary);
            $file->object_model = CustomForm::class;
            $file->object_id = (int)$form->id;
            if (!$file->save(false)) {
                return null;
            }
            $file->setStoredFileContent($binary);
            return (int)$file->id;
        } catch (\Throwable $e) {
            Yii::warning('Consent signature image was not stored: ' . $e->getMessage(), 'thiscovery-forms');
            return null;
        }
    }

    private function queueReconsent(CustomForm $form, int $documentId): void
    {
        $templateId = (int)$form->getSetting('completion_email_template_id', 0);
        if ($templateId < 1) {
            return;
        }
        $template = \humhub\modules\thiscoveryForms\models\FormEmailTemplate::findOne($templateId);
        if (!$template) {
            return;
        }
        $doc = $this->document($documentId, (int)$form->id);
        if (!$doc) {
            return;
        }
        $mailer = new EmailTemplateService();
        $seen = [];
        foreach ($this->records((int)$form->id) as $record) {
            if ((string)$record['signature_method'] === 'legacy' || !$record['panel_member_id']) {
                continue;
            }
            $memberId = (int)$record['panel_member_id'];
            if (isset($seen[$memberId])) {
                continue;
            }
            $seen[$memberId] = true;
            $older = (new Query())->from(['r' => '{{%custom_form_consent_record}}'])
                ->innerJoin(['d' => '{{%custom_form_consent_document}}'], 'd.id = r.document_id')
                ->where(['r.panel_member_id' => $memberId, 'r.form_id' => (int)$form->id])
                ->andWhere(['<', 'd.version', (int)$doc['version']])
                ->exists();
            if (!$older) {
                continue;
            }
            $member = FormPanelMember::findOne($memberId);
            if (!$member || !$member->isActive()) {
                continue;
            }
            $to = strtolower(trim((string)$member->email));
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $mailer->sendTemplate($template, $to, $mailer->varsFor($form, $member), [
                'form_id' => (int)$form->id,
                'member_id' => $memberId,
                'kind' => 'consent_reconsent',
            ]);
        }
    }

    private function ensureLegacyDocument(int $formId): int
    {
        $row = (new Query())->from('{{%custom_form_consent_document}}')->where([
            'form_id' => $formId,
            'version' => 0,
        ])->one();
        if ($row) {
            return (int)$row['id'];
        }
        Yii::$app->db->createCommand()->insert('{{%custom_form_consent_document}}', [
            'form_id' => $formId,
            'version' => 0,
            'title' => 'Legacy / unverified',
            'body_html' => '',
            'approval_reference' => '',
            'status' => 'published',
            'published_at' => gmdate('Y-m-d H:i:s'),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ])->execute();
        return (int)Yii::$app->db->getLastInsertID();
    }

    private function dateOrNull($value): ?string
    {
        $value = trim((string)$value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        return $value;
    }
}
