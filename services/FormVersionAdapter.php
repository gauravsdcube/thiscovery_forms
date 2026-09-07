<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryVersioning\interfaces\VersionableAdapter;
use humhub\modules\thiscoveryVersioning\models\VersionEdition;
use humhub\modules\thiscoveryVersioning\permissions\DeleteVersion;
use humhub\modules\thiscoveryVersioning\permissions\PublishVersion;
use humhub\modules\thiscoveryVersioning\permissions\RestoreVersion;
use humhub\modules\thiscoveryVersioning\permissions\ViewVersions;
use Yii;

class FormVersionAdapter implements VersionableAdapter
{
    public const OWNER_TYPE = 'form';

    private FormSnapshotService $snapshots;

    public function __construct(?FormSnapshotService $snapshots = null)
    {
        $this->snapshots = $snapshots ?: new FormSnapshotService();
    }

    public function getOwnerType(): string
    {
        return self::OWNER_TYPE;
    }

    public function exportSnapshot(int $ownerId): array
    {
        $form = CustomForm::findOne($ownerId);
        if (!$form) {
            return [];
        }
        return $this->snapshots->export($form);
    }

    public function importSnapshot(int $ownerId, array $snapshot): bool
    {
        $form = CustomForm::findOne($ownerId);
        if (!$form) {
            return false;
        }
        return $this->snapshots->import($form, $snapshot, true);
    }

    public function canDeleteEdition(int $ownerId, int $editionId): bool
    {
        return !FormAnswer::find()
            ->where(['form_id' => $ownerId, 'edition_id' => $editionId])
            ->exists();
    }

    public function canDeleteRevision(int $ownerId, int $revisionId): bool
    {
        $editions = VersionEdition::find()
            ->where(['owner_type' => self::OWNER_TYPE, 'owner_id' => $ownerId, 'revision_id' => $revisionId])
            ->all();
        foreach ($editions as $edition) {
            if (!$this->canDeleteEdition($ownerId, (int)$edition->id)) {
                return false;
            }
            if ((int)$edition->is_current === 1) {
                return false;
            }
        }
        return true;
    }

    public function canViewVersions(int $ownerId, $user = null): bool
    {
        return $this->perm($ownerId, ViewVersions::class, $user);
    }

    public function canRestoreVersion(int $ownerId, $user = null): bool
    {
        return $this->perm($ownerId, RestoreVersion::class, $user);
    }

    public function canPublishVersion(int $ownerId, $user = null): bool
    {
        return $this->perm($ownerId, PublishVersion::class, $user);
    }

    public function canDeleteVersion(int $ownerId, $user = null): bool
    {
        return $this->perm($ownerId, DeleteVersion::class, $user);
    }

    public function getPreviewUrl(int $ownerId, ?int $revisionId = null, ?int $editionId = null): string
    {
        $form = CustomForm::findOne($ownerId);
        if (!$form || !$form->canManage()) {
            return '';
        }
        $url = Url::toPreview($form, false);
        $params = [];
        if ($editionId) {
            $params['edition_id'] = $editionId;
        } elseif ($revisionId) {
            $params['revision_id'] = $revisionId;
        }
        if (!$params) {
            return $url;
        }
        $sep = str_contains($url, '?') ? '&' : '?';
        return $url . $sep . http_build_query($params);
    }

    public function onEditionPublished(int $ownerId, int $editionId): void
    {
        $form = CustomForm::findOne($ownerId);
        if (!$form) {
            return;
        }
        $form->updateAttributes(['current_edition_id' => $editionId]);

        // If the form is currently Open, stamp the active open period with the new edition.
        if ((int)$form->status === CustomForm::STATUS_OPEN
            && FormVersionService::isAvailable()) {
            $active = (new FormVersionService())->versions()->openPeriods()
                ->findActive(self::OWNER_TYPE, $ownerId);
            if ($active) {
                $active->updateAttributes(['edition_id' => $editionId]);
            }
        }
    }

    public function formatAvailabilityStatus(?string $status): string
    {
        if ($status === null || $status === '') {
            return '—';
        }
        $labels = CustomForm::getStatusLabels();
        $int = (int)$status;
        return $labels[$int] ?? (string)$status;
    }

    protected function perm(int $ownerId, string $permissionClass, $user = null): bool
    {
        $form = CustomForm::findOne($ownerId);
        if (!$form || !$form->canManage($user)) {
            return false;
        }
        // Manage form implies version ops by default; explicit permission still checked when present.
        try {
            if ($form->isGlobal()) {
                return Yii::$app->user->can($permissionClass) || $form->canManage($user);
            }
            $container = $form->content->container ?? null;
            if ($container && method_exists($container, 'getPermissionManager')) {
                return $container->getPermissionManager($user)->can($permissionClass) || $form->canManage($user);
            }
        } catch (\Throwable $e) {
            // Fall through to manage-form.
        }
        return $form->canManage($user);
    }
}
