<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services;

use Yii;
use yii\base\Exception;

/**
 * Chat to refine a research brief before survey generation.
 */
class BriefChatService
{
    private LlmClient $llm;

    public function __construct(?LlmClient $llm = null)
    {
        $this->llm = $llm ?? new LlmClient();
    }

    /**
     * @param list<array{role: string, content: string}> $history
     * @return array{reply: string, brief: string, briefChanged: bool, history: list<array{role: string, content: string}>}
     */
    public function turn(string $brief, array $history, string $userMessage, string $sessionId): array
    {
        if (!$this->llm->isConfigured()) {
            throw new Exception(Yii::t('ThiscoveryFormsModule.base', 'LLM assist is not enabled.'));
        }
        $userMessage = trim($userMessage);
        if ($userMessage === '') {
            throw new Exception(Yii::t('ThiscoveryFormsModule.base', 'Enter a message.'));
        }

        $typeGuide = SurveyDesignMapper::fromBriefTypeGuide();
        $system = 'You help form creators refine a research survey brief that will be turned into Thiscovery Forms questions. '
            . 'Supported question types for create-from-brief (use these names when advising): ' . $typeGuide . '. '
            . 'Prefer common types (text, textarea, radio, checkbox, dropdown, rating, number, email, date, page_break, rich_text). '
            . 'Advanced types (ranking, grid_single, grid_multi, file) are available but keep the first draft simple unless asked. '
            . 'Other FormField types (maxdiff, map, image_area, etc.) exist in the builder later but should not be promised in the brief unless the creator asks. '
            . 'When the creator asks to change the survey (add/remove/reorder questions, shorten, change tone, add sections, consent, demographics, etc.), '
            . 'you MUST update the working brief and include the full revised brief in this exact block at the end of your reply:\n'
            . "<<<BRIEF>>>\n...complete updated brief text...\n<<<END_BRIEF>>>\n"
            . 'Put the whole brief in that block (not a diff). Keep the brief concrete: numbered questions, choice lists, section headings, and suggested types where helpful (e.g. “Type: radio”). '
            . 'Only omit the BRIEF block if you are answering a meta question and not changing the survey content. '
            . 'After updating the brief, tell the creator to click “Regenerate questions from brief”. '
            . 'Do not invent respondent PII.';

        $messages = [['role' => 'system', 'content' => $system]];
        $messages[] = ['role' => 'user', 'content' => "Current brief:\n" . $brief];
        foreach ($history as $row) {
            if (!is_array($row) || empty($row['role']) || empty($row['content'])) {
                continue;
            }
            if (!in_array($row['role'], ['user', 'assistant'], true)) {
                continue;
            }
            $messages[] = ['role' => $row['role'], 'content' => (string)$row['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $result = $this->llm->chat($messages, 'chat', $sessionId, null);
        $reply = trim($result['content']);
        $newBrief = $brief;
        $briefChanged = false;
        if (preg_match('/<<<BRIEF>>>\s*(.*?)\s*<<<END_BRIEF>>>/s', $reply, $m)) {
            $candidate = trim($m[1]);
            if ($candidate !== '') {
                $newBrief = $candidate;
                $briefChanged = ($newBrief !== $brief);
            }
            $reply = trim(preg_replace('/<<<BRIEF>>>.*?<<<END_BRIEF>>>/s', '', $reply) ?? $reply);
        }

        // If the user clearly asked for survey edits but the model forgot the block, append their ask to the brief.
        if (!$briefChanged && $this->looksLikeSurveyEditRequest($userMessage)) {
            $newBrief = rtrim($brief) . "\n\nCreator revision notes:\n- " . $userMessage;
            $briefChanged = true;
            if ($reply === '') {
                $reply = Yii::t('ThiscoveryFormsModule.base', 'I noted your changes on the brief. Click “Regenerate questions from brief” to refresh the proposal.');
            } else {
                $reply .= "\n\n" . Yii::t('ThiscoveryFormsModule.base', 'Click “Regenerate questions from brief” to refresh the proposal.');
            }
        }

        $history[] = ['role' => 'user', 'content' => $userMessage];
        $history[] = ['role' => 'assistant', 'content' => $result['content']];
        if (count($history) > 24) {
            $history = array_slice($history, -24);
        }

        return [
            'reply' => $reply !== '' ? $reply : Yii::t('ThiscoveryFormsModule.base', 'Brief updated.'),
            'brief' => $newBrief,
            'briefChanged' => $briefChanged,
            'history' => $history,
        ];
    }

    /**
     * Recent user chat lines to steer remapping even when the brief text is similar.
     *
     * @param list<array{role?: string, content?: string}> $history
     */
    public static function recentInstructions(array $history, int $limit = 6): string
    {
        $notes = [];
        foreach (array_reverse($history) as $row) {
            if (!is_array($row) || ($row['role'] ?? '') !== 'user') {
                continue;
            }
            $text = trim((string)($row['content'] ?? ''));
            if ($text === '') {
                continue;
            }
            $notes[] = $text;
            if (count($notes) >= $limit) {
                break;
            }
        }
        if (!$notes) {
            return '';
        }
        $notes = array_reverse($notes);
        return "Creator chat instructions (apply these when mapping):\n- " . implode("\n- ", $notes);
    }

    protected function looksLikeSurveyEditRequest(string $message): bool
    {
        return (bool)preg_match(
            '/\b(add|remove|delete|drop|shorten|longer|change|update|include|exclude|reorder|replace|more|fewer|less|consent|demographic|section|page|question|option|scale|rating|skip)\b/iu',
            $message
        );
    }
}
