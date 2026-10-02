<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\user\models\User;
use yii\helpers\Html;

/** @var CustomForm[] $forms */
/** @var mixed $contentContainer */

$this->title = Yii::t('ThiscoveryFormsModule.base', 'Trash');
?>
<div class="panel panel-default cf-trash">
    <div class="panel-heading">
        <h1 class="h4"><?= Yii::t('ThiscoveryFormsModule.base', 'Trash') ?></h1>
        <p class="text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'Deleted forms keep their answers here until they are restored or deleted permanently.') ?></p>
        <?= Html::a(Yii::t('ThiscoveryFormsModule.base', 'Back to forms'), Url::toManageIndex($contentContainer)) ?>
    </div>
    <div class="panel-body">
        <?php if ($forms === []): ?>
            <p><?= Yii::t('ThiscoveryFormsModule.base', 'The trash is empty.') ?></p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Form') ?></th>
                    <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Deleted') ?></th>
                    <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Answers') ?></th>
                    <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Actions') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($forms as $form): ?>
                    <?php
                    $last = CustomForm::lifecycleLog((int)$form->id)[0] ?? null;
                    $by = $last && $last['actor_id'] ? User::findOne((int)$last['actor_id']) : null;
                    $count = (int)$form->getAnswers()->andWhere(['is_test' => 0])->count();
                    ?>
                    <tr>
                        <th scope="row"><?= Html::encode((string)$form->title) ?></th>
                        <td>
                            <?= $last ? Html::encode(Yii::$app->formatter->asDatetime($last['created_at'], 'short')) : '' ?>
                            <?= $by ? ' — ' . Html::encode((string)$by->displayName) : '' ?>
                            <?= $last && $last['reason'] ? '<br><span class="text-muted">' . Html::encode((string)$last['reason']) . '</span>' : '' ?>
                        </td>
                        <td><?= $count ?></td>
                        <td>
                            <?= Html::beginForm(Url::toRestoreForm($form), 'post', ['class' => 'd-inline']) ?>
                                <?= Html::submitButton(Yii::t('ThiscoveryFormsModule.base', 'Restore'), ['class' => 'btn btn-default btn-sm']) ?>
                            <?= Html::endForm() ?>
                            <details class="mt-2">
                                <summary><?= Yii::t('ThiscoveryFormsModule.base', 'Delete permanently') ?></summary>
                                <?= Html::beginForm(Url::toPurgeForm($form), 'post') ?>
                                    <p class="text-danger"><?= Yii::t('ThiscoveryFormsModule.base', 'This removes the form and all {n} answers for good. It cannot be undone.', ['n' => $count]) ?></p>
                                    <label for="cf-purge-<?= (int)$form->id ?>"><?= Yii::t('ThiscoveryFormsModule.base', 'Type the form title to confirm') ?></label>
                                    <?= Html::textInput('confirm_title', '', ['class' => 'form-control', 'id' => 'cf-purge-' . (int)$form->id, 'autocomplete' => 'off', 'required' => true]) ?>
                                    <label for="cf-purge-reason-<?= (int)$form->id ?>"><?= Yii::t('ThiscoveryFormsModule.base', 'Reason') ?></label>
                                    <?= Html::textInput('reason', '', ['class' => 'form-control', 'id' => 'cf-purge-reason-' . (int)$form->id, 'maxlength' => 255]) ?>
                                    <?= Html::submitButton(Yii::t('ThiscoveryFormsModule.base', 'Delete permanently'), ['class' => 'btn btn-danger btn-sm mt-2']) ?>
                                <?= Html::endForm() ?>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
