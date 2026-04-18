<?php

namespace App\AI\Services;

use App\AI\DTO\CriterionResult;
use App\AI\DTO\ReviewResult;

class ReviewResultAssembler
{
    /**
     * @param array<int, array<string, mixed>> $criteria
     * @param array<int, CriterionResult> $serverResults
     * @param array<int, string> $unsupportedChecks
     */
    public function assemble(array $criteria, array $serverResults, ?ReviewResult $llmResult, array $unsupportedChecks): ReviewResult
    {
        $criteriaById = [];
        foreach ($criteria as $criterion) {
            $criteriaById[(string) ($criterion['id'] ?? '')] = $criterion;
        }

        $serverMap = [];
        foreach ($serverResults as $result) {
            $serverMap[$result->criterionId] = $result;
        }

        $llmMap = [];
        foreach ($llmResult?->criteriaResults ?? [] as $result) {
            if (isset($serverMap[$result->criterionId])) {
                continue;
            }

            $llmMap[$result->criterionId] = $result;
        }

        $mergedResults = [];
        foreach ($criteria as $criterion) {
            $criterionId = (string) ($criterion['id'] ?? '');
            $result = $serverMap[$criterionId] ?? $llmMap[$criterionId] ?? null;
            if (!$result instanceof CriterionResult) {
                continue;
            }

            $mergedResults[] = $this->decorateResult(
                $result,
                $criterion,
                isset($serverMap[$criterionId]) ? 'server' : 'model',
            );
        }

        $allUnsupportedChecks = array_values(array_unique([
            ...$unsupportedChecks,
            ...$this->collectUnsupportedCriterionLabels($mergedResults),
            ...($llmResult?->unsupportedChecks ?? []),
        ]));

        $finalScore = $this->calculateRecommendedScore($mergedResults);
        $summary = $this->buildSummary($mergedResults, $finalScore, $allUnsupportedChecks);
        $confidence = $llmResult?->confidence ?? ($mergedResults === [] ? 0.0 : 1.0);
        $raw = array_merge($llmResult?->raw ?? [], [
            'model_summary' => $llmResult?->summary,
            'model_recommended_score' => $llmResult?->recommendedScore,
            'server_criteria_results' => array_map(static fn (CriterionResult $result) => $result->toArray(), $serverResults),
            'final_recommended_score' => $finalScore,
        ]);

        return new ReviewResult(
            summary: $summary,
            recommendedScore: $finalScore,
            confidence: max(0, min(1, round((float) $confidence, 4))),
            criteriaResults: $mergedResults,
            unsupportedChecks: $allUnsupportedChecks,
            raw: $raw,
        );
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function decorateResult(CriterionResult $result, array $criterion, string $defaultSource): CriterionResult
    {
        $status = $result->status;
        if ($status === null || $status === '') {
            $status = $result->passed ? 'passed' : 'failed';
        }

        return new CriterionResult(
            criterionId: $result->criterionId,
            label: $result->label,
            passed: $result->passed,
            score: $result->score,
            evidence: $result->evidence,
            feedback: $result->feedback,
            status: $status,
            source: $result->source !== '' ? $result->source : $defaultSource,
            weight: isset($criterion['weight']) && is_numeric($criterion['weight']) ? (int) $criterion['weight'] : $result->weight,
        );
    }

    /**
     * @param array<int, CriterionResult> $results
     * @return array<int, string>
     */
    private function collectUnsupportedCriterionLabels(array $results): array
    {
        $labels = [];
        foreach ($results as $result) {
            if ($result->status === 'unsupported') {
                $labels[] = $result->label;
            }
        }

        return $labels;
    }

    /**
     * @param array<int, CriterionResult> $results
     */
    private function calculateRecommendedScore(array $results): int
    {
        $weightedTotal = 0.0;
        $weightSum = 0;
        $unweightedPercentages = [];

        foreach ($results as $result) {
            if ($result->status === 'unsupported') {
                continue;
            }

            $percent = $this->resolveCriterionPercent($result);
            $weight = $result->weight !== null && $result->weight > 0 ? $result->weight : null;

            if ($weight === null) {
                $unweightedPercentages[] = $percent;
                continue;
            }

            $weightedTotal += $percent * $weight;
            $weightSum += $weight;
        }

        if ($weightSum > 0) {
            return (int) round($weightedTotal / $weightSum);
        }

        if ($unweightedPercentages !== []) {
            return (int) round(array_sum($unweightedPercentages) / count($unweightedPercentages));
        }

        return 0;
    }

    private function resolveCriterionPercent(CriterionResult $result): int
    {
        if ($result->score === null) {
            return $result->passed ? 100 : 0;
        }

        $score = max(0, min(100, $result->score));
        $weight = $result->weight;

        if ($weight !== null && $weight > 0 && $weight < 100 && $score <= $weight) {
            return (int) round(($score / $weight) * 100);
        }

        return $score;
    }

    /**
     * @param array<int, CriterionResult> $results
     * @param array<int, string> $unsupportedChecks
     */
    private function buildSummary(array $results, int $finalScore, array $unsupportedChecks): string
    {
        $passed = [];
        $failed = [];
        $unsupported = [];

        foreach ($results as $result) {
            match ($result->status) {
                'unsupported' => $unsupported[] = $result->label,
                'passed' => $passed[] = $result->label,
                default => $failed[] = $result->label,
            };
        }

        $parts = ["Проверка завершена. Итоговая оценка: {$finalScore}/100."];

        if ($passed !== []) {
            $parts[] = 'Пройдены критерии: '.implode(', ', $passed).'.';
        }

        if ($failed !== []) {
            $parts[] = 'Не пройдены критерии: '.implode(', ', $failed).'.';
        }

        if ($unsupported !== [] || $unsupportedChecks !== []) {
            $parts[] = 'Неподдерживаемые проверки не влияли на итоговую оценку: '.implode(', ', array_values(array_unique([
                ...$unsupported,
                ...$unsupportedChecks,
            ]))).'.';
        }

        return implode(' ', $parts);
    }
}
