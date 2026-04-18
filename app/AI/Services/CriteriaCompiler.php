<?php

namespace App\AI\Services;

use App\AI\DTO\ReviewProfile;
use App\AI\DTO\StructuredCriterion;

class CriteriaCompiler
{
    private const UNSUPPORTED_KEYWORDS = [
        'compile', 'compilation', 'build', 'run', 'execute', 'execution', 'test',
        'pytest', 'phpunit', 'npm test', 'docker', 'sandbox', 'компил', 'собер',
        'запуск', 'выполн', 'тест', 'собрать проект', 'прогнать',
    ];

    /**
     * @return array{criteria: array<int, array<string, mixed>>, unsupported_checks: array<int, string>}
     */
    public function compile(ReviewProfile $profile): array
    {
        $criteria = [];
        $unsupportedChecks = [];

        foreach ($profile->criteria as $index => $criterion) {
            $compiled = $this->compileCriterion($criterion, $index);
            $criteria[] = $compiled['criterion'];
            $unsupportedChecks = [...$unsupportedChecks, ...$compiled['unsupported_checks']];
        }

        return [
            'criteria' => $criteria,
            'unsupported_checks' => array_values(array_unique(array_filter($unsupportedChecks))),
        ];
    }

    /**
     * @return array{criterion: array<string, mixed>, unsupported_checks: array<int, string>}
     */
    private function compileCriterion(StructuredCriterion $criterion, int $index): array
    {
        $unsupportedChecks = [];

        $texts = [
            $criterion->label,
            $criterion->description,
            $criterion->instructions ?? '',
            ...$criterion->checks,
        ];

        foreach ($texts as $text) {
            $normalized = mb_strtolower($text, 'UTF-8');
            foreach (self::UNSUPPORTED_KEYWORDS as $keyword) {
                if (str_contains($normalized, mb_strtolower($keyword, 'UTF-8'))) {
                    $unsupportedChecks[] = trim($text) !== '' ? trim($text) : $criterion->label;
                    break;
                }
            }
        }

        return [
            'criterion' => [
                'id' => $criterion->id !== '' ? $criterion->id : "criterion_{$index}",
                'label' => $criterion->label,
                'description' => $criterion->description,
                'instructions' => $criterion->instructions,
                'checks' => $criterion->checks,
                'weight' => $criterion->weight,
            ],
            'unsupported_checks' => $unsupportedChecks,
        ];
    }
}
