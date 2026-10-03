<?php

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\SecureEvent;
use humhub\modules\thiscoveryForms\models\SecureRelease;
use humhub\modules\thiscoveryForms\services\ExportService;
use humhub\modules\thiscoveryForms\services\SecureSendService;
use yii\helpers\Html;

/** @var CustomForm $formModel */

$minutes = SecureSendService::minutes($formModel);
$releases = [];
if ($formModel->id && Yii::$app->db->schema->getTableSchema(SecureRelease::tableName(), true)) {
    $releases = SecureRelease::find()->where(['form_id' => (int)$formModel->id])->orderBy(['id' => SORT_DESC])->all();
}
$links = (new SecureSendService())->pullLinks();
$draft = Yii::$app->session->get('cfSecureDraft');
$draftParams = is_array($draft) && (int)($draft['formId'] ?? 0) === (int)$formModel->id && is_array($draft['params'] ?? null)
    ? $draft['params']
    : [];
$export = $draftParams ?: [
    'header_mode' => (string)Yii::$app->request->get('header_mode', ExportService::HEADER_LABEL),
    'include_in_progress' => (string)Yii::$app->request->get('include_in_progress', '0'),
    'include_excluded' => (string)Yii::$app->request->get('include_excluded', '0'),
];
$headerMode = (string)($export['header_mode'] ?? ExportService::HEADER_LABEL);
if (!isset(ExportService::headerModeLabels()[$headerMode])) {
    $headerMode = ExportService::HEADER_LABEL;
}
?>
<div class="cf-studio__settings cf-secure-settings">
    <h3 class="cf-settings-pane__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Secure send') ?></h3>
    <p class="cf-hint text-muted">
        <?= Yii::t('ThiscoveryFormsModule.base', 'Prepare a frozen file for one named contact. They open a private link and enter a one-time code emailed to them. They do not need an account. Only form managers can prepare, change, or revoke a file.') ?>
    </p>

    <div class="cf-secure-lifetime">
        <label>
            <?= Yii::t('ThiscoveryFormsModule.base', 'Code lifetime (minutes)') ?>
            <?= Html::input('number', 'minutes', $minutes, [
                'data-cf-target' => 'cf-secure-lifetime',
                'data-cf-required' => '1',
                'class' => 'form-control',
                'min' => SecureSendService::MIN_MINUTES,
                'max' => SecureSendService::MAX_MINUTES,
            ]) ?>
        </label>
        <button type="button" class="btn btn-default" data-cf-target="cf-secure-lifetime">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Save lifetime') ?>
        </button>
        <p class="cf-hint text-muted">
            <?= Yii::t('ThiscoveryFormsModule.base', 'From 5 minutes to 7 days. The next code uses this. A code already sent keeps the expiry it was given.') ?>
        </p>
    </div>

    <div class="cf-secure-create">
        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Prepare from answers') ?></h4>
        <?php if ($draftParams): ?>
            <p class="cf-hint"><?= Yii::t('ThiscoveryFormsModule.base', 'These choices were taken from the Answers download. You can change them before preparing the file.') ?></p>
        <?php endif; ?>
        <label>
            <?= Yii::t('ThiscoveryFormsModule.base', 'File name') ?>
            <?= Html::textInput('label', Yii::t('ThiscoveryFormsModule.base', 'Answers CSV'), [
                'data-cf-target' => 'cf-secure-prepare',
                'data-cf-required' => '1',
                'class' => 'form-control',
                'maxlength' => 160,
            ]) ?>
        </label>
        <label>
            <?= Yii::t('ThiscoveryFormsModule.base', 'Contact name') ?>
            <?= Html::textInput('contact_name', '', [
                'data-cf-target' => 'cf-secure-prepare',
                'data-cf-required' => '1',
                'class' => 'form-control',
                'maxlength' => 160,
            ]) ?>
        </label>
        <label>
            <?= Yii::t('ThiscoveryFormsModule.base', 'Contact email') ?>
            <?= Html::input('email', 'contact_email', '', [
                'data-cf-target' => 'cf-secure-prepare',
                'data-cf-required' => '1',
                'class' => 'form-control',
                'maxlength' => 190,
            ]) ?>
        </label>
        <label>
            <?= Yii::t('ThiscoveryFormsModule.base', 'Column headings') ?>
            <?= Html::dropDownList('header_mode', $headerMode, ExportService::headerModeLabels(), [
                'data-cf-target' => 'cf-secure-prepare',
                'class' => 'form-control',
            ]) ?>
        </label>
        <label class="cf-secure-check">
            <?= Html::checkbox('include_in_progress', (string)($export['include_in_progress'] ?? '0') === '1', [
                'data-cf-target' => 'cf-secure-prepare',
                'value' => '1',
                'uncheck' => null,
            ]) ?>
            <?= Yii::t('ThiscoveryFormsModule.base', 'Include unfinished responses') ?>
        </label>
        <label class="cf-secure-check">
            <?= Html::checkbox('include_excluded', (string)($export['include_excluded'] ?? '0') === '1', [
                'data-cf-target' => 'cf-secure-prepare',
                'value' => '1',
                'uncheck' => null,
            ]) ?>
            <?= Yii::t('ThiscoveryFormsModule.base', 'Include responses excluded from analysis') ?>
        </label>
        <?php foreach (['status', 'q', 'integrity', 'min_score'] as $carry): ?>
            <?php if ((string)($export[$carry] ?? '') !== ''): ?>
                <?= Html::hiddenInput($carry, (string)$export[$carry], ['data-cf-target' => 'cf-secure-prepare']) ?>
            <?php endif; ?>
        <?php endforeach; ?>
        <button type="button" class="btn btn-primary" data-cf-target="cf-secure-prepare">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Prepare file') ?>
        </button>
    </div>

    <div class="cf-secure-create">
        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Upload a file') ?></h4>
        <p class="cf-hint text-muted">
            <?= Yii::t('ThiscoveryFormsModule.base', 'CSV, Excel, PDF, JSON, text, and zip files up to 20 MB. The file is downloaded as an attachment.') ?>
        </p>
        <label>
            <?= Yii::t('ThiscoveryFormsModule.base', 'File name') ?>
            <?= Html::textInput('label', '', [
                'data-cf-target' => 'cf-secure-upload',
                'data-cf-required' => '1',
                'class' => 'form-control',
                'maxlength' => 160,
            ]) ?>
        </label>
        <label>
            <?= Yii::t('ThiscoveryFormsModule.base', 'Contact name') ?>
            <?= Html::textInput('contact_name', '', [
                'data-cf-target' => 'cf-secure-upload',
                'data-cf-required' => '1',
                'class' => 'form-control',
                'maxlength' => 160,
            ]) ?>
        </label>
        <label>
            <?= Yii::t('ThiscoveryFormsModule.base', 'Contact email') ?>
            <?= Html::input('email', 'contact_email', '', [
                'data-cf-target' => 'cf-secure-upload',
                'data-cf-required' => '1',
                'class' => 'form-control',
                'maxlength' => 190,
            ]) ?>
        </label>
        <label>
            <?= Yii::t('ThiscoveryFormsModule.base', 'File') ?>
            <?= Html::fileInput('secure_file', null, [
                'data-cf-target' => 'cf-secure-upload',
                'data-cf-required' => '1',
                'class' => 'form-control',
                'accept' => '.csv,.txt,.tsv,.xlsx,.xls,.pdf,.zip,.json',
            ]) ?>
        </label>
        <button type="button" class="btn btn-primary" data-cf-target="cf-secure-upload">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Prepare uploaded file') ?>
        </button>
    </div>

    <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Prepared files') ?></h4>
    <?php if (!$releases): ?>
        <p class="cf-hint text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'No prepared files yet.') ?></p>
    <?php endif; ?>
    <?php foreach ($releases as $release): ?>
        <?php
        /** @var SecureRelease $release */
        $rid = (int)$release->id;
        $active = $release->status === SecureRelease::STATUS_ACTIVE;
        $events = SecureEvent::find()->where(['release_id' => $rid])->with('actor')->orderBy(['id' => SORT_DESC])->limit(100)->all();
        ?>
        <article class="cf-secure-release">
            <header>
                <h5><?= Html::encode($release->label) ?></h5>
                <span class="cf-form-row__chip"><?= $active
                    ? Yii::t('ThiscoveryFormsModule.base', 'Active')
                    : Yii::t('ThiscoveryFormsModule.base', 'Revoked') ?></span>
            </header>
            <p>
                <?= Html::encode($release->contact_name) ?>
                · <?= Html::encode($release->contact_email) ?>
                · <?= Html::encode($release->original_name) ?>
                <?php if ($release->row_count !== null): ?>
                    · <?= Yii::t('ThiscoveryFormsModule.base', '{n} rows', ['n' => (int)$release->row_count]) ?>
                <?php endif; ?>
            </p>
            <?php if (!empty($links[$rid])): ?>
                <div class="cf-secure-link">
                    <p><?= Yii::t('ThiscoveryFormsModule.base', 'Copy this link now. It will not be shown again. Send it to the contact yourself. The code is emailed separately.') ?></p>
                    <input type="text" readonly value="<?= Html::encode($links[$rid]) ?>" onclick="this.select()">
                </div>
            <?php endif; ?>
            <?php if ($active): ?>
                <div class="cf-secure-actions">
                    <button type="button" class="btn btn-primary btn-sm" data-cf-target="cf-secure-send-<?= $rid ?>">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Send a new code') ?>
                    </button>
                    <button type="button" class="btn btn-default btn-sm" data-cf-target="cf-secure-revoke-code-<?= $rid ?>">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Revoke code') ?>
                    </button>
                    <button type="button" class="btn btn-default btn-sm" data-cf-target="cf-secure-rotate-<?= $rid ?>">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Issue a new link') ?>
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" data-cf-target="cf-secure-revoke-<?= $rid ?>"
                        onclick="return confirm(<?= \yii\helpers\Json::htmlEncode(Yii::t('ThiscoveryFormsModule.base', 'Revoke this file? The link will stop working. The audit is kept.')) ?>);">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Revoke file') ?>
                    </button>
                </div>
                <div class="cf-secure-contact">
                    <label>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Contact name') ?>
                        <?= Html::textInput('contact_name', $release->contact_name, [
                            'data-cf-target' => 'cf-secure-contact-' . $rid,
                            'data-cf-required' => '1',
                            'class' => 'form-control',
                            'maxlength' => 160,
                        ]) ?>
                    </label>
                    <label>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Contact email') ?>
                        <?= Html::input('email', 'contact_email', $release->contact_email, [
                            'data-cf-target' => 'cf-secure-contact-' . $rid,
                            'data-cf-required' => '1',
                            'class' => 'form-control',
                            'maxlength' => 190,
                        ]) ?>
                    </label>
                    <button type="button" class="btn btn-default btn-sm" data-cf-target="cf-secure-contact-<?= $rid ?>">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Save contact') ?>
                    </button>
                    <p class="cf-hint text-muted">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Changing the email address issues a new link and cancels the current code.') ?>
                    </p>
                </div>
            <?php endif; ?>
            <details class="cf-secure-audit">
                <summary><?= Yii::t('ThiscoveryFormsModule.base', 'Audit ({n})', ['n' => count($events)]) ?></summary>
                <?php if (!$events): ?>
                    <p class="cf-hint text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'No activity yet.') ?></p>
                <?php else: ?>
                    <div class="cf-form-table-wrap">
                        <table class="cf-form-table">
                            <thead>
                            <tr>
                                <th><?= Yii::t('ThiscoveryFormsModule.base', 'When') ?></th>
                                <th><?= Yii::t('ThiscoveryFormsModule.base', 'Event') ?></th>
                                <th><?= Yii::t('ThiscoveryFormsModule.base', 'Who') ?></th>
                                <th><?= Yii::t('ThiscoveryFormsModule.base', 'IP address') ?></th>
                                <th><?= Yii::t('ThiscoveryFormsModule.base', 'Browser') ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($events as $event): ?>
                                <?php
                                $who = $event->actor ? $event->actor->displayName : null;
                                if (!$who) {
                                    $who = $event->actor_id
                                        ? Yii::t('ThiscoveryFormsModule.base', 'Deleted user')
                                        : Yii::t('ThiscoveryFormsModule.base', 'Contact');
                                }
                                ?>
                                <tr>
                                    <td><?= Html::encode(Yii::$app->formatter->asDatetime($event->created_at, 'short')) ?></td>
                                    <td><?= Html::encode(SecureSendService::eventLabel((string)$event->event)) ?></td>
                                    <td><?= Html::encode($who) ?></td>
                                    <td><?= Html::encode((string)$event->ip) ?></td>
                                    <td title="<?= Html::encode((string)$event->user_agent) ?>">
                                        <?= Html::encode(mb_strimwidth((string)$event->user_agent, 0, 80, '…')) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </details>
        </article>
    <?php endforeach; ?>
</div>
