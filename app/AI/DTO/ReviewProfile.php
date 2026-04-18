<?php

namespace App\AI\DTO;

readonly class ReviewProfile
{
    /**
     * @param array<int, StructuredCriterion> $criteria
     * @param array<int, string> $supportedFormats
     */
    public function __construct(
        public bool $enabled,
        public array $criteria,
        public ?string $customPrompt,
        public array $supportedFormats,
        public int $version,
    ) {
    }

    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'rubric' => array_map(static fn (StructuredCriterion $criterion) => $criterion->toArray(), $this->criteria),
            'custom_prompt' => $this->customPrompt,
            'supported_formats' => $this->supportedFormats,
            'version' => $this->version,
        ];
    }
}
