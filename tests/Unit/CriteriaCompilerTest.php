<?php

namespace Tests\Unit;

use App\AI\DTO\ReviewProfile;
use App\AI\DTO\StructuredCriterion;
use App\AI\Services\CriteriaCompiler;
use PHPUnit\Framework\TestCase;

class CriteriaCompilerTest extends TestCase
{
    public function test_compiler_preserves_utf8_and_marks_compile_checks_as_unsupported(): void
    {
        $profile = new ReviewProfile(
            enabled: true,
            criteria: [
                StructuredCriterion::fromArray([
                    'id' => 'criterion_code',
                    'label' => 'Проверка структуры кода',
                    'description' => 'Оцени читаемость и корректность решения',
                    'checks' => [
                        'Проверить компиляцию проекта',
                        'Проверить наличие комментариев',
                    ],
                ], 0),
            ],
            customPrompt: 'Ответ должен быть на русском языке',
            supportedFormats: ['py', 'zip'],
            version: 1,
        );

        $result = (new CriteriaCompiler())->compile($profile);

        $this->assertSame('Проверка структуры кода', $result['criteria'][0]['label']);
        $this->assertContains('Проверить компиляцию проекта', $result['unsupported_checks']);
        $this->assertContains('Проверить наличие комментариев', $result['criteria'][0]['checks']);
    }
}
