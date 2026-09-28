<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\commands;

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\FormPager;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Count completed answers on translated forms whose choice cells are empty.
 * Read-only. Prints a count.
 */
class DetectTranslationLossController extends Controller
{
    public function isTranslationLoss(FormAnswer $answer): bool
    {
        $form = $answer->form;
        if (!$form || (int)$answer->status !== FormAnswer::STATUS_COMPLETE || (int)$answer->is_test === 1) {
            return false;
        }
        $values = [];
        foreach ($answer->answerFields as $cell) {
            $values[(int)$cell->field_id] = $cell->value;
        }
        $visited = (new FormPager())->visitedFieldIds($form->fields, $values);
        $source = $this->languageBase((string)$form->getSourceLanguage());
        $response = $this->languageBase((string)($answer->getVars()['response_language'] ?? ''));
        $foreign = $response !== '' && $response !== $source;

        foreach ($form->fields as $field) {
            if (!$field->collectsAnswer() || !FormField::isChoiceType($field->type)) {
                continue;
            }
            $id = (int)$field->id;
            $raw = $values[$id] ?? null;
            $blank = $raw === null || $raw === '' || $raw === [];
            if ($foreign && $field->required && isset($visited[$id]) && $blank) {
                return true;
            }
            if ($blank) {
                continue;
            }
            $codes = [];
            foreach ($field->getChoicePairs() as $pair) {
                $codes[(string)$pair['code']] = true;
            }
            foreach ($this->choiceTokens($raw) as $token) {
                if ($token !== '' && !isset($codes[$token])) {
                    return true;
                }
            }
        }
        return false;
    }

    public function actionIndex(): int
    {
        $total = 0;
        $byForm = [];
        $query = FormAnswer::find()
            ->where(['status' => FormAnswer::STATUS_COMPLETE, 'is_test' => 0])
            ->with(['answerFields', 'form']);
        foreach ($query->each(100) as $answer) {
            if (!$answer->form || !$this->isTranslationLoss($answer)) {
                continue;
            }
            $total++;
            $formId = (int)$answer->form_id;
            $byForm[$formId] = ($byForm[$formId] ?? 0) + 1;
            unset($answer->form->fields);
        }
        ksort($byForm);
        foreach ($byForm as $formId => $count) {
            $this->stdout('form=' . $formId . ' translation_losses=' . $count . "\n");
        }
        $this->stdout('translation_losses=' . $total . "\n");
        return ExitCode::OK;
    }

    private function languageBase(string $code): string
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            return '';
        }
        return explode('-', $code)[0];
    }

    /**
     * @param mixed $raw
     * @return string[]
     */
    private function choiceTokens($raw): array
    {
        if (is_array($raw)) {
            $tokens = [];
            foreach ($raw as $item) {
                if (is_scalar($item)) {
                    $tokens[] = (string)$item;
                }
            }
            return $tokens;
        }
        $text = trim((string)$raw);
        if ($text !== '' && ($text[0] === '[' || $text[0] === '{')) {
            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                return $this->choiceTokens($decoded);
            }
        }
        return [$text];
    }
}
