<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\Module;
use Yii;
use yii\base\Exception;

/**
 * LLM chat client (OpenAI-compatible + Anthropic Messages) with usage logging.
 */
class LlmClient
{
    private const TIMEOUT = 120;
    private const ANTHROPIC_VERSION = '2023-06-01';

    public const PROVIDER_OPENAI = 'openai';
    public const PROVIDER_ANTHROPIC = 'anthropic';

    public static function baseAllowed(string $base): bool
    {
        $parts = parse_url($base);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }
        $host = strtolower((string)($parts['host'] ?? ''));
        $allowed = array_merge(['api.openai.com', 'api.anthropic.com'], array_map('strtolower', (array)(Yii::$app->params['thiscoveryForms.llmHosts'] ?? [])));
        foreach ($allowed as $entry) {
            $entry = trim((string)$entry);
            if ($entry !== '' && ($host === $entry || (str_starts_with($entry, '*.') && str_ends_with($host, substr($entry, 1))))) {
                return true;
            }
        }
        return false;
    }

    public function isConfigured(): bool
    {
        return Module::isFromBriefLlmEnabled();
    }

    public static function providerLabels(): array
    {
        return [
            self::PROVIDER_OPENAI => Yii::t('ThiscoveryFormsModule.base', 'OpenAI (or compatible)'),
            self::PROVIDER_ANTHROPIC => Yii::t('ThiscoveryFormsModule.base', 'Anthropic (Claude)'),
        ];
    }

    /**
     * @param list<array{role: string, content: string}> $messages
     * @return array{content: string, usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int}, stop_reason: string}
     */
    public function chat(array $messages, string $purpose, ?string $sessionId = null, ?int $formId = null): array
    {
        $started = microtime(true);
        $module = Yii::$app->getModule('thiscovery-forms');
        if (!$module instanceof Module) {
            throw new Exception(Yii::t('ThiscoveryFormsModule.base', 'Thiscovery Forms is not available.'));
        }
        $apiKey = trim((string)$module->settings->get(Module::SETTING_LLM_API_KEY, ''));
        if ($apiKey === '') {
            throw new Exception(Yii::t('ThiscoveryFormsModule.base', 'LLM API key is not configured.'));
        }

        $provider = strtolower(trim((string)$module->settings->get(Module::SETTING_LLM_PROVIDER, self::PROVIDER_OPENAI)));
        if ($provider !== self::PROVIDER_ANTHROPIC) {
            $provider = self::PROVIDER_OPENAI;
        }

        $defaultModel = $provider === self::PROVIDER_ANTHROPIC ? 'claude-sonnet-4-5' : 'gpt-4o-mini';
        $model = trim((string)$module->settings->get(Module::SETTING_LLM_MODEL, $defaultModel)) ?: $defaultModel;
        $base = rtrim(trim((string)$module->settings->get(Module::SETTING_LLM_API_BASE, '')), '/');
        // The API key goes only to https on a known provider host, or to a host listed in the
        // server config (params['thiscoveryForms.llmHosts']), never to any host set in the UI (SEC-18).
        if ($base !== '' && !self::baseAllowed($base)) {
            throw new Exception(Yii::t('ThiscoveryFormsModule.base', 'The LLM API address is not an allowed https host.'));
        }
        // Briefs can contain names, emails and phone numbers: redacted before they leave (SEC-18).
        $redactor = new PiiRedactor();
        foreach ($messages as $i => $message) {
            if (is_array($message) && isset($message['content']) && is_string($message['content'])) {
                $messages[$i]['content'] = (string)$redactor->redact($message['content']);
            }
        }

        try {
            if ($provider === self::PROVIDER_ANTHROPIC) {
                $result = $this->chatAnthropic($messages, $purpose, $apiKey, $model, $base);
            } else {
                $result = $this->chatOpenAi($messages, $purpose, $apiKey, $model, $base);
            }
        } catch (Exception $e) {
            $latency = (int)round((microtime(true) - $started) * 1000);
            $this->log($purpose, $sessionId, $formId, $provider, $model, 0, 0, $latency, false, substr($e->getMessage(), 0, 190));
            throw $e;
        }

        $latency = (int)round((microtime(true) - $started) * 1000);
        $content = $result['content'];
        $prompt = (int)$result['prompt_tokens'];
        $completion = (int)$result['completion_tokens'];
        if ($prompt === 0 && $completion === 0) {
            $prompt = max(1, (int)ceil(mb_strlen(json_encode($messages)) / 4));
            $completion = max(1, (int)ceil(mb_strlen($content) / 4));
        }
        $this->log($purpose, $sessionId, $formId, $provider, $model, $prompt, $completion, $latency, true, null);

        return [
            'content' => $content,
            'usage' => [
                'prompt_tokens' => $prompt,
                'completion_tokens' => $completion,
                'total_tokens' => $prompt + $completion,
            ],
            'stop_reason' => (string)($result['stop_reason'] ?? ''),
        ];
    }

    /**
     * @param list<array{role: string, content: string}> $messages
     * @return array{content: string, prompt_tokens: int, completion_tokens: int, stop_reason: string}
     */
    protected function chatOpenAi(array $messages, string $purpose, string $apiKey, string $model, string $base): array
    {
        if ($base === '') {
            $base = 'https://api.openai.com/v1';
        }
        $url = $base . '/chat/completions';

        $body = [
            'model' => $model,
            'temperature' => 0.2,
            'messages' => $messages,
            'max_tokens' => $purpose === 'map' ? 16384 : 4096,
        ];
        if ($purpose === 'map') {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $decoded = $this->postJson($url, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ], $body);

        $content = (string)($decoded['choices'][0]['message']['content'] ?? '');
        $usage = $decoded['usage'] ?? [];
        $finish = (string)($decoded['choices'][0]['finish_reason'] ?? '');

        return [
            'content' => $content,
            'prompt_tokens' => (int)($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int)($usage['completion_tokens'] ?? 0),
            'stop_reason' => $finish,
        ];
    }

    /**
     * @param list<array{role: string, content: string}> $messages
     * @return array{content: string, prompt_tokens: int, completion_tokens: int, stop_reason: string}
     */
    protected function chatAnthropic(array $messages, string $purpose, string $apiKey, string $model, string $base): array
    {
        if ($base === '') {
            $base = 'https://api.anthropic.com';
        }
        // Accept either https://api.anthropic.com or .../v1
        $url = preg_match('#/v1$#', $base)
            ? $base . '/messages'
            : $base . '/v1/messages';

        $systemParts = [];
        $apiMessages = [];
        foreach ($messages as $row) {
            $role = (string)($row['role'] ?? '');
            $content = (string)($row['content'] ?? '');
            if ($content === '') {
                continue;
            }
            if ($role === 'system') {
                $systemParts[] = $content;
                continue;
            }
            $apiRole = $role === 'assistant' ? 'assistant' : 'user';
            $last = $apiMessages ? $apiMessages[count($apiMessages) - 1] : null;
            if ($last && $last['role'] === $apiRole) {
                $apiMessages[count($apiMessages) - 1]['content'] .= "\n\n" . $content;
            } else {
                $apiMessages[] = ['role' => $apiRole, 'content' => $content];
            }
        }
        if (!$apiMessages) {
            throw new Exception(Yii::t('ThiscoveryFormsModule.base', 'LLM request has no user messages.'));
        }
        if ($apiMessages[0]['role'] !== 'user') {
            array_unshift($apiMessages, ['role' => 'user', 'content' => '(continue)']);
        }

        $maxTokens = $purpose === 'map' ? 16384 : 4096;
        $body = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => 0.2,
            'messages' => $apiMessages,
        ];
        if ($systemParts) {
            $body['system'] = implode("\n\n", $systemParts);
        }
        if ($purpose === 'map') {
            $body['system'] = trim(($body['system'] ?? '') . "\n\nRespond with a single JSON object only. No markdown fences or commentary.");
        }

        $decoded = $this->postJson($url, [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: ' . self::ANTHROPIC_VERSION,
        ], $body);

        $content = '';
        foreach (($decoded['content'] ?? []) as $block) {
            if (!is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'text') {
                $content .= (string)($block['text'] ?? '');
            }
        }
        $usage = $decoded['usage'] ?? [];

        return [
            'content' => $content,
            'prompt_tokens' => (int)($usage['input_tokens'] ?? 0),
            'completion_tokens' => (int)($usage['output_tokens'] ?? 0),
            'stop_reason' => (string)($decoded['stop_reason'] ?? ''),
        ];
    }

    /**
     * @param list<string> $headers
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    protected function postJson(string $url, array $headers, array $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => self::TIMEOUT,
        ]);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno || $response === false) {
            throw new Exception(Yii::t('ThiscoveryFormsModule.base', 'LLM request failed: {error}', [
                'error' => $error ?: 'network',
            ]));
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            throw new Exception(Yii::t('ThiscoveryFormsModule.base', 'LLM returned an invalid response (HTTP {status}).', [
                'status' => $status,
            ]));
        }
        if ($status >= 400) {
            $msg = (string)(
                $decoded['error']['message']
                ?? $decoded['error']['type']
                ?? (is_string($decoded['error'] ?? null) ? $decoded['error'] : null)
                ?? ('HTTP ' . $status)
            );
            throw new Exception(Yii::t('ThiscoveryFormsModule.base', 'LLM error: {error}', ['error' => $msg]));
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    public function chatJson(string $system, string $user, string $purpose, ?string $sessionId = null, ?int $formId = null): array
    {
        $result = $this->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], $purpose, $sessionId, $formId);

        $stop = (string)($result['stop_reason'] ?? '');
        if (in_array($stop, ['max_tokens', 'length'], true)) {
            throw new Exception(Yii::t(
                'ThiscoveryFormsModule.base',
                'AI response was truncated. Try a shorter brief, or regenerate.'
            ));
        }

        $decoded = $this->decodeJsonContent((string)$result['content']);
        if ($decoded === null) {
            throw new Exception(Yii::t('ThiscoveryFormsModule.base', 'AI did not return valid JSON.'));
        }
        return $decoded;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodeJsonContent(string $content): ?array
    {
        $content = trim($content);
        if ($content === '') {
            return null;
        }
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
            $content = preg_replace('/\s*```.*/s', '', $content) ?? $content;
            $content = trim($content);
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($content, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return null;
    }

    protected function log(
        string $purpose,
        ?string $sessionId,
        ?int $formId,
        string $provider,
        string $model,
        int $prompt,
        int $completion,
        int $latencyMs,
        bool $success,
        ?string $errorCode
    ): void {
        try {
            (new LlmUsageLogger())->log([
                'purpose' => $purpose,
                'session_id' => $sessionId,
                'form_id' => $formId,
                'provider' => $provider,
                'model' => $model,
                'prompt_tokens' => $prompt,
                'completion_tokens' => $completion,
                'latency_ms' => $latencyMs,
                'success' => $success ? 1 : 0,
                'error_code' => $errorCode,
            ]);
        } catch (\Throwable $e) {
            Yii::error('LLM usage log failed: ' . $e->getMessage(), 'thiscovery-forms');
        }
    }
}
