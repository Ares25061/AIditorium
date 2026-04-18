<?php

namespace App\AI\DTO;

readonly class ReviewResult
{
    /**
     * @param array<int, CriterionResult> $criteriaResults
     * @param array<int, string> $unsupportedChecks
     */
    public function __construct(
        public string $summary,
        public int $recommendedScore,
        public float $confidence,
        public array $criteriaResults,
        public array $unsupportedChecks,
        public array $raw,
    ) {
    }

    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'recommended_score' => $this->recommendedScore,
            'confidence' => $this->confidence,
            'criteria_results' => array_map(static fn (CriterionResult $result) => $result->toArray(), $this->criteriaResults),
            'unsupported_checks' => $this->unsupportedChecks,
            'raw' => $this->raw,
        ];
    }
}
