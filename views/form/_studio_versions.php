<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\services\FormVersionAdapter;
use humhub\modules\thiscoveryForms\services\FormVersionService;
use humhub\modules\thiscoveryVersioning\widgets\VersionsPanel;
use yii\helpers\Html;

/** @var CustomForm $formModel */

if (!FormVersionService::isAvailable() || !$formModel->id) {
    echo '<div class="alert alert-warning">'
        . Html::encode(Yii::t(
            'ThiscoveryFormsModule.base',
            'Versioning requires the Thiscovery Versioning module. Enable it in Administration → Modules.'
        ))
        . '</div>';
    return;
}

echo '<div class="cf-label-row mb-2"><p class="cf-hint text-muted mb-0">'
    . Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Publish an edition before you change the form status. Participants see the published edition, not unsaved studio edits.'))
    . '</p>'
    . $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'A revision is a saved copy. Publish freezes the edition people fill. Restore puts an older edition back into the studio. Status on Basics stays locked until an edition has been published.')])
    . '</div>';

$adapter = new FormVersionAdapter();
$ownerId = (int)$formModel->id;

echo VersionsPanel::widget([
    'ownerType' => FormVersionAdapter::OWNER_TYPE,
    'ownerId' => $ownerId,
    'canView' => $adapter->canViewVersions($ownerId),
    'canPublish' => $adapter->canPublishVersion($ownerId),
    'canRestore' => $adapter->canRestoreVersion($ownerId),
    'canDelete' => $adapter->canDeleteVersion($ownerId),
    'actionUrls' => [
        'publish' => $formModel->isGlobal()
            ? \yii\helpers\Url::to(['/thiscovery-forms/global/publish-version', 'id' => $ownerId])
            : $formModel->content->container->createUrl('/thiscovery-forms/form/publish-version', ['id' => $ownerId]),
        'restore' => $formModel->isGlobal()
            ? \yii\helpers\Url::to(['/thiscovery-forms/global/restore-version', 'id' => $ownerId])
            : $formModel->content->container->createUrl('/thiscovery-forms/form/restore-version', ['id' => $ownerId]),
        'deleteRevision' => $formModel->isGlobal()
            ? \yii\helpers\Url::to(['/thiscovery-forms/global/delete-revision', 'id' => $ownerId])
            : $formModel->content->container->createUrl('/thiscovery-forms/form/delete-revision', ['id' => $ownerId]),
        'deleteEdition' => $formModel->isGlobal()
            ? \yii\helpers\Url::to(['/thiscovery-forms/global/delete-edition', 'id' => $ownerId])
            : $formModel->content->container->createUrl('/thiscovery-forms/form/delete-edition', ['id' => $ownerId]),
    ],
]);
