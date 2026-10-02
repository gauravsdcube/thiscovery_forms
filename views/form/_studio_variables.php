<?php

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\FormPager;
use humhub\modules\thiscoveryForms\services\FormVersionAdapter;
use humhub\modules\thiscoveryForms\services\FormVersionService;
use yii\helpers\Html;

/**
 * Saved variable names and the question each one belongs to.
 * The list is the last save, with that save's revision number.
 *
 * @var CustomForm $formModel
 * @var bool $isNew
 * @var FormField[] $fieldList
 */

$isNew = !empty($isNew);
?>
<h3 class="cf-settings-pane__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Variables') ?></h3>
<p class="cf-settings-pane__lead">
    <?= Yii::t('ThiscoveryFormsModule.base', 'Each variable name and the question it belongs to. The list is the last save, so a change in the builder appears here after you save.') ?>
</p>

<?php if ($isNew || !$formModel->id): ?>
    <div class="alert alert-info" role="status">
        <?= Yii::t('ThiscoveryFormsModule.base', 'Save the form first. The variable list is created with that save.') ?>
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
$pageName = static function (array $page) use ($built): string {
    $key = (string)$page['pageKey'];
    $number = (int)($built['pageKeyIndex'][$key] ?? 0) + 1;
    $title = trim((string)$page['title']);
    return $title !== ''
        ? Yii::t('ThiscoveryFormsModule.base', 'Page {n}: {title}', ['n' => $number, 'title' => $title])
        : Yii::t('ThiscoveryFormsModule.base', 'Page {n}', ['n' => $number]);
};
$rows = [];
$unnamed = 0;
$typeLabels = FormField::getTypeLabels();
foreach ($built['pages'] as $page) {
    $fields = $page['items'];
    if ($page['break'] instanceof FormField) {
        $fields[] = $page['break'];
    }
    foreach ($fields as $field) {
        if ($field->type === FormField::TYPE_GROUP_END) {
            continue;
        }
        $variable = trim((string)$field->variable);
        if ($variable === '') {
            $unnamed++;
            continue;
        }
        $rows[] = [
            'variable' => $variable,
            'label' => (string)$field->label,
            'type' => (string)($typeLabels[$field->type] ?? $field->type),
            'page' => $pageName($page),
        ];
    }
}
?>
<div class="cf-var-meta">
    <?php if ($revisionNumber): ?>
        <p class="cf-hint mb-1">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Revision #{n}. Saving the form again records the next revision and refreshes this list.', ['n' => $revisionNumber]) ?>
        </p>
    <?php else: ?>
        <p class="cf-hint mb-1">
            <?= Yii::t('ThiscoveryFormsModule.base', 'This is the saved form. No revision number is recorded yet.') ?>
        </p>
    <?php endif; ?>
    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Variable names from the last save, with the question, the question type, and the page. The revision number is the latest saved copy of this form.')]) ?>
</div>

<?php if (!$rows): ?>
    <p class="cf-hint text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'This form has no saved variable names yet. Add questions and save.') ?></p>
<?php else: ?>
    <div class="form-group" style="max-width: 22rem">
        <label class="cf-label" for="cf-var-filter"><?= Yii::t('ThiscoveryFormsModule.base', 'Find a variable') ?></label>
        <input type="search" id="cf-var-filter" class="form-control" data-cf-var-filter placeholder="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Name or question')) ?>">
    </div>
    <div class="table-responsive">
        <table class="table cf-var-list">
            <thead>
                <tr>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Variable') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Question') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Type') ?></th>
                    <th><?= Yii::t('ThiscoveryFormsModule.base', 'Page') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr data-cf-var-row="<?= Html::encode(strtolower($row['variable'] . ' ' . $row['label'] . ' ' . $row['type'] . ' ' . $row['page'])) ?>">
                        <td><code><?= Html::encode($row['variable']) ?></code></td>
                        <td><?= Html::encode($row['label']) ?></td>
                        <td><?= Html::encode($row['type']) ?></td>
                        <td><?= Html::encode($row['page']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="cf-hint text-muted" data-cf-var-empty hidden><?= Yii::t('ThiscoveryFormsModule.base', 'No variable matches that search.') ?></p>
    <?php if ($unnamed): ?>
        <p class="cf-hint text-muted">
            <?= $unnamed === 1
                ? Yii::t('ThiscoveryFormsModule.base', '1 saved question has no variable name.')
                : Yii::t('ThiscoveryFormsModule.base', '{n} saved questions have no variable name.', ['n' => $unnamed]) ?>
        </p>
    <?php endif; ?>
<?php endif; ?>
<?php
$this->registerJs(<<<'JS'
(function () {
    var input = document.querySelector('[data-cf-var-filter]');
    if (!input || input.getAttribute('data-cf-var-bound')) {
        return;
    }
    input.setAttribute('data-cf-var-bound', '1');
    var empty = document.querySelector('[data-cf-var-empty]');
    input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        var shown = 0;
        document.querySelectorAll('[data-cf-var-row]').forEach(function (row) {
            var text = row.getAttribute('data-cf-var-row') || '';
            var hide = q !== '' && text.indexOf(q) === -1;
            row.hidden = hide;
            if (!hide) {
                shown++;
            }
        });
        if (empty) {
            empty.hidden = shown !== 0;
        }
    });
})();
JS
);
