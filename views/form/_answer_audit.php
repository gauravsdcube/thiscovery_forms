<?php

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\services\AnswerAudit;
use humhub\modules\user\models\User;
use yii\db\Query;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var FormAnswer $answer */

// Who changed what, when and why (V3-44). Managers only; the actor is a manager or the
// identified respondent, never a respondent on an anonymous form.
if (!$formModel->canManage() || !AnswerAudit::tableReady() || !$answer->id) {
    return;
}
$rows = (new Query())->from('{{%custom_form_answer_audit}}')
    ->where(['answer_id' => (int)$answer->id])
    ->orderBy(['created_at' => SORT_DESC, 'id' => SORT_DESC])
    ->limit(200)
    ->all();
if ($rows === []) {
    return;
}
$labels = [];
foreach ($formModel->getAllFields()->all() as $field) {
    $labels[(int)$field->id] = (string)$field->label;
}
$actors = [];
$actorIds = array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'actor_id')))));
if ($actorIds) {
    foreach (User::find()->where(['id' => $actorIds])->all() as $user) {
        $actors[(int)$user->id] = (string)$user->displayName;
    }
}
$show = static function ($value): string {
    $value = (string)$value;
    if ($value === '') {
        return Yii::t('ThiscoveryFormsModule.base', '(blank)');
    }
    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        $value = implode(', ', array_map(static fn($v) => is_scalar($v) ? (string)$v : json_encode($v), $decoded));
    }
    return mb_strimwidth($value, 0, 200, '…');
};
?>
<details class="cf-answer-audit">
    <summary><?= Yii::t('ThiscoveryFormsModule.base', 'Change history ({n})', ['n' => count($rows)]) ?></summary>
    <table class="table table-condensed">
        <thead>
        <tr>
            <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'When') ?></th>
            <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Question') ?></th>
            <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Before') ?></th>
            <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'After') ?></th>
            <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'By') ?></th>
            <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Reason') ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= Html::encode(Yii::$app->formatter->asDatetime($row['created_at'], 'short')) ?></td>
                <td><?= Html::encode(($labels[(int)$row['field_id']] ?? ('#' . (int)$row['field_id'])) . ((string)$row['instance_key'] !== '' ? ' [' . $row['instance_key'] . ']' : '')) ?></td>
                <td><?= Html::encode($row['old_value'] === null ? Yii::t('ThiscoveryFormsModule.base', '(erased)') : $show($row['old_value'])) ?></td>
                <td><?= Html::encode($row['new_value'] === null ? Yii::t('ThiscoveryFormsModule.base', '(removed)') : $show($row['new_value'])) ?></td>
                <td><?= Html::encode($row['actor_id'] ? ($actors[(int)$row['actor_id']] ?? ('#' . (int)$row['actor_id'])) : Yii::t('ThiscoveryFormsModule.base', 'Respondent')) ?></td>
                <td><?= Html::encode((string)$row['reason']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</details>
