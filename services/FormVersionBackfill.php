<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryVersioning\models\VersionEdition;
use humhub\modules\thiscoveryVersioning\models\VersionOpenPeriod;
use humhub\modules\thiscoveryVersioning\models\VersionRevision;
use Yii;

/**
 * One-time / idempotent backfill of editions for existing forms.
 */
class FormVersionBackfill
{
    public function run(): int
    {
        if (!FormVersionService::isAvailable()) {
            return 0;
        }

        $snapshots = new FormSnapshotService();
        $count = 0;
        $forms = CustomForm::find()->where(['is_template' => 0])->all();
        foreach ($forms as $form) {
            /** @var CustomForm $form */
            if ($form->current_edition_id) {
                continue;
            }

            $needsEdition = (int)$form->status === CustomForm::STATUS_OPEN
                || FormAnswer::find()->where(['form_id' => $form->id])->exists();
            if (!$needsEdition) {
                continue;
            }

            $existing = VersionRevision::find()
                ->where(['owner_type' => FormVersionAdapter::OWNER_TYPE, 'owner_id' => $form->id])
                ->count();
            if ($existing) {
                $edition = VersionEdition::find()
                    ->where(['owner_type' => FormVersionAdapter::OWNER_TYPE, 'owner_id' => $form->id, 'is_current' => 1])
                    ->one();
                if ($edition) {
                    $form->updateAttributes(['current_edition_id' => $edition->id]);
                    FormAnswer::updateAll(
                        ['edition_id' => $edition->id],
                        ['and', ['form_id' => $form->id], ['edition_id' => null]]
                    );
                }
                continue;
            }

            $rev = new VersionRevision();
            $rev->owner_type = FormVersionAdapter::OWNER_TYPE;
            $rev->owner_id = (int)$form->id;
            $rev->revision_number = 1;
            $rev->label = Yii::t('ThiscoveryFormsModule.base', 'Migrated from existing form');
            $rev->availability_status = (string)(int)$form->status;
            $rev->setSnapshot($snapshots->export($form));
            $rev->setChangeSummary(['Initial migrated snapshot']);
            $rev->created_at = date('Y-m-d H:i:s');
            $rev->created_by = null;
            $rev->save(false);

            $edition = new VersionEdition();
            $edition->owner_type = FormVersionAdapter::OWNER_TYPE;
            $edition->owner_id = (int)$form->id;
            $edition->edition_number = 1;
            $edition->revision_id = (int)$rev->id;
            $edition->is_current = 1;
            $edition->published_at = date('Y-m-d H:i:s');
            $edition->published_by = null;
            $edition->save(false);

            $form->updateAttributes(['current_edition_id' => $edition->id]);
            FormAnswer::updateAll(
                ['edition_id' => $edition->id],
                ['and', ['form_id' => $form->id], ['edition_id' => null]]
            );

            if ((int)$form->status === CustomForm::STATUS_OPEN) {
                $open = VersionOpenPeriod::find()
                    ->where([
                        'owner_type' => FormVersionAdapter::OWNER_TYPE,
                        'owner_id' => $form->id,
                        'closed_at' => null,
                    ])
                    ->one();
                if (!$open) {
                    $period = new VersionOpenPeriod();
                    $period->owner_type = FormVersionAdapter::OWNER_TYPE;
                    $period->owner_id = (int)$form->id;
                    $period->edition_id = (int)$edition->id;
                    $period->opened_at = $form->content->updated_at ?? date('Y-m-d H:i:s');
                    $period->save(false);
                }
            }
            $count++;
        }

        return $count;
    }
}
