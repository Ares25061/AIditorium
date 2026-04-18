<?php

namespace App\AI\DTO;

readonly class StructuredCriterion
{
    public function __construct(
        public string $id,
        public string $label,
        public string $description,
        public array $checks = [],
        public ?string $instructions = null,
        public ?int $weight = null,
    ) {
    }

    public static function fromArray(array $data, int $index): self
    {
        $label = trim((string) ($data['label'] ?? $data['name'] ?? $data['title'] ?? "criterion_{$index}"));
        $description = trim((string) ($data['description'] ?? $data['prompt'] ?? $data['details'] ?? ''));
        $checks = array_values(array_map(static fn ($item) => is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE), $data['checks'] ?? []));
        $instructions = isset($data['instructions']) ? trim((string) $data['instructions']) : null;
        $weight = isset($data['weight']) && is_numeric($data['weight']) ? (int) $data['weight'] : null;

        return new self(
            id: trim((string) ($data['id'] ?? "criterion_{$index}")),
            label: $label,
            description: $description,
            checks: $checks,
            instructions: $instructions !== '' ? $instructions : null,
            weight: $weight,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'description' => $this->description,
            'checks' => $this->checks,
            'instructions' => $this->instructions,
            'weight' => $this->weight,
        ];
    }
}
