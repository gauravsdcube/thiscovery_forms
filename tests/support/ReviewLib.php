<?php

use humhub\modules\content\models\Content;
use humhub\modules\space\models\Space;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\user\models\User;
use yii\db\Query;

final class ReviewLib
{
    public static function asUser(?User $user): void
    {
        Yii::$app->user->switchIdentity($user);
    }

    public static function form(Space $space, string $title, array $attrs = []): CustomForm
    {
        $existing = CustomForm::find()->where(['title' => $title])->one();
        if ($existing && (int)$existing->content->container->id === (int)$space->id) {
            foreach ($attrs as $k => $v) {
                if ($k === 'settings' || $k === 'status') {
                    continue;
                }
                $existing->$k = $v;
            }
            $existing->save();
            return $existing;
        }
        $form = new CustomForm($space);
        $form->title = $title;
        $form->kind = $attrs['kind'] ?? CustomForm::KIND_SURVEY;
        $form->status = CustomForm::STATUS_DRAFT;
        $form->allow_anonymous = $attrs['allow_anonymous'] ?? 0;
        $form->allow_multiple = $attrs['allow_multiple'] ?? 1;
        $form->allow_resume = $attrs['allow_resume'] ?? 1;
        $form->identity_mode = $attrs['identity_mode'] ?? CustomForm::IDENTITY_IDENTIFIED;
        $form->description = $attrs['description'] ?? 'Review sandbox form';
        $form->content->visibility = Content::VISIBILITY_PUBLIC;
        foreach ($attrs as $k => $v) {
            if ($k === 'settings' || $k === 'status') {
                continue;
            }
            $form->$k = $v;
        }
        if (!$form->save()) {
            throw new RuntimeException('form save ' . $title . ': ' . json_encode($form->errors));
        }
        return $form;
    }

    public static function clearFields(CustomForm $form): void
    {
        $ids = FormField::find()->select('id')->where(['form_id' => (int)$form->id])->column();
        if ($ids) {
            FormAnswerField::deleteAll(['field_id' => $ids]);
        }
        FormField::deleteAll(['form_id' => (int)$form->id]);
    }

    public static function field(CustomForm $form, string $type, string $label, array $opts = []): FormField
    {
        $field = new FormField();
        $field->form_id = $form->id;
        $field->type = $type;
        $field->label = $label;
        $field->variable = $opts['variable'] ?? FormField::slugVariable($label, $type);
        $field->sort_order = $opts['sort_order'] ?? ((int)FormField::find()->where(['form_id' => $form->id])->max('sort_order') + 1);
        $field->required = !empty($opts['required']) ? 1 : 0;
        if (isset($opts['options'])) {
            $field->options_json = is_string($opts['options']) ? $opts['options'] : json_encode($opts['options'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($opts['logic'])) {
            $logic = is_string($opts['logic']) ? json_decode($opts['logic'], true) : $opts['logic'];
            if (!is_array($logic)) {
                throw new InvalidArgumentException('A formula rule is required.');
            }
            $field->setLogic($logic);
        }
        if (isset($opts['actions'])) {
            $field->actions_json = is_string($opts['actions']) ? $opts['actions'] : json_encode($opts['actions'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($opts['help_text'])) {
            $field->help_text = $opts['help_text'];
        }
        if (isset($opts['variable'])) {
            $field->ensureVariable();
        } else {
            // Like the studio: a generated name steps past live names already on the form (DAT-10).
            $used = [];
            $live = FormField::find()->select('variable')->where(['form_id' => $form->id]);
            if (FormField::supportsSoftDelete()) {
                $live->andWhere(['deleted_at' => null]);
            }
            foreach ($live->column() as $name) {
                $used[strtolower((string)$name)] = true;
            }
            $field->ensureVariable($used);
        }
        if (!$field->save()) {
            throw new RuntimeException('field save ' . $label . ': ' . json_encode($field->errors));
        }
        return $field;
    }

    public static function publishOpen(CustomForm $form): CustomForm
    {
        $svc = new \humhub\modules\thiscoveryForms\services\FormVersionService();
        $svc->recordSave($form);
        $edition = $svc->publish($form);
        if (!$edition) {
            throw new RuntimeException('Could not publish edition for form ' . $form->id);
        }
        $form->status = CustomForm::STATUS_OPEN;
        if (!$form->save()) {
            throw new RuntimeException('open form: ' . json_encode($form->errors));
        }
        return self::reload($form);
    }

    public static function reload(CustomForm $form): CustomForm
    {
        return CustomForm::findOne($form->id);
    }

    public static function submit(CustomForm $form, array $values, bool $asDraft = false, ?FormAnswer $existing = null): ?FormAnswer
    {
        $form = self::reload($form);
        $submit = new SubmitForm();
        $submit->form = $form;
        $submit->values = $values;
        if ($existing && !$existing->isNewRecord && $existing->isComplete()) {
            $submit->changeReason = 'Review edit';
        }
        $anonymous = (bool)$form->allowsAnonymous() || Yii::$app->user->isGuest;
        return $submit->save($existing, $anonymous, $asDraft, false);
    }

    public static function answerRows(int $answerId): array
    {
        return (new Query())
            ->from('custom_form_answer_field')
            ->where(['answer_id' => $answerId])
            ->all();
    }
}
