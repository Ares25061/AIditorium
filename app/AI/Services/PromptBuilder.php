<?php

namespace App\AI\Services;

use App\AI\DTO\CompiledReviewPayload;

class PromptBuilder
{
    /**
     * @return array{system: string, user: string}
     */
    public function build(CompiledReviewPayload $payload): array
    {
        $system = <<<'TEXT'
Ты — модуль автопроверки учебных работ в AIditorium.
Отвечай только валидным JSON без markdown и без пояснений вне JSON.
Оценивай только то, что реально видно в извлечённых артефактах.
Критерии из server_results уже проверены сервером: не переоценивай их и не противоречь им.
Критерии из unsupported_checks не должны автоматически занижать остальные критерии и итоговую оценку.
Не придумывай запуск внешних команд, тестов или другой runtime-анализ сверх того, что уже передано в payload.
JSON-схема ответа:
{
  "summary": "string",
  "recommended_score": 0,
  "confidence": 0.0,
  "unsupported_checks": ["string"],
  "criteria_results": [
    {
      "criterion_id": "string",
      "label": "string",
      "passed": true,
      "score": 0,
      "evidence": ["string"],
      "feedback": "string"
    }
  ]
}
TEXT;

        $user = json_encode([
            'task' => 'Проведи автопроверку загруженной работы ученика.',
            'submission' => $payload->submission,
            'server_results' => $payload->serverResults,
            'criteria' => $payload->criteria,
            'unsupported_checks' => $payload->unsupportedChecks,
            'custom_prompt' => $payload->customPrompt,
            'artifacts' => $payload->artifacts,
            'requirements' => [
                'language' => 'ru',
                'score_range' => [0, 100],
                'strict_json' => true,
                'do_not_execute_code' => true,
                'evaluate_only_criteria_from_payload' => true,
                'do_not_penalize_unsupported_checks' => true,
                'score_per_criterion_is_percent' => true,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            'system' => $system,
            'user' => $user ?: '{}',
        ];
    }
}
