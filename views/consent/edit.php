<?php

use yii\helpers\Html;

/** @var \humhub\modules\thiscoveryForms\models\CustomForm $formModel */
/** @var array|null $document */
/** @var array $items */

$document = $document ?? null;
$frozen = $document && (string)$document['status'] === 'published';
?>
<div class="panel">
    <div class="panel-body">
        <?= Html::beginForm($formModel->actionUrl(['/thiscovery-forms/consent/edit', 'id' => $formModel->id, 'documentId' => $document['id'] ?? null])) ?>
        <div class="form-group">
            <label><?= Yii::t('ThiscoveryFormsModule.base', 'Title') ?></label>
            <input class="form-control" name="title" value="<?= Html::encode((string)($document['title'] ?? '')) ?>" <?= $frozen ? 'readonly' : '' ?>>
        </div>
        <div class="form-group">
            <label><?= Yii::t('ThiscoveryFormsModule.base', 'Approval reference') ?></label>
            <input class="form-control" name="approval_reference" value="<?= Html::encode((string)($document['approval_reference'] ?? '')) ?>" <?= $frozen ? 'readonly' : '' ?>>
        </div>
        <div class="form-group">
            <label><?= Yii::t('ThiscoveryFormsModule.base', 'Effective date') ?></label>
            <input class="form-control" name="effective_on" value="<?= Html::encode((string)($document['effective_on'] ?? '')) ?>" placeholder="YYYY-MM-DD" <?= $frozen ? 'readonly' : '' ?>>
        </div>
        <div class="form-group">
            <label><?= Yii::t('ThiscoveryFormsModule.base', 'Information') ?></label>
            <textarea class="form-control" name="body_html" rows="8" <?= $frozen ? 'readonly' : '' ?>><?= Html::encode((string)($document['body_html'] ?? '')) ?></textarea>
        </div>
        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Statements') ?></h4>
        <?php
        $rows = $items ?: [['code' => '', 'label' => '', 'required' => 0, 'input' => 'yes_no']];
        foreach (array_values($rows) as $i => $item): ?>
            <div class="row">
                <div class="col-md-3"><input class="form-control" name="items[<?= (int)$i ?>][code]" value="<?= Html::encode((string)($item['code'] ?? '')) ?>" placeholder="<?= Yii::t('ThiscoveryFormsModule.base', 'Code') ?>" <?= $frozen ? 'readonly' : '' ?>></div>
                <div class="col-md-5"><input class="form-control" name="items[<?= (int)$i ?>][label]" value="<?= Html::encode((string)($item['label'] ?? '')) ?>" placeholder="<?= Yii::t('ThiscoveryFormsModule.base', 'Statement') ?>" <?= $frozen ? 'readonly' : '' ?>></div>
                <div class="col-md-2"><select class="form-control" name="items[<?= (int)$i ?>][input]" <?= $frozen ? 'disabled' : '' ?>><option value="yes_no">yes/no</option><option value="checkbox" <?= (($item['input'] ?? '') === 'checkbox') ? 'selected' : '' ?>>checkbox</option></select></div>
                <div class="col-md-2"><label><input type="checkbox" name="items[<?= (int)$i ?>][required]" value="1" <?= !empty($item['required']) ? 'checked' : '' ?> <?= $frozen ? 'disabled' : '' ?>> <?= Yii::t('ThiscoveryFormsModule.base', 'Required') ?></label></div>
            </div>
        <?php endforeach; ?>
        <?php if (!$frozen): ?>
            <p>
                <button class="btn btn-default" type="submit"><?= Yii::t('ThiscoveryFormsModule.base', 'Save draft') ?></button>
                <button class="btn btn-primary" type="submit" name="publish" value="1"><?= Yii::t('ThiscoveryFormsModule.base', 'Publish version') ?></button>
            </p>
        <?php else: ?>
            <p><button class="btn btn-primary" type="submit" name="new_version" value="1"><?= Yii::t('ThiscoveryFormsModule.base', 'Start the next version') ?></button></p>
        <?php endif; ?>
        <?= Html::endForm() ?>
    </div>
</div>
