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
        $this->assertSame('none', $requests[0]->data()['reasoning']['effort']);
        $this->assertTrue($requests[0]->data()['reasoning']['exclude']);
        $this->assertSame(4096, $requests[0]->data()['max_completion_tokens']);
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

    private function payload(): CompiledReviewPayload
    {
        return new CompiledReviewPayload(
            reviewRunId: 1,
            provider: 'openrouter',
            model: 'minimax/minimax-m2.5:free',
            submission: [
                'task_id' => 1,
                'task_name' => 'Тестовое задание',
                'student_id' => 2,
                'student_name' => 'Студент',
                'file_id' => 3,
                'file_name' => 'solution.txt',
                'file_extension' => 'txt',
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
