<?php

namespace Tests\Unit;

use App\AI\Services\ServerCriterionEvaluator;
use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServerCriterionEvaluatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_runs_php_compile_check_on_server_and_keeps_other_criteria_for_llm(): void
    {
        Storage::disk('public')->put('fixtures/solution.php', "<?php\n\nfunction testValue(): int\n{\n    return 1;\n}\n");

        $file = new File([
            'path' => 'fixtures/solution.php',
            'original_name' => 'solution.php',
            'extension' => 'php',
        ]);

        $criteria = [
            [
                'id' => 'compile_file',
                'label' => 'Компиляция файла',
                'description' => 'Проверь, компилируется ли файл.',
                'checks' => ['Скомпилировать файл'],
                'weight' => 40,
            ],
            [
                'id' => 'logic',
                'label' => 'Логика решения',
                'description' => 'Проверь основную логику.',
                'checks' => ['Проанализируй код'],
                'weight' => 60,
            ],
        ];

        $result = app(ServerCriterionEvaluator::class)->evaluate($file, $criteria);

        $this->assertCount(1, $result['criterion_results']);
        $this->assertCount(1, $result['llm_criteria']);
        $this->assertSame('compile_file', $result['criterion_results'][0]->criterionId);
        $this->assertSame('passed', $result['criterion_results'][0]->status);
        $this->assertSame('server', $result['criterion_results'][0]->source);
        $this->assertSame('logic', $result['llm_criteria'][0]['id']);
    }

    public function test_validates_html_markup_without_external_runtime(): void
    {
        Storage::disk('public')->put('fixtures/page.html', "<!DOCTYPE html>\n<html><body><main><h1>Привет</h1></main></body></html>\n");

        $file = new File([
            'path' => 'fixtures/page.html',
            'original_name' => 'page.html',
            'extension' => 'html',
        ]);

        $result = app(ServerCriterionEvaluator::class)->evaluate($file, [$this->compileCriterion()]);

        $this->assertCount(1, $result['criterion_results']);
        $this->assertSame('passed', $result['criterion_results'][0]->status);
        $this->assertSame('server', $result['criterion_results'][0]->source);
    }

    public function test_fails_invalid_css_structure(): void
    {
        Storage::disk('public')->put('fixtures/styles.css', ".card { color: red; \n.button { display: flex; }\n");

        $file = new File([
            'path' => 'fixtures/styles.css',
            'original_name' => 'styles.css',
            'extension' => 'css',
        ]);

        $result = app(ServerCriterionEvaluator::class)->evaluate($file, [$this->compileCriterion()]);

        $this->assertCount(1, $result['criterion_results']);
        $this->assertSame('failed', $result['criterion_results'][0]->status);
        $this->assertStringContainsString('CSS', $result['criterion_results'][0]->feedback);
    }

    public function test_cpp_and_csharp_checks_do_not_crash_when_runtime_is_missing(): void
    {
        Storage::disk('public')->put('fixtures/sample.cpp', "#include <iostream>\nint main() { return 0; }\n");
        Storage::disk('public')->put('fixtures/sample.cs', "public class Sample { public int Value() => 1; }\n");

        $cppFile = new File([
            'path' => 'fixtures/sample.cpp',
            'original_name' => 'sample.cpp',
            'extension' => 'cpp',
        ]);
        $csFile = new File([
            'path' => 'fixtures/sample.cs',
            'original_name' => 'sample.cs',
            'extension' => 'cs',
        ]);

        $cppResult = app(ServerCriterionEvaluator::class)->evaluate($cppFile, [$this->compileCriterion()]);
        $csResult = app(ServerCriterionEvaluator::class)->evaluate($csFile, [$this->compileCriterion()]);

        $this->assertContains($cppResult['criterion_results'][0]->status, ['passed', 'unsupported', 'failed']);
        $this->assertContains($csResult['criterion_results'][0]->status, ['passed', 'unsupported', 'failed']);
        $this->assertSame('server', $cppResult['criterion_results'][0]->source);
        $this->assertSame('server', $csResult['criterion_results'][0]->source);
    }

    /**
     * @return array<string, mixed>
     */
    private function compileCriterion(): array
    {
        return [
            'id' => 'compile_file',
            'label' => 'Компиляция файла',
            'description' => 'Проверь, компилируется ли файл.',
            'checks' => ['Скомпилировать файл'],
            'weight' => 40,
        ];
    }
}
