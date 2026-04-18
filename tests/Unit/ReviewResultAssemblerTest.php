<?php

namespace Tests\Unit;

use App\AI\DTO\CriterionResult;
use App\AI\DTO\ReviewResult;
use App\AI\Services\ReviewResultAssembler;
use Tests\TestCase;

class ReviewResultAssemblerTest extends TestCase
{
    public function test_unsupported_criteria_do_not_reduce_other_scores(): void
    {
        $criteria = [
            [
                'id' => 'compile_file',
                'label' => 'Компиляция файла',
                'weight' => 30,
            ],
            [
                'id' => 'methods_more_than_6',
                'label' => 'Количество методов больше 6',
                'weight' => 30,
            ],
            [
                'id' => 'has_crud_methods',
                'label' => 'Наличие CRUD методов',
                'weight' => 40,
            ],
        ];

        $serverResults = [
            new CriterionResult(
                criterionId: 'compile_file',
                label: 'Компиляция файла',
                passed: false,
                score: null,
                evidence: ['Компиляция для этого формата не поддерживается'],
                feedback: 'Unsupported',
                status: 'unsupported',
                source: 'server',
                weight: 30,
            ),
        ];

        $llmResult = new ReviewResult(
            summary: 'Модель ошибочно занизила общую оценку.',
            recommendedScore: 0,
            confidence: 0.77,
            criteriaResults: [
                new CriterionResult(
                    criterionId: 'methods_more_than_6',
                    label: 'Количество методов больше 6',
                    passed: true,
                    score: 30,
                    evidence: ['function_like_count = 17'],
                    feedback: 'Условие выполнено.',
                ),
                new CriterionResult(
                    criterionId: 'has_crud_methods',
                    label: 'Наличие CRUD методов',
                    passed: true,
                    score: 40,
                    evidence: ['index, store, show, update, destroy'],
                    feedback: 'Условие выполнено.',
                ),
            ],
            unsupportedChecks: ['Скомпилировать файл'],
            raw: ['provider' => 'fake'],
        );

        $result = app(ReviewResultAssembler::class)->assemble(
            criteria: $criteria,
            serverResults: $serverResults,
            llmResult: $llmResult,
            unsupportedChecks: ['Компиляция файла'],
        );

        $this->assertSame(100, $result->recommendedScore);
        $this->assertStringContainsString('Неподдерживаемые проверки не влияли', $result->summary);
        $this->assertSame('unsupported', $result->criteriaResults[0]->status);
        $this->assertSame('server', $result->criteriaResults[0]->source);
    }
}
