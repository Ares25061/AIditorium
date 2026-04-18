<?php

namespace Tests\Unit;

use App\AI\DTO\ReviewProfile;
use App\AI\DTO\StructuredCriterion;
use App\AI\Services\CriteriaCompiler;
use PHPUnit\Framework\TestCase;

class CriteriaCompilerTest extends TestCase
{
    public function test_compiler_preserves_utf8_and_leaves_compile_checks_for_server_execution(): void
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
        $this->assertContains('Проверить наличие комментариев', $result['criteria'][0]['checks']);
        $this->assertSame([], $result['unsupported_checks']);
    }

    public function test_compiler_marks_runtime_checks_as_unsupported(): void
    {
        $profile = new ReviewProfile(
            enabled: true,
            criteria: [
                StructuredCriterion::fromArray([
                    'id' => 'criterion_runtime',
                    'label' => 'Проверка запуска',
                    'description' => 'Запусти проект и прогоняй тесты',
                    'checks' => [
                        'Запустить приложение',
                        'Прогнать pytest',
                    ],
                ], 0),
            ],
            customPrompt: null,
            supportedFormats: ['py'],
            version: 1,
        );

        $result = (new CriteriaCompiler())->compile($profile);

        $this->assertContains('Запусти проект и прогоняй тесты', $result['unsupported_checks']);
        $this->assertContains('Запустить приложение', $result['unsupported_checks']);
    }
}
