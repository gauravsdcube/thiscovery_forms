<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;
use yii\helpers\Json;

/** @var $contentContainer */
/** @var string $sessionId */
/** @var array $state */
/** @var bool $llmEnabled */
/** @var string|null $costWarning */
/** @var array $sessionCost */

ThiscoveryFormsAsset::register($this);
$fields = is_array($state['fields'] ?? null) ? $state['fields'] : [];
$typeLabels = FormField::getTypeLabels();
$chatUrl = Url::toFromBriefChat($contentContainer, $sessionId);
$generateUrl = Url::toFromBriefGenerate($contentContainer, $sessionId);
$statusUrl = Url::toFromBriefStatus($contentContainer, $sessionId);
$pendingLlm = $llmEnabled && !empty($state['pending_llm']);
?>

<div class="cf-from-brief panel panel-default">
    <div class="panel-heading">
        <strong><?= Yii::t('ThiscoveryFormsModule.base', 'Review proposed survey') ?></strong>
        <span class="pull-right">
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Start over'))
                ->link(Url::toCreateFromBrief($contentContainer))
                ->loader(false) ?>
        </span>
    </div>
    <div class="panel-body">
        <?php if ($pendingLlm): ?>
            <div class="alert alert-info" id="cf-brief-llm-pending">
                <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
                <?= Yii::t('ThiscoveryFormsModule.base', 'Improving the proposal with AI… You can keep the rules-based draft below if this takes too long.') ?>
            </div>
        <?php endif; ?>

        <?php if ($costWarning): ?>
            <div class="alert alert-warning"><?= Html::encode($costWarning) ?></div>
        <?php endif; ?>

        <?php if (!empty($state['map_warning'])): ?>
            <div class="alert alert-warning"><?= Html::encode((string)$state['map_warning']) ?></div>
        <?php endif; ?>

        <p class="text-muted" id="cf-brief-map-meta">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Mapping mode: {mode}', [
                'mode' => ($state['map_mode'] ?? 'rules') === 'llm'
                    ? Yii::t('ThiscoveryFormsModule.base', 'AI')
                    : Yii::t('ThiscoveryFormsModule.base', 'Rules'),
            ]) ?>
            <?php if ($llmEnabled): ?>
                · <?= Yii::t('ThiscoveryFormsModule.base', 'This session ~{tokens} tokens / ~${cost}', [
                    'tokens' => (int)($sessionCost['tokens'] ?? 0),
                    'cost' => number_format((float)($sessionCost['cost'] ?? 0), 4),
                ]) ?>
            <?php endif; ?>
            <?php if (!empty($state['source_name'])): ?>
                · <?= Yii::t('ThiscoveryFormsModule.base', 'Source: {name}', ['name' => Html::encode((string)$state['source_name'])]) ?>
            <?php endif; ?>
        </p>

        <div class="row">
            <div class="col-md-<?= $llmEnabled ? '7' : '12' ?>">
                <?= Html::beginForm(Url::toFromBriefGenerate($contentContainer, $sessionId), 'post', ['id' => 'cf-brief-generate-form']) ?>
                    <div class="form-group">
                        <label><?= Yii::t('ThiscoveryFormsModule.base', 'Brief') ?></label>
                        <?= Html::textarea('brief', (string)($state['brief'] ?? ''), [
                            'id' => 'cf-brief-text',
                            'class' => 'form-control',
                            'rows' => 10,
                        ]) ?>
                    </div>
                    <button type="submit" class="btn btn-default" id="cf-brief-generate-btn">
                        <i class="fa fa-refresh" aria-hidden="true"></i>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Regenerate questions from brief') ?>
                    </button>
                    <span class="help-block" id="cf-brief-generate-hint" style="<?= $pendingLlm ? '' : 'display:none' ?>">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Starting AI improvement… the page will reload when finished.') ?>
                    </span>
                <?= Html::endForm() ?>

                <hr>

                <?= Html::beginForm(Url::toFromBriefApply($contentContainer, $sessionId), 'post') ?>
                    <div class="form-group">
                        <label><?= Yii::t('ThiscoveryFormsModule.base', 'Draft title') ?></label>
                        <?= Html::textInput('title', (string)($state['title'] ?? ''), ['class' => 'form-control', 'required' => true]) ?>
                    </div>

                    <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Proposed questions ({n})', ['n' => count($fields)]) ?></h4>
                    <?php if (!$fields): ?>
                        <p class="alert alert-warning"><?= Yii::t('ThiscoveryFormsModule.base', 'No questions yet. Regenerate from the brief.') ?></p>
                    <?php else: ?>
                        <ol class="cf-from-brief__list">
                            <?php foreach ($fields as $field): ?>
                                <li>
                                    <strong><?= Html::encode($typeLabels[$field['type'] ?? ''] ?? ($field['type'] ?? '')) ?></strong>
                                    — <?= Html::encode((string)($field['label'] ?? '')) ?>
                                    <?php if (!empty($field['page_title'])): ?>
                                        <span class="text-muted">(<?= Html::encode((string)$field['page_title']) ?>)</span>
                                    <?php endif; ?>
                                    <?php if (!empty($field['options']) && is_array($field['options'])): ?>
                                        <div class="text-muted small"><?= Html::encode(implode(' · ', $field['options'])) ?></div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary" <?= $fields ? '' : 'disabled' ?>>
                        <i class="fa fa-check" aria-hidden="true"></i>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Create Draft survey') ?>
                    </button>
                <?= Html::endForm() ?>
            </div>

            <?php if ($llmEnabled): ?>
                <div class="col-md-5">
                    <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Refine with AI chat') ?></h4>
                    <p class="help-block">
                        <?= Yii::t('ThiscoveryFormsModule.base', '1) Chat to change the survey. 2) Check that the Brief text updates. 3) Click Regenerate questions from brief.') ?>
                    </p>
                    <div id="cf-brief-chat" class="cf-brief-chat" style="border:1px solid #ddd;border-radius:4px;padding:12px;min-height:280px;max-height:420px;overflow:auto;background:#fafafa;margin-bottom:10px;"></div>
                    <div class="input-group">
                        <input type="text" id="cf-brief-chat-input" class="form-control" placeholder="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Ask the assistant…')) ?>">
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-primary" id="cf-brief-chat-send"><?= Yii::t('ThiscoveryFormsModule.base', 'Send') ?></button>
                        </span>
                    </div>
                    <p class="help-block" id="cf-brief-chat-status"></p>
                    <div id="cf-brief-after-chat" class="alert alert-info" style="display:none;margin-top:10px">
                        <p id="cf-brief-after-chat-msg" style="margin-bottom:8px"></p>
                        <button type="button" class="btn btn-primary btn-sm" id="cf-brief-regen-from-chat">
                            <i class="fa fa-refresh" aria-hidden="true"></i>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Regenerate questions from brief') ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
if ($llmEnabled) {
    $chatHistory = [];
    foreach (is_array($state['chat'] ?? null) ? $state['chat'] : [] as $row) {
        if (!is_array($row) || empty($row['role']) || !isset($row['content'])) {
            continue;
        }
        if (!in_array($row['role'], ['user', 'assistant'], true)) {
            continue;
        }
        // Prefer cleaned assistant text without BRIEF markers for display
        $content = (string)$row['content'];
        if ($row['role'] === 'assistant') {
            $content = trim(preg_replace('/<<<BRIEF>>>.*?<<<END_BRIEF>>>/s', '', $content) ?? $content);
        }
        if ($content === '') {
            continue;
        }
        $chatHistory[] = ['role' => $row['role'], 'content' => $content];
    }

    $js = <<<'JS'
(function () {
    var chatUrl = CHAT_URL;
    var generateUrl = GENERATE_URL;
    var statusUrl = STATUS_URL;
    var csrf = CSRF;
    var csrfName = CSRF_NAME;
    var pendingLlm = PENDING;
    var history = HISTORY;
    var box = document.getElementById("cf-brief-chat");
    var input = document.getElementById("cf-brief-chat-input");
    var sendBtn = document.getElementById("cf-brief-chat-send");
    var status = document.getElementById("cf-brief-chat-status");
    var brief = document.getElementById("cf-brief-text");
    var generateForm = document.getElementById("cf-brief-generate-form");
    var generateBtn = document.getElementById("cf-brief-generate-btn");
    var pendingBox = document.getElementById("cf-brief-llm-pending");
    var afterChat = document.getElementById("cf-brief-after-chat");
    var afterChatMsg = document.getElementById("cf-brief-after-chat-msg");
    var regenFromChat = document.getElementById("cf-brief-regen-from-chat");
    var pollTimer = null;

    function append(role, text) {
        if (!box) return;
        var el = document.createElement("div");
        el.style.marginBottom = "10px";
        var strong = document.createElement("strong");
        strong.textContent = (role === "user" ? "You" : "Assistant") + ":";
        el.appendChild(strong);
        el.appendChild(document.createTextNode(" "));
        String(text || "").split("\n").forEach(function (part, i) {
            if (i) { el.appendChild(document.createElement("br")); }
            el.appendChild(document.createTextNode(part));
        });
        box.appendChild(el);
        box.scrollTop = box.scrollHeight;
    }

    (history || []).forEach(function (row) {
        if (row && row.role && row.content) append(row.role, row.content);
    });

    function setPendingUi(on, message) {
        if (!pendingBox) {
            if (on && !document.getElementById("cf-brief-llm-pending")) {
                pendingBox = document.createElement("div");
                pendingBox.id = "cf-brief-llm-pending";
                pendingBox.className = "alert alert-info";
                var body = document.querySelector(".cf-from-brief .panel-body");
                if (body) body.insertBefore(pendingBox, body.firstChild);
            } else {
                pendingBox = document.getElementById("cf-brief-llm-pending");
            }
        }
        if (!pendingBox) return;
        pendingBox.style.display = on ? "" : "none";
        if (message) {
            pendingBox.className = on ? "alert alert-info" : "alert alert-warning";
            pendingBox.innerHTML = on
                ? '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i> ' + message
                : message;
        }
    }

    function showBriefUpdatedCue() {
        if (!afterChat || !afterChatMsg) return;
        afterChat.style.display = "";
        afterChatMsg.textContent = MSG_BRIEF_UPDATED;
        if (status) status.textContent = MSG_BRIEF_UPDATED;
    }

    function pollStatus() {
        fetch(statusUrl, { credentials: "same-origin", headers: { "Accept": "application/json", "X-Requested-With": "XMLHttpRequest" } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) return;
                if (data.pending) return;
                if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
                window.location.reload();
            })
            .catch(function () {});
    }

    function startPolling() {
        setPendingUi(true, MSG_PENDING);
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(pollStatus, 3000);
        pollStatus();
    }

    function startGenerate() {
        if (!brief) return;
        if (generateBtn) generateBtn.disabled = true;
        if (regenFromChat) regenFromChat.disabled = true;
        setPendingUi(true, MSG_STARTING);
        if (afterChat) afterChat.style.display = "none";
        var body = new FormData();
        body.append(csrfName, csrf);
        body.append("ajax", "1");
        body.append("brief", brief.value || "");
        fetch(generateUrl, {
            method: "POST",
            body: body,
            credentials: "same-origin",
            headers: { "Accept": "application/json", "X-Requested-With": "XMLHttpRequest" }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (generateBtn) generateBtn.disabled = false;
                if (regenFromChat) regenFromChat.disabled = false;
                if (!data || !data.success) {
                    setPendingUi(false, (data && data.error) ? data.error : MSG_FAIL);
                    return;
                }
                startPolling();
            })
            .catch(function () {
                if (generateBtn) generateBtn.disabled = false;
                if (regenFromChat) regenFromChat.disabled = false;
                setPendingUi(false, MSG_FAIL);
            });
    }

    if (generateForm) {
        generateForm.addEventListener("submit", function (e) {
            e.preventDefault();
            startGenerate();
        });
    }
    if (regenFromChat) {
        regenFromChat.addEventListener("click", function () { startGenerate(); });
    }

    if (pendingLlm) {
        startPolling();
    }

    function send() {
        if (!input || !sendBtn) return;
        var msg = (input.value || "").trim();
        if (!msg) return;
        append("user", msg);
        input.value = "";
        if (status) status.textContent = MSG_THINKING;
        sendBtn.disabled = true;
        var body = new FormData();
        body.append(csrfName, csrf);
        body.append("message", msg);
        fetch(chatUrl, { method: "POST", body: body, credentials: "same-origin" })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                sendBtn.disabled = false;
                if (!data.success) { if (status) status.textContent = data.error || "Error"; return; }
                append("assistant", data.reply || "");
                if (data.brief && brief) { brief.value = data.brief; }
                if (data.briefChanged) {
                    showBriefUpdatedCue();
                } else if (status) {
                    status.textContent = data.sessionCost ? MSG_COST : MSG_NO_BRIEF_CHANGE;
                }
            })
            .catch(function () {
                sendBtn.disabled = false;
                if (status) status.textContent = MSG_CHAT_FAIL;
            });
    }
    if (sendBtn) sendBtn.addEventListener("click", send);
    if (input) input.addEventListener("keydown", function (e) {
        if (e.key === "Enter") { e.preventDefault(); send(); }
    });
})();
JS;
    $js = strtr($js, [
        'CHAT_URL' => Json::htmlEncode($chatUrl),
        'GENERATE_URL' => Json::htmlEncode($generateUrl),
        'STATUS_URL' => Json::htmlEncode($statusUrl),
        'CSRF' => Json::htmlEncode(Yii::$app->request->csrfToken),
        'CSRF_NAME' => Json::htmlEncode(Yii::$app->request->csrfParam),
        'PENDING' => $pendingLlm ? 'true' : 'false',
        'HISTORY' => Json::htmlEncode($chatHistory),
        'MSG_PENDING' => Json::htmlEncode(Yii::t('ThiscoveryFormsModule.base', 'Improving the proposal with AI… this page will refresh when ready.')),
        'MSG_STARTING' => Json::htmlEncode(Yii::t('ThiscoveryFormsModule.base', 'Starting AI improvement…')),
        'MSG_FAIL' => Json::htmlEncode(Yii::t('ThiscoveryFormsModule.base', 'AI timed out or failed. Keep the rules draft, or try Regenerate again.')),
        'MSG_THINKING' => Json::htmlEncode(Yii::t('ThiscoveryFormsModule.base', 'Thinking…')),
        'MSG_COST' => Json::htmlEncode(Yii::t('ThiscoveryFormsModule.base', 'Session estimate updated.')),
        'MSG_CHAT_FAIL' => Json::htmlEncode(Yii::t('ThiscoveryFormsModule.base', 'Chat request failed.')),
        'MSG_BRIEF_UPDATED' => Json::htmlEncode(Yii::t('ThiscoveryFormsModule.base', 'Brief updated. Regenerate questions to refresh the proposal.')),
        'MSG_NO_BRIEF_CHANGE' => Json::htmlEncode(Yii::t('ThiscoveryFormsModule.base', 'No brief change detected. Ask for a concrete survey edit, then regenerate.')),
    ]);
    $this->registerJs($js, \yii\web\View::POS_READY);
}
?>
