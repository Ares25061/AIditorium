<?php

namespace Tests\Unit;

use App\AI\DTO\CompiledReviewPayload;
use App\AI\Services\OpenRouterClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterClientTest extends TestCase
{
    public function test_client_retries_empty_completion_and_accepts_markdown_json(): void
    {
        config([
            'ai.api_key' => 'test-key',
            'ai.base_url' => 'https://openrouter.ai/api/v1',
            'ai.model' => 'minimax/minimax-m2.5:free',
            'ai.timeout' => 10,
            'ai.connect_timeout' => 2,
            'ai.temperature' => 0.1,
            'ai.max_completion_tokens' => 4096,
            'ai.openrouter.retry_attempts' => 2,
            'ai.openrouter.retry_delay_ms' => 0,
            'ai.openrouter.json_mode' => true,
            'ai.openrouter.retry_without_json_mode' => true,
            'ai.openrouter.reasoning_effort' => 'none',
            'ai.openrouter.exclude_reasoning' => true,
        ]);

        Http::fakeSequence()
            ->push([
                'choices' => [
                    [
                        'finish_reason' => null,
                        'message' => [
                            'role' => 'assistant',
                            'content' => '',
                        ],
                    ],
                ],
                'usage' => [
                    'completion_tokens' => 0,
                ],
            ])
            ->push([
                'choices' => [
                    [
                        'finish_reason' => 'stop',
                        'message' => [
                            'role' => 'assistant',
                            'content' => <<<'JSON'
```json
{
  "summary": "Работа проверена.",
  "recommended_score": 84,
  "confidence": 0.75,
  "unsupported_checks": [],
  "criteria_results": [
    {
      "criterion_id": "criterion_1",
      "label": "Корректность",
      "passed": true,
      "score": 84,
      "evidence": ["Ответ содержит решение."],
      "feedback": "Критерий выполнен."
    }
  ]
}
```
JSON,
                        ],
                    ],
                ],
                'usage' => [
                    'completion_tokens' => 120,
                ],
            ]);

        $result = app(OpenRouterClient::class)->analyze($this->payload());

        $this->assertSame(84, $result->recommendedScore);
        $this->assertSame('Работа проверена.', $result->summary);
        Http::assertSentCount(2);

        $requests = Http::recorded()
            ->map(static fn (array $record) => $record[0])
            ->filter(static fn (Request $request) => str_contains($request->url(), '/chat/completions'))
            ->values();

        $this->assertTrue(isset($requests[0]->data()['response_format']));
        $this->assertFalse(isset($requests[1]->data()['response_format']));
        $this->assertFalse(isset($requests[0]->data()['reasoning']));
        $this->assertSame(4096, $requests[0]->data()['max_completion_tokens']);
    }

    public function test_client_retries_without_json_mode_when_provider_rejects_response_format(): void
    {
        config([
            'ai.api_key' => 'test-key',
            'ai.base_url' => 'https://openrouter.ai/api/v1',
            'ai.timeout' => 10,
            'ai.connect_timeout' => 2,
            'ai.temperature' => 0.1,
            'ai.max_completion_tokens' => 4096,
            'ai.openrouter.retry_attempts' => 2,
            'ai.openrouter.retry_delay_ms' => 0,
            'ai.openrouter.json_mode' => true,
            'ai.openrouter.retry_without_json_mode' => true,
            'ai.openrouter.reasoning_effort' => 'none',
            'ai.openrouter.exclude_reasoning' => true,
        ]);

        Http::fakeSequence()
            ->push([
                'error' => [
                    'message' => 'response_format is not supported for this model.',
                ],
            ], 400)
            ->push([
                'choices' => [
                    [
                        'finish_reason' => 'stop',
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'summary' => 'Проверка выполнена без JSON mode.',
                                'recommended_score' => 91,
                                'confidence' => 0.8,
                                'unsupported_checks' => [],
                                'criteria_results' => [
                                    [
                                        'criterion_id' => 'criterion_1',
                                        'label' => 'Корректность',
                                        'passed' => true,
                                        'score' => 91,
                                        'evidence' => ['Вторая попытка прошла без response_format.'],
                                        'feedback' => 'Проверка выполнена.',
                                    ],
                                ],
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ]);

        $result = app(OpenRouterClient::class)->analyze($this->payload());

        $this->assertSame(91, $result->recommendedScore);

        $requests = Http::recorded()
            ->map(static fn (array $record) => $record[0])
            ->filter(static fn (Request $request) => str_contains($request->url(), '/chat/completions'))
            ->values();

        $this->assertTrue(isset($requests[0]->data()['response_format']));
        $this->assertFalse(isset($requests[1]->data()['response_format']));
    }

    public function test_client_retries_connection_timeout(): void
    {
        config([
            'ai.api_key' => 'test-key',
            'ai.base_url' => 'https://openrouter.ai/api/v1',
            'ai.timeout' => 10,
            'ai.connect_timeout' => 2,
            'ai.temperature' => 0.1,
            'ai.max_completion_tokens' => 4096,
            'ai.openrouter.retry_attempts' => 2,
            'ai.openrouter.retry_delay_ms' => 0,
            'ai.openrouter.json_mode' => true,
            'ai.openrouter.retry_without_json_mode' => true,
            'ai.openrouter.reasoning_effort' => 'none',
            'ai.openrouter.exclude_reasoning' => true,
        ]);

        $attempt = 0;

        Http::fake(function () use (&$attempt) {
            $attempt++;

            if ($attempt === 1) {
                throw new ConnectionException('cURL error 28: Operation timed out after 120000 milliseconds.');
            }

            return Http::response([
                'choices' => [
                    [
                        'finish_reason' => 'stop',
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'summary' => 'AI-проверка завершена после retry.',
                                'recommended_score' => 77,
                                'confidence' => 0.7,
                                'unsupported_checks' => [],
                                'criteria_results' => [
                                    [
                                        'criterion_id' => 'criterion_1',
                                        'label' => 'Корректность',
                                        'passed' => true,
                                        'score' => 77,
                                        'evidence' => ['Ответ получен после повторной попытки.'],
                                        'feedback' => 'Проверка выполнена.',
                                    ],
                                ],
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ]);
        });

        $result = app(OpenRouterClient::class)->analyze($this->payload());

        $this->assertSame(77, $result->recommendedScore);
        $this->assertSame(2, $attempt);
    }

    public function test_client_can_call_nekocode_compatible_model_config(): void
    {
        config([
            'ai.api_key' => null,
            'ai.base_url' => 'https://openrouter.ai/api/v1',
            'ai.timeout' => 10,
            'ai.connect_timeout' => 2,
            'ai.temperature' => 0.1,
            'ai.max_completion_tokens' => 4096,
            'ai.openrouter.retry_attempts' => 1,
            'ai.openrouter.retry_delay_ms' => 0,
            'ai.openrouter.json_mode' => true,
            'ai.openrouter.retry_without_json_mode' => true,
            'ai.openrouter.reasoning_effort' => 'none',
            'ai.openrouter.exclude_reasoning' => true,
            'ai.models.deepseek_v4' => [
                'label' => 'Deepseek v4',
                'provider' => 'nekocode',
                'base_url' => 'https://gateway.nekocode.app/andromeda/v1',
                'api_key' => 'neko-test-key',
                'model' => 'gpt-5.5',
            ],
        ]);

        Http::fake([
            'https://gateway.nekocode.app/andromeda/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'finish_reason' => 'stop',
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'summary' => 'NekoCode модель проверила ответ.',
                                'recommended_score' => 93,
                                'confidence' => 0.84,
                                'unsupported_checks' => [],
                                'criteria_results' => [
                                    [
                                        'criterion_id' => 'criterion_1',
                                        'label' => 'Корректность',
                                        'passed' => true,
                                        'score' => 93,
                                        'evidence' => ['Ответ получен через NekoCode gateway.'],
                                        'feedback' => 'Проверка выполнена.',
                                    ],
                                ],
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ]),
        ]);

        $result = app(OpenRouterClient::class)->analyze($this->payload('nekocode', 'gpt-5.5'));

        $this->assertSame(93, $result->recommendedScore);
        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://gateway.nekocode.app/andromeda/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer neko-test-key')
                && $request->data()['model'] === 'gpt-5.5';
        });
    }

    private function payload(string $provider = 'openrouter', string $model = 'minimax/minimax-m2.5:free'): CompiledReviewPayload
    {
        return new CompiledReviewPayload(
            reviewRunId: 1,
            provider: $provider,
            model: $model,
            submission: [
                'task_id' => 1,
                'task_name' => 'Тестовое задание',
                'student_id' => 2,
                'student_name' => 'Студент',
                'file_id' => 3,
                'file_name' => 'solution.txt',
                'file_extension' => 'txt',
            ],
            context: [
                'task' => [
                    'id' => 1,
                    'name' => 'Тестовое задание',
                    'description' => 'Описание задания.',
                    'deadline_at' => '2026-06-20T21:00:00.000000Z',
                ],
                'course' => [
                    'id' => 10,
                    'name' => 'Тестовый курс',
                ],
                'discipline' => [
                    'id' => 20,
                    'name' => 'Тестовая дисциплина',
                ],
                'submission' => [
                    'submitted_at' => '2026-06-13T21:00:00.000000Z',
                ],
            ],
            artifacts: [
                'kind' => 'text',
                'text_excerpt' => 'Ответ студента.',
            ],
            criteria: [
                [
                    'id' => 'criterion_1',
                    'label' => 'Корректность',
                    'description' => 'Проверь решение.',
                    'checks' => [],
                    'weight' => 100,
                ],
            ],
            serverResults: [],
            unsupportedChecks: [],
            customPrompt: null,
        );
    }
}
