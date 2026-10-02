<?php

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\FormActionService;
use humhub\modules\thiscoveryForms\services\FormPager;
use humhub\modules\thiscoveryForms\services\FormVersionAdapter;
use humhub\modules\thiscoveryForms\services\FormVersionService;
use humhub\modules\thiscoveryForms\services\LogicAudit;
use humhub\modules\thiscoveryForms\services\LogicEngine;
use yii\helpers\Html;

/**
 * Flow chart of the saved form: pages in order, the path when no rule matches,
 * and each branch, show, or hide rule.
 *
 * @var CustomForm $formModel
 * @var bool $isNew
 */

$isNew = !empty($isNew);
?>
<div class="cf-studio__settings cf-studio__settings--route">
    <div class="cf-label-row">
        <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Flow chart') ?></h3>
        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Every saved question is listed on its page, in order. A plain arrow is the path when no rule matches. An amber line is a branch. A show or hide rule is written under that question. Unsaved builder edits are not in this chart.')]) ?>
    </div>

    <?php if ($isNew || !$formModel->id): ?>
        <div class="alert alert-info" role="status">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Save the form first. The flow chart is drawn from the saved questions.') ?>
        </div>
    </div>
        <?php return; ?>
    <?php endif; ?>

    <?php
    $revisionNumber = null;
    if (FormVersionService::isAvailable()) {
        $latest = (new FormVersionService())->versions()->latestRevision(
            FormVersionAdapter::OWNER_TYPE,
            (int)$formModel->id
        );
        $revisionNumber = $latest ? (int)$latest->revision_number : null;
    }
    $built = (new FormPager())->buildPages($formModel->getFields()->all());
    $titles = [];
    foreach ($built['pages'] as $page) {
        $titles[(string)$page['pageKey']] = (string)$page['title'];
    }
    $pageName = static function (string $key) use ($built, $titles): string {
        if (!isset($built['pageKeyIndex'][$key])) {
            return Yii::t('ThiscoveryFormsModule.base', 'missing page “{key}”', ['key' => $key]);
        }
        $number = (int)$built['pageKeyIndex'][$key] + 1;
        $title = $titles[$key] ?? '';
        return $title !== ''
            ? Yii::t('ThiscoveryFormsModule.base', 'Page {n}: {title}', ['n' => $number, 'title' => $title])
            : Yii::t('ThiscoveryFormsModule.base', 'Page {n}', ['n' => $number]);
    };
    $actionLabels = LogicEngine::actionLabels();
    $problems = LogicAudit::errors($formModel);
    $endLabel = Yii::t('ThiscoveryFormsModule.base', 'End of survey');
    $screenLabel = Yii::t('ThiscoveryFormsModule.base', 'Screened out');
    ?>

    <?php if ($revisionNumber): ?>
        <p class="cf-hint"><?= Yii::t('ThiscoveryFormsModule.base', 'Revision #{n}. This chart changes when you save.', ['n' => $revisionNumber]) ?></p>
    <?php else: ?>
        <p class="cf-hint"><?= Yii::t('ThiscoveryFormsModule.base', 'This chart is the saved form. It changes when you save.') ?></p>
    <?php endif; ?>

    <?php if ($problems): ?>
        <div class="alert alert-danger" role="alert">
            <strong><?= Yii::t('ThiscoveryFormsModule.base', 'These problems stop the form from being saved or published:') ?></strong>
            <ul>
                <?php foreach ($problems as $problem): ?>
                    <li><?= Html::encode($problem) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!$built['pages']): ?>
        <p class="cf-hint text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'Add questions and save to see the flow.') ?></p>
    </div>
        <?php return; ?>
    <?php endif; ?>

    <ol class="cf-flow">
        <li class="cf-flow__term"><?= Yii::t('ThiscoveryFormsModule.base', 'Start') ?></li>
        <?php foreach ($built['pages'] as $index => $page): ?>
            <?php
            $fields = $page['items'];
            if ($page['break'] instanceof FormField) {
                $fields[] = $page['break'];
            }
            $questions = [];
            $branches = [];
            foreach ($fields as $field) {
                $logic = $field->getLogic();
                $ruleText = trim((string)($logic['text'] ?? ''));
                $action = (string)($logic['action'] ?? '');
                $verb = '';
                $displayRule = '';
                if ($ruleText !== '' && is_array($logic['when'] ?? null)) {
                    $verb = (string)($actionLabels[$action] ?? $action);
                    if ($action === LogicEngine::ACTION_SHOW || $action === LogicEngine::ACTION_HIDE) {
                        $displayRule = $ruleText;
                    } elseif ($action === LogicEngine::ACTION_GOTO_PAGE) {
                        $branches[] = [(string)$field->label, $verb, $ruleText, $pageName((string)($logic['gotoPageKey'] ?? ''))];
                    } elseif ($action === LogicEngine::ACTION_GOTO_END) {
                        $branches[] = [(string)$field->label, $verb, $ruleText, $endLabel];
                    } elseif ($action === LogicEngine::ACTION_SCREEN_OUT) {
                        $branches[] = [(string)$field->label, $verb, $ruleText, $screenLabel];
                    } elseif ($action === LogicEngine::ACTION_SKIP_PAGE) {
                        $branches[] = [(string)$field->label, $verb, $ruleText, Yii::t('ThiscoveryFormsModule.base', 'Skips the next page')];
                    }
                }
                if ($field->type !== FormField::TYPE_PAGE_BREAK && $field->type !== FormField::TYPE_GROUP_END && $field->type !== FormField::TYPE_RAND_BLOCK && $field->type !== FormField::TYPE_RAND_BLOCK_END) {
                    $questions[] = [(string)$field->label, (string)$field->variable, $verb, $displayRule];
                }
                foreach ($field->getActions() as $fn) {
                    if (($fn['fn'] ?? '') === FormActionService::FN_GOTO_PAGE) {
                        $branches[] = [
                            (string)$field->label,
                            Yii::t('ThiscoveryFormsModule.base', 'Action'),
                            Yii::t('ThiscoveryFormsModule.base', 'when answered'),
                            $pageName((string)($fn['page_key'] ?? '')),
                        ];
                    }
                }
                if ($field->type === FormField::TYPE_PAGE_BREAK) {
                    foreach ($field->getPageBreakConfig()['branches'] as $branch) {
                        $target = trim((string)($branch['gotoPageKey'] ?? ''));
                        if ($target === '') {
                            continue;
                        }
                        $branches[] = [
                            (string)$field->label,
                            Yii::t('ThiscoveryFormsModule.base', 'Page branch'),
                            (string)($branch['text'] ?? $branch['formula'] ?? ''),
                            $pageName($target),
                        ];
                    }
                }
            }
            $otherwise = '';
            if ($page['break'] instanceof FormField) {
                $otherwise = trim((string)$page['break']->getPageBreakConfig()['otherwise']);
            }
            $continue = $otherwise !== ''
                ? $pageName($otherwise)
                : (isset($built['pages'][$index + 1]) ? $pageName((string)$built['pages'][$index + 1]['pageKey']) : $endLabel);
            ?>
            <li class="cf-flow__link" aria-hidden="true"></li>
            <li class="cf-flow__page">
                <h4><?= Html::encode($pageName((string)$page['pageKey'])) ?></h4>
                <p class="cf-flow__meta">
                    <code><?= Html::encode((string)$page['pageKey']) ?></code>
                    <span><?= count($questions) === 1
                        ? Yii::t('ThiscoveryFormsModule.base', '1 question')
                        : Yii::t('ThiscoveryFormsModule.base', '{n} questions', ['n' => count($questions)]) ?></span>
                </p>
                <?php if ($questions): ?>
                    <ol class="cf-flow__logic">
                        <?php foreach ($questions as [$label, $variable, $verb, $ruleText]): ?>
                            <li>
                                <span class="cf-flow__q"><?= Html::encode($label) ?></span>
                                <?php if ($variable !== ''): ?>
                                    <code><?= Html::encode($variable) ?></code>
                                <?php endif; ?>
                                <?php if ($ruleText !== ''): ?>
                                    <span class="cf-flow__rule"><?= Html::encode($verb) ?> <code><?= Html::encode($ruleText) ?></code></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <p class="cf-flow__none"><?= Yii::t('ThiscoveryFormsModule.base', 'No questions on this page.') ?></p>
                <?php endif; ?>
            </li>
            <?php foreach ($branches as [$label, $verb, $ruleText, $target]): ?>
                <li class="cf-flow__branch">
                    <span class="cf-flow__q"><?= Html::encode($label) ?></span>
                    <span><?= Html::encode($verb) ?></span>
                    <?php if ($ruleText !== ''): ?>
                        <code><?= Html::encode($ruleText) ?></code>
                    <?php endif; ?>
                    <span aria-hidden="true">→</span>
                    <span class="sr-only"><?= Yii::t('ThiscoveryFormsModule.base', 'goes to') ?></span>
                    <strong><?= Html::encode($target) ?></strong>
                </li>
            <?php endforeach; ?>
            <li class="cf-flow__next">
                <?= $branches
                    ? Yii::t('ThiscoveryFormsModule.base', 'If no branch matches, continue to {target}', ['target' => $continue])
                    : Yii::t('ThiscoveryFormsModule.base', 'Continue to {target}', ['target' => $continue]) ?>
            </li>
        <?php endforeach; ?>
        <li class="cf-flow__link" aria-hidden="true"></li>
        <li class="cf-flow__term"><?= Html::encode($endLabel) ?></li>
    </ol>
</div>
