<?php

namespace App\AI\Services;

use App\AI\DTO\CriterionResult;
use App\AI\DTO\ReviewResult;
use InvalidArgumentException;

class AIResultSchemaValidator
{
    public function validate(array $payload): ReviewResult
    {
        if (!isset($payload['summary']) || !is_string($payload['summary']) || trim($payload['summary']) === '') {
            throw new InvalidArgumentException('AI response must contain a non-empty summary.');
        }

        if (!array_key_exists('recommended_score', $payload) || !is_numeric($payload['recommended_score'])) {
            throw new InvalidArgumentException('AI response must contain recommended_score.');
        }

        if (!array_key_exists('confidence', $payload) || !is_numeric($payload['confidence'])) {
            throw new InvalidArgumentException('AI response must contain confidence.');
        }

        if (!isset($payload['criteria_results']) || !is_array($payload['criteria_results'])) {
            throw new InvalidArgumentException('AI response must contain criteria_results array.');
        }

        if (!isset($payload['unsupported_checks']) || !is_array($payload['unsupported_checks'])) {
            throw new InvalidArgumentException('AI response must contain unsupported_checks array.');
        }

        $criteriaResults = array_map(
            static fn (array $item) => CriterionResult::fromArray($item),
            array_values(array_filter($payload['criteria_results'], 'is_array')),
        );

        $confidence = max(0, min(1, round((float) $payload['confidence'], 4)));
        $recommendedScore = max(0, min(100, (int) round((float) $payload['recommended_score'])));
        $unsupportedChecks = array_values(array_map(static fn ($item) => (string) $item, $payload['unsupported_checks']));

        return new ReviewResult(
            summary: trim($payload['summary']),
            recommendedScore: $recommendedScore,
            confidence: $confidence,
            criteriaResults: $criteriaResults,
            unsupportedChecks: $unsupportedChecks,
            raw: $payload,
        );
    }
}
