<?php

namespace App\AI\Services;

use App\AI\Contracts\LLMClientInterface;
use App\AI\DTO\CompiledReviewPayload;
use App\AI\DTO\ReviewResult;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class OpenRouterClient implements LLMClientInterface
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly PromptBuilder $promptBuilder,
        private readonly AIResultSchemaValidator $validator,
    ) {
    }

    public function analyze(CompiledReviewPayload $payload): ReviewResult
    {
        $apiKey = (string) config('ai.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('AI_API_KEY is not configured.');
        }

        $prompt = $this->promptBuilder->build($payload);
        $url = config('ai.base_url').'/chat/completions';

        try {
            $response = $this->http
                ->timeout((int) config('ai.timeout', 120))
                ->withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => (string) config('app.url'),
                    'X-Title' => (string) config('app.name', 'AIditorium'),
                ])
                ->post($url, [
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
                    'temperature' => 0.2,
                    'response_format' => [
                        'type' => 'json_object',
                    ],
                ])
                ->throw();
        } catch (RequestException $exception) {
            $message = $exception->response?->json('error.message')
                ?? $exception->response?->body()
                ?? $exception->getMessage();

            throw new RuntimeException("OpenRouter request failed: {$message}", previous: $exception);
        }

        $content = $response->json('choices.0.message.content');
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('OpenRouter returned an empty completion.');
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('OpenRouter returned invalid JSON content.');
        }

        return $this->validator->validate($decoded);
    }
}
