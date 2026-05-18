<?php

namespace App\AI\Services;

use App\AI\Contracts\LLMClientInterface;
use App\AI\DTO\CompiledReviewPayload;
use App\AI\DTO\ReviewResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenRouterClient implements LLMClientInterface
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly PromptBuilder $promptBuilder,
        private readonly AIResultSchemaValidator $validator,
        private readonly AIModelResolver $modelResolver,
    ) {}

    public function analyze(CompiledReviewPayload $payload): ReviewResult
    {
        $modelConfig = $this->modelResolver->resolveProviderModel($payload->provider, $payload->model);
        $apiKey = $modelConfig['api_key'];
        if ($apiKey === '') {
            throw new RuntimeException('AI API key is not configured for model '.$modelConfig['key'].'.');
        }

        $prompt = $this->promptBuilder->build($payload);
        $url = $modelConfig['base_url'].'/chat/completions';
        $baseRequest = $this->buildRequestPayload($payload, $prompt);
        $maxAttempts = max(1, (int) config('ai.openrouter.retry_attempts', 3));
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $requestPayload = $this->prepareAttemptPayload($baseRequest, $attempt);

            try {
                $response = $this->sendRequest($url, $apiKey, $requestPayload);
            } catch (ConnectionException $exception) {
                $lastException = $this->buildConnectionException($exception, $attempt, $maxAttempts);

                if ($attempt >= $maxAttempts) {
                    throw $lastException;
                }

                $this->waitBeforeRetry($attempt);

                continue;
            } catch (RequestException $exception) {
                $lastException = $this->buildRequestException($exception);

                if (! $this->shouldRetryRequestException($exception, $attempt, $maxAttempts)) {
                    throw $lastException;
                }

                $this->waitBeforeRetry($attempt);

                continue;
            }

            $content = $this->extractContent($response);
            if ($content === null) {
                $lastException = $this->buildEmptyCompletionException($response, $attempt, $maxAttempts);
                $this->logEmptyCompletion($response, $payload->model, $attempt, $maxAttempts);

                if ($attempt < $maxAttempts) {
                    $this->waitBeforeRetry($attempt);

                    continue;
                }

                throw $lastException;
            }

            $decoded = $this->decodeJsonContent($content);
            if (! is_array($decoded)) {
                $lastException = new RuntimeException('OpenRouter returned invalid JSON content.');

                if ($attempt < $maxAttempts) {
                    $this->waitBeforeRetry($attempt);

                    continue;
                }

                throw $lastException;
            }

            return $this->validator->validate($decoded);
        }

        throw $lastException ?? new RuntimeException('OpenRouter request failed.');
    }

    /**
     * @param  array{system: string, user: string}  $prompt
     * @return array<string, mixed>
     */
    private function buildRequestPayload(CompiledReviewPayload $payload, array $prompt): array
    {
        $requestPayload = [
            'model' => $payload->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $prompt['system'],
                ],
                [
                    'role' => 'user',
                    'content' => $prompt['user'],
                ],
            ],
            'temperature' => (float) config('ai.temperature', 0.1),
            'max_completion_tokens' => max(256, (int) config('ai.max_completion_tokens', 4096)),
        ];

        $reasoning = $this->buildReasoningPayload();
        if ($reasoning !== []) {
            $requestPayload['reasoning'] = $reasoning;
        }

        return $requestPayload;
    }

    /**
     * @param  array<string, mixed>  $baseRequest
     * @return array<string, mixed>
     */
    private function prepareAttemptPayload(array $baseRequest, int $attempt): array
    {
        $requestPayload = $baseRequest;
        $useJsonMode = (bool) config('ai.openrouter.json_mode', true);
        $retryWithoutJsonMode = (bool) config('ai.openrouter.retry_without_json_mode', true);

        if ($useJsonMode && (! $retryWithoutJsonMode || $attempt === 1)) {
            $requestPayload['response_format'] = [
                'type' => 'json_object',
            ];
        } else {
            unset($requestPayload['response_format']);
            $requestPayload['messages'][] = [
                'role' => 'user',
                'content' => 'JSON mode may be unavailable for this model. Return exactly one valid JSON object. Do not wrap it in markdown.',
            ];
        }

        return $requestPayload;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReasoningPayload(): array
    {
        $reasoning = [];
        $effort = trim((string) config('ai.openrouter.reasoning_effort', 'none'));

        if ($effort !== '' && strcasecmp($effort, 'none') !== 0) {
            $reasoning['effort'] = $effort;
        }

        if ($reasoning !== [] && (bool) config('ai.openrouter.exclude_reasoning', true)) {
            $reasoning['exclude'] = true;
        }

        return $reasoning;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendRequest(string $url, string $apiKey, array $payload): Response
    {
        return $this->http
            ->timeout((int) config('ai.timeout', 120))
            ->connectTimeout((int) config('ai.connect_timeout', 20))
            ->withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => (string) config('app.url'),
                'X-Title' => (string) config('app.name', 'AIditorium'),
            ])
            ->post($url, $payload)
            ->throw();
    }

    private function extractContent(Response $response): ?string
    {
        $content = $response->json('choices.0.message.content');

        if (is_array($content)) {
            $text = '';
            foreach ($content as $part) {
                if (is_array($part) && isset($part['text'])) {
                    $text .= (string) $part['text'];
                } elseif (is_string($part)) {
                    $text .= $part;
                }
            }

            $content = $text;
        }

        if (! is_string($content) || trim($content) === '') {
            return null;
        }

        return trim($content);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonContent(string $content): ?array
    {
        $candidates = array_values(array_unique(array_filter([
            trim($content),
            $this->extractMarkdownJsonBlock($content),
            $this->extractBalancedJsonObject($content),
        ])));

        foreach ($candidates as $candidate) {
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function extractMarkdownJsonBlock(string $content): ?string
    {
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $content, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    private function extractBalancedJsonObject(string $content): ?string
    {
        $start = strpos($content, '{');
        if ($start === false) {
            return null;
        }

        $length = strlen($content);
        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($index = $start; $index < $length; $index++) {
            $char = $content[$index];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;

                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;

                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;

                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($content, $start, $index - $start + 1);
                }
            }
        }

        return null;
    }

    private function buildRequestException(RequestException $exception): RuntimeException
    {
        $message = $exception->response?->json('error.message')
            ?? $exception->response?->body()
            ?? $exception->getMessage();

        return new RuntimeException("OpenRouter request failed: {$message}", previous: $exception);
    }

    private function buildConnectionException(ConnectionException $exception, int $attempt, int $maxAttempts): RuntimeException
    {
        return new RuntimeException(
            "OpenRouter connection failed after attempt {$attempt}/{$maxAttempts}: {$exception->getMessage()}",
            previous: $exception,
        );
    }

    private function shouldRetryRequestException(RequestException $exception, int $attempt, int $maxAttempts): bool
    {
        if ($attempt >= $maxAttempts) {
            return false;
        }

        $status = $exception->response?->status();

        if ($status === null || $status === 429 || $status >= 500) {
            return true;
        }

        $message = mb_strtolower((string) (
            $exception->response?->json('error.message')
            ?? $exception->response?->body()
            ?? $exception->getMessage()
        ), 'UTF-8');

        return in_array($status, [400, 422], true)
            && preg_match('/response_format|json[_\s-]*mode|reasoning|unsupported parameter|unsupported param|invalid parameter|invalid param/u', $message) === 1;
    }

    private function buildEmptyCompletionException(Response $response, int $attempt, int $maxAttempts): RuntimeException
    {
        $finishReason = $response->json('choices.0.finish_reason')
            ?? $response->json('choices.0.native_finish_reason')
            ?? 'empty';
        $usage = $response->json('usage') ?: [];
        $completionTokens = is_array($usage) ? ($usage['completion_tokens'] ?? 'unknown') : 'unknown';

        return new RuntimeException(
            "OpenRouter returned an empty completion after attempt {$attempt}/{$maxAttempts} "
            ."(finish_reason={$finishReason}, completion_tokens={$completionTokens}). "
            .'Try again or switch the AI model if the provider keeps returning zero-token responses.',
        );
    }

    private function logEmptyCompletion(Response $response, string $model, int $attempt, int $maxAttempts): void
    {
        Log::warning('OpenRouter returned an empty completion.', [
            'model' => $model,
            'attempt' => $attempt,
            'max_attempts' => $maxAttempts,
            'finish_reason' => $response->json('choices.0.finish_reason'),
            'native_finish_reason' => $response->json('choices.0.native_finish_reason'),
            'usage' => $response->json('usage'),
            'has_reasoning' => is_string($response->json('choices.0.message.reasoning'))
                && trim((string) $response->json('choices.0.message.reasoning')) !== '',
            'error' => $response->json('error.message') ?? $response->json('error'),
        ]);
    }

    private function waitBeforeRetry(int $attempt): void
    {
        $delayMs = max(0, (int) config('ai.openrouter.retry_delay_ms', 500));
        if ($delayMs === 0) {
            return;
        }

        usleep($delayMs * $attempt * 1000);
    }
}
