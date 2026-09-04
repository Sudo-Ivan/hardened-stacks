<?php

namespace HardenedStacks\SpamProtection;

use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class AiClient
{
    public function __construct(
        private SettingsRepositoryInterface $settings,
        private LoggerInterface $logger
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '' && $this->baseUrl() !== '' && $this->model() !== '';
    }

    public function isEnabled(): bool
    {
        return (bool) (int) $this->settings->get('hardened-stacks-spam-protection.enabled', 0)
            && $this->isConfigured();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function classify(string $kind, array $context): SpamVerdict
    {
        if (! $this->isEnabled()) {
            $this->logger->warning('hardened-stacks-spam-protection: classify skipped (disabled or unconfigured)', [
                'kind' => $kind,
            ]);

            return SpamVerdict::clean();
        }

        $payload = [
            'model' => $this->model(),
            'temperature' => 0,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'kind' => $kind,
                        'context' => $context,
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
                ],
            ],
        ];

        try {
            $response = $this->request($payload);
            $content = $response['choices'][0]['message']['content'] ?? null;
            if (! is_string($content) || $content === '') {
                return $this->onFailure('empty model response');
            }

            $decoded = json_decode($content, true);
            if (! is_array($decoded)) {
                return $this->onFailure('invalid model json');
            }

            $verdict = SpamVerdict::fromArray($decoded);
            $this->logger->info('hardened-stacks-spam-protection: AI verdict', [
                'kind' => $kind,
                'is_spam' => $verdict->isSpam,
                'confidence' => $verdict->confidence,
                'actions' => $verdict->actions,
                'reason' => $verdict->reason,
            ]);

            return $verdict;
        } catch (Throwable $e) {
            $this->logger->warning('hardened-stacks-spam-protection: AI request failed', [
                'kind' => $kind,
                'error' => $e->getMessage(),
            ]);

            return $this->onFailure($e->getMessage());
        }
    }

    private function onFailure(string $reason): SpamVerdict
    {
        if ($this->failOpen()) {
            return SpamVerdict::clean();
        }

        return new SpamVerdict(true, 100, ['hide_post'], 'AI unavailable: '.$reason);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a forum spam classifier for a game preservation community.
Decide whether the submitted content or signup looks like spam, scams, SEO abuse, malware links, mass advertising, or bot registration.
Preservation posts often include many legitimate download or archive links. That alone is not spam.
Administrators and owners can still post spam. Classify the content itself. Do not treat author is_admin as a reason to mark clean.
Respond with JSON only using this schema:
{
  "is_spam": boolean,
  "confidence": number from 0 to 100,
  "actions": array of zero or more of ["hide_post","hide_discussion","lock_discussion","suspend_user"],
  "reason": short string
}
Only recommend actions when is_spam is true.
Prefer hide_post for mild spam. Use hide_discussion and lock_discussion for topic-starting spam. Use suspend_user for clear bots or repeat offenders.
PROMPT;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function request(array $payload): array
    {
        $url = rtrim($this->baseUrl(), '/').'/chat/completions';
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new \RuntimeException('Failed to encode AI request');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer '.$this->apiKey(),
        ];

        $referer = getenv('SPAM_AI_HTTP_REFERER');
        if (is_string($referer) && $referer !== '') {
            $headers[] = 'HTTP-Referer: '.$referer;
        }

        $title = getenv('SPAM_AI_APP_TITLE');
        if (is_string($title) && $title !== '') {
            $headers[] = 'X-Title: '.$title;
        }

        if (function_exists('curl_init')) {
            return $this->requestWithCurl($url, $headers, $body);
        }

        return $this->requestWithStreams($url, $headers, $body);
    }

    /**
     * @param list<string> $headers
     * @return array<string, mixed>
     */
    private function requestWithCurl(string $url, array $headers, string $body): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Failed to init curl');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException($error !== '' ? $error : 'curl request failed');
        }

        return $this->decodeResponse($raw, $status);
    }

    /**
     * @param list<string> $headers
     * @return array<string, mixed>
     */
    private function requestWithStreams(string $url, array $headers, string $body): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);

        $raw = file_get_contents($url, false, $context);
        if ($raw === false) {
            throw new \RuntimeException('stream request failed');
        }

        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }

        return $this->decodeResponse($raw, $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(string $raw, int $status): array
    {
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('AI response was not JSON');
        }

        if ($status >= 400) {
            $message = is_string($decoded['error']['message'] ?? null)
                ? (string) $decoded['error']['message']
                : 'HTTP '.$status;
            throw new \RuntimeException($message);
        }

        return $decoded;
    }

    private function apiKey(): string
    {
        $setting = trim((string) $this->settings->get('hardened-stacks-spam-protection.api_key', ''));
        if ($setting !== '') {
            return $setting;
        }

        $env = getenv('SPAM_AI_API_KEY');

        return is_string($env) ? trim($env) : '';
    }

    private function baseUrl(): string
    {
        $setting = trim((string) $this->settings->get('hardened-stacks-spam-protection.base_url', ''));
        if ($setting !== '') {
            return rtrim($setting, '/');
        }

        $env = getenv('SPAM_AI_BASE_URL');
        if (is_string($env) && trim($env) !== '') {
            return rtrim(trim($env), '/');
        }

        return 'https://openrouter.ai/api/v1';
    }

    private function model(): string
    {
        $setting = trim((string) $this->settings->get('hardened-stacks-spam-protection.model', ''));
        if ($setting !== '') {
            return $setting;
        }

        $env = getenv('SPAM_AI_MODEL');
        if (is_string($env) && trim($env) !== '') {
            return trim($env);
        }

        return 'openai/gpt-4o-mini';
    }

    private function failOpen(): bool
    {
        return (bool) (int) $this->settings->get('hardened-stacks-spam-protection.fail_open', 1);
    }
}
