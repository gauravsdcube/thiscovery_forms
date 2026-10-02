<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\formula\FormulaDeps;
use Yii;

/**
 * Design checks on a form's logic, run when questions are saved and before publishing (LOG-9).
 *
 * Backward go-tos are refused separately (they are what makes a route cycle). This adds:
 * duplicate page keys, go-to targets that don't exist, show/hide rules on their own answer,
 * and rules that read a question on a later page, which has never been answered when the
 * rule runs.
 */
class LogicAudit
{
    /** @return string[] */
    public static function errors(CustomForm $form): array
    {
        $fields = array_values(array_filter($form->getFields()->all(), static fn($f) => $f instanceof FormField));
        $errors = [];

        // Page keys, as FormPager assigns them: "start", then the break's key or pN.
        $keys = ['start' => (string)Yii::t('ThiscoveryFormsModule.base', 'the first page')];
        $pageOf = [];
        $page = 0;
        foreach ($fields as $field) {
            if ($field->type === FormField::TYPE_PAGE_BREAK) {
                // A break ends its page; its rules run on that page.
                $pageOf[(int)$field->id] = $page;
                $page++;
                $key = $field->getPageBreakConfig()['pageKey'] ?: ('p' . $page);
                if (isset($keys[$key])) {
                    $errors[] = Yii::t('ThiscoveryFormsModule.base', 'Two pages use the page key “{key}”. Each page needs its own key.', ['key' => $key]);
                }
                $keys[$key] = (string)$field->label;
                continue;
            }
            $pageOf[(int)$field->id] = $page;
        }

        $byName = [];
        foreach ($fields as $field) {
            $variable = strtolower(trim((string)$field->variable));
            if ($variable !== '') {
                $byName[$variable] = $field;
            }
            $byName['id' . (int)$field->id] = $field;
            $byName[(string)(int)$field->id] = $field;
        }

        $missingTarget = static function (string $label, string $key) use ($keys, &$errors): void {
            $key = trim($key);
            if ($key === '') {
                $errors[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” goes to a page, but no page is chosen.', ['label' => $label]);
            } elseif (!isset($keys[$key])) {
                $errors[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” goes to page “{key}”, which does not exist.', ['label' => $label, 'key' => $key]);
            }
        };

        foreach ($fields as $field) {
            $label = (string)$field->label !== '' ? (string)$field->label : ('#' . (int)$field->id);
            $logic = $field->getLogic();
            $action = (string)($logic['action'] ?? '');
            $hasRule = is_array($logic['when'] ?? null);

            if ($hasRule && $action === LogicEngine::ACTION_GOTO_PAGE) {
                $missingTarget($label, (string)($logic['gotoPageKey'] ?? ''));
            }
            foreach ($field->getActions() as $fn) {
                if (($fn['fn'] ?? '') === FormActionService::FN_GOTO_PAGE) {
                    $missingTarget($label, (string)($fn['page_key'] ?? ''));
                }
            }
            if ($field->type === FormField::TYPE_PAGE_BREAK) {
                foreach ($field->getPageBreakConfig()['branches'] as $branch) {
                    if (trim((string)($branch['gotoPageKey'] ?? '')) !== '') {
                        $missingTarget($label, (string)$branch['gotoPageKey']);
                    }
                }
                $otherwise = trim((string)$field->getPageBreakConfig()['otherwise']);
                if ($otherwise !== '') {
                    $missingTarget($label, $otherwise);
                }
            }

            if (!$hasRule) {
                continue;
            }
            $own = $pageOf[(int)$field->id] ?? 0;
            foreach (FormulaDeps::names($logic['when']) as $name) {
                if ($name === 'arm' || str_contains($name, ':')) {
                    continue;
                }
                $target = $byName[strtolower($name)] ?? null;
                if (!$target instanceof FormField) {
                    // Unknown names are refused by FormulaPolicy at publish.
                    continue;
                }
                $visibility = in_array($action, [LogicEngine::ACTION_SHOW, LogicEngine::ACTION_HIDE, LogicEngine::ACTION_SKIP], true);
                if ($visibility && (int)$target->id === (int)$field->id) {
                    $errors[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” is shown or hidden by its own answer, which can only be given once it is shown.', ['label' => $label]);
                    continue;
                }
                // Values that exist before any page is answered can be read from anywhere.
                if ($target->type === FormField::TYPE_CALCULATED || $target->isHiddenFromRespondent()) {
                    continue;
                }
                if (($pageOf[(int)$target->id] ?? 0) > $own) {
                    $errors[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” uses [{name}], which is on a later page, so it has no answer yet when the rule runs.', [
                        'label' => $label,
                        'name' => $name,
                    ]);
                }
            }
        }
        return array_values(array_unique($errors));
    }
}
