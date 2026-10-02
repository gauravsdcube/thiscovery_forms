<?php

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\FormActionService;
use humhub\modules\thiscoveryForms\services\FormPager;
use humhub\modules\thiscoveryForms\services\LogicAudit;
use humhub\modules\thiscoveryForms\services\LogicEngine;
use yii\helpers\Html;

/**
 * Route map: each page and where its rules can send the respondent, with the logic design
 * problems that block saving and publishing (LOG-9). Read-only; reflects the saved design.
 *
 * @var CustomForm $formModel
 */

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
$problems = LogicAudit::errors($formModel);
?>
<div class="cf-studio__settings cf-studio__settings--route">
    <div class="cf-label-row">
        <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Route map') ?></h3>
        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Read-only map of the saved questions: each page, where its rules can send someone, and logic problems that stop save or publish. Without a rule, a page continues to the next one.')]) ?>
    </div>
    <p class="help-block"><?= Yii::t('ThiscoveryFormsModule.base', 'Where each page can lead. Without a rule, a page continues to the next one. This shows the saved questions.') ?></p>

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

    <ol class="cf-route-map">
        <?php foreach ($built['pages'] as $page): ?>
            <?php
            $jumps = [];
            $fields = $page['items'];
            if ($page['break'] instanceof FormField) {
                $fields[] = $page['break'];
            }
            foreach ($fields as $field) {
                $logic = $field->getLogic();
                $action = (string)($logic['action'] ?? '');
                $rule = trim((string)($logic['text'] ?? ''));
                if (is_array($logic['when'] ?? null)) {
                    if ($action === LogicEngine::ACTION_GOTO_PAGE) {
                        $jumps[] = [$field->label, $rule, $pageName((string)($logic['gotoPageKey'] ?? ''))];
                    } elseif ($action === LogicEngine::ACTION_GOTO_END) {
                        $jumps[] = [$field->label, $rule, Yii::t('ThiscoveryFormsModule.base', 'End of survey')];
                    } elseif ($action === LogicEngine::ACTION_SCREEN_OUT) {
                        $jumps[] = [$field->label, $rule, Yii::t('ThiscoveryFormsModule.base', 'Screened out')];
                    } elseif ($action === LogicEngine::ACTION_SKIP_PAGE) {
                        $jumps[] = [$field->label, $rule, Yii::t('ThiscoveryFormsModule.base', 'Skips the next page')];
                    }
                }
                foreach ($field->getActions() as $fn) {
                    if (($fn['fn'] ?? '') === FormActionService::FN_GOTO_PAGE) {
                        $jumps[] = [$field->label, Yii::t('ThiscoveryFormsModule.base', 'action, when answered'), $pageName((string)($fn['page_key'] ?? ''))];
                    }
                }
                if ($field->type === FormField::TYPE_PAGE_BREAK) {
                    foreach ($field->getPageBreakConfig()['branches'] as $branch) {
                        $target = trim((string)($branch['gotoPageKey'] ?? ''));
                        if ($target !== '') {
                            $jumps[] = [$field->label, (string)($branch['text'] ?? $branch['formula'] ?? ''), $pageName($target)];
                        }
                    }
                    $otherwise = trim((string)$field->getPageBreakConfig()['otherwise']);
                    if ($otherwise !== '') {
                        $jumps[] = [$field->label, Yii::t('ThiscoveryFormsModule.base', 'otherwise'), $pageName($otherwise)];
                    }
                }
            }
            ?>
            <li class="cf-route-map__page">
                <strong><?= Html::encode($pageName((string)$page['pageKey'])) ?></strong>
                <span class="text-muted">(<?= Html::encode((string)$page['pageKey']) ?>)</span>
                <?php if ($jumps): ?>
                    <ul class="cf-route-map__jumps">
                        <?php foreach ($jumps as [$label, $rule, $target]): ?>
                            <li>
                                <?= Html::encode((string)$label) ?>
                                <?php if ($rule !== ''): ?>
                                    <code><?= Html::encode($rule) ?></code>
                                <?php endif; ?>
                                <span aria-hidden="true">→</span>
                                <span class="sr-only"><?= Yii::t('ThiscoveryFormsModule.base', 'goes to') ?></span>
                                <?= Html::encode($target) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</div>
