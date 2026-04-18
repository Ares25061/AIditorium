<?php

namespace App\AI\DTO;

readonly class CriterionResult
{
    /**
     * @param array<int, string> $evidence
     */
    public function __construct(
        public string $criterionId,
        public string $label,
        public bool $passed,
        public ?int $score,
        public array $evidence,
        public string $feedback,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            criterionId: trim((string) ($data['criterion_id'] ?? 'unknown')),
            label: trim((string) ($data['label'] ?? 'Unknown')),
            passed: (bool) ($data['passed'] ?? false),
            score: isset($data['score']) && is_numeric($data['score']) ? max(0, min(100, (int) round((float) $data['score']))) : null,
            evidence: array_values(array_map(static fn ($item) => (string) $item, $data['evidence'] ?? [])),
            feedback: trim((string) ($data['feedback'] ?? '')),
        );
    }

    public function toArray(): array
    {
        return [
            'criterion_id' => $this->criterionId,
            'label' => $this->label,
            'passed' => $this->passed,
            'score' => $this->score,
            'evidence' => $this->evidence,
            'feedback' => $this->feedback,
        ];
    }
}
