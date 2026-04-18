<?php

namespace Tests\Unit;

use App\AI\Services\AIResultSchemaValidator;
use PHPUnit\Framework\TestCase;

class AIResultSchemaValidatorTest extends TestCase
{
    public function test_validator_accepts_strict_json_result(): void
    {
        $validator = new AIResultSchemaValidator();

        $result = $validator->validate([
            'summary' => 'Работа в целом корректная, но есть недочеты по стилю.',
            'recommended_score' => 87,
            'confidence' => 0.91,
            'unsupported_checks' => ['Скомпилировать проект'],
            'criteria_results' => [
                [
                    'criterion_id' => 'criterion_1',
                    'label' => 'Структура',
                    'passed' => true,
                    'score' => 90,
                    'evidence' => ['Найдены функции и понятные имена переменных'],
                    'feedback' => 'Структура решения хорошая.',
                ],
            ],
        ]);

        $this->assertSame('Работа в целом корректная, но есть недочеты по стилю.', $result->summary);
        $this->assertSame(87, $result->recommendedScore);
        $this->assertSame(0.91, $result->confidence);
        $this->assertCount(1, $result->criteriaResults);
        $this->assertSame('Структура', $result->criteriaResults[0]->label);
    }
}
