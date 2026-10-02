<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryVersioning\models\VersionEdition;
use humhub\modules\thiscoveryVersioning\models\VersionRevision;
use humhub\modules\thiscoveryVersioning\services\VersioningService;
use Yii;

/**
 * Forms-facing helpers around the shared VersioningService.
 */
class FormVersionService
{
    private VersioningService $versions;
    private FormSnapshotService $snapshots;

    public function __construct(?VersioningService $versions = null, ?FormSnapshotService $snapshots = null)
    {
        $this->versions = $versions ?: new VersioningService();
        $this->snapshots = $snapshots ?: new FormSnapshotService();
    }

    public static function isAvailable(): bool
    {
        if (!Yii::$app->hasModule('thiscovery-versioning') || !class_exists(VersioningService::class)) {
            return false;
        }
        /** @var \humhub\modules\thiscoveryVersioning\Module|null $module */
        $module = Yii::$app->getModule('thiscovery-versioning');
        if (!$module instanceof \humhub\modules\thiscoveryVersioning\Module) {
            return false;
        }
        return $module->isOwnerEnabled(FormVersionAdapter::OWNER_TYPE);
    }

    public function versions(): VersioningService
    {
        return $this->versions;
    }

    public function recordSave(CustomForm $form): ?VersionRevision
    {
        if (!self::isAvailable() || !$form->id || $form->isTemplate()) {
            return null;
        }
        return $this->versions->createRevision(
            FormVersionAdapter::OWNER_TYPE,
            (int)$form->id,
            $this->snapshots->export($form),
            (string)(int)$form->status
        );
    }

    public function publish(CustomForm $form, ?int $revisionId = null): ?VersionEdition
    {
        if (!self::isAvailable() || !$form->id) {
            return null;
        }
        $quotaErrors = (new QuotaService())->authoringErrors($form);
        $formulaErrors = \humhub\modules\thiscoveryForms\services\formula\FormulaPolicy::authoringErrors($form);
        $blocked = array_merge($quotaErrors, $formulaErrors, LogicAudit::errors($form), (new Eq5dService())->authoringErrors($form));
        if ($blocked) {
            Yii::$app->session->setFlash('error', implode(' ', $blocked));
            return null;
        }
        return $this->versions->publishRevision(
            FormVersionAdapter::OWNER_TYPE,
            (int)$form->id,
            $revisionId
        );
    }

    public function hasPublishedEdition(CustomForm $form): bool
    {
        if ($form->current_edition_id) {
            return true;
        }
        if (!self::isAvailable() || !$form->id) {
            return false;
        }
        return (bool)$this->versions->currentEdition(FormVersionAdapter::OWNER_TYPE, (int)$form->id);
    }

    public function onAvailabilityChanged(CustomForm $form, int $previousStatus, int $newStatus): void
    {
        if (!self::isAvailable() || !$form->id) {
            return;
        }
        $ownerType = FormVersionAdapter::OWNER_TYPE;
        $ownerId = (int)$form->id;
        $periods = $this->versions->openPeriods();

        if ($previousStatus !== CustomForm::STATUS_OPEN && $newStatus === CustomForm::STATUS_OPEN) {
            $editionId = $form->current_edition_id
                ?: ($this->versions->currentEdition($ownerType, $ownerId)->id ?? null);
            $periods->open($ownerType, $ownerId, $editionId ? (int)$editionId : null);
        } elseif ($previousStatus === CustomForm::STATUS_OPEN && $newStatus !== CustomForm::STATUS_OPEN) {
            $periods->closeActive($ownerType, $ownerId);
        }
    }

    /**
     * For fill/preview: hydrate published (or historical) definition onto $form in memory.
     */
    public function applyFillDefinition(CustomForm $form, ?FormAnswer $answer = null, bool $isPreview = false): void
    {
        if (!self::isAvailable() || !$form->id) {
            return;
        }

        $revisionId = (int)Yii::$app->request->get('revision_id', 0);
        $editionId = (int)Yii::$app->request->get('edition_id', 0);

        if ($isPreview && ($revisionId || $editionId)) {
            if ($editionId) {
                $edition = $this->versions->findEdition($editionId);
                if (!$edition || (int)$edition->owner_id !== (int)$form->id) {
                    $this->markEditionUnavailable($form);
                    return;
                }
                $this->snapshots->hydrateInMemory($form, $edition->getSnapshot());
                $form->fillEditionId = (int)$edition->id;
                return;
            }
            $rev = $this->versions->findRevision($revisionId);
            if (!$rev || (int)$rev->owner_id !== (int)$form->id) {
                $this->markEditionUnavailable($form);
                return;
            }
            $this->snapshots->hydrateInMemory($form, $rev->getSnapshot());
            return;
        }

        if ($isPreview) {
            // Working draft preview — leave live fields.
            return;
        }

        $targetEditionId = null;
        if ($answer && $answer->edition_id) {
            $targetEditionId = (int)$answer->edition_id;
        } else {
            $current = $this->versions->currentEdition(FormVersionAdapter::OWNER_TYPE, (int)$form->id);
            $targetEditionId = $current
                ? (int)$current->id
                : ($form->current_edition_id ? (int)$form->current_edition_id : null);
        }

        if (!$targetEditionId) {
            return;
        }
        $edition = $this->versions->findEdition($targetEditionId);
        if (!$edition || (int)$edition->owner_id !== (int)$form->id) {
            $this->markEditionUnavailable($form);
            return;
        }
        $this->snapshots->hydrateInMemory($form, $edition->getSnapshot());
        $form->fillEditionId = (int)$edition->id;
    }

    /**
     * A missing edition must not leave the working draft on the fill page.
     */
    public function markEditionUnavailable(CustomForm $form): void
    {
        $form->editionLoadFailed = true;
        $form->fillEditionId = null;
        $form->populateRelation('fields', []);
    }

    public function signEdition(int $formId, int $editionId): string
    {
        return Yii::$app->security->hashData($formId . ':' . $editionId, $this->editionKey());
    }

    public function readSignedEdition(string $token, int $formId): ?int
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        $data = Yii::$app->security->validateData($token, $this->editionKey());
        if (!is_string($data) || !preg_match('/^(\d+):(\d+)$/', $data, $match)) {
            return null;
        }
        if ((int)$match[1] !== $formId || (int)$match[2] < 1) {
            return null;
        }
        return (int)$match[2];
    }

    private function editionKey(): string
    {
        $key = '';
        if (Yii::$app->has('request')) {
            $key = (string)Yii::$app->request->cookieValidationKey;
        }
        return $key !== '' ? $key : 'thiscovery-forms-edition';
    }

    /**
     * Answerable questions from a published edition, or null when it cannot be loaded.
     *
     * @return FormField[]|null
     */
    public function editionFields(CustomForm $form, int $editionId): ?array
    {
        if (!self::isAvailable() || $editionId < 1 || !$form->id) {
            return null;
        }
        $edition = $this->versions->findEdition($editionId);
        if (!$edition || (int)$edition->owner_id !== (int)$form->id) {
            return null;
        }
        $copy = new CustomForm();
        $copy->id = (int)$form->id;
        $this->snapshots->hydrateInMemory($copy, $edition->getSnapshot());
        $fields = [];
        foreach ($copy->fields as $field) {
            if ($field->collectsAnswer()) {
                $fields[] = $field;
            }
        }
        return $fields;
    }

    public function stampAnswerEdition(CustomForm $form, FormAnswer $answer): void
    {
        if ($answer->edition_id || !$form->current_edition_id) {
            return;
        }
        $answer->updateAttributes(['edition_id' => (int)$form->current_edition_id]);
        $answer->edition_id = (int)$form->current_edition_id;
    }
}
