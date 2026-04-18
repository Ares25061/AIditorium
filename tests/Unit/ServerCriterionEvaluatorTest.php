<?php

namespace Tests\Unit;

use App\AI\Services\ServerCriterionEvaluator;
use App\Models\File;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Tests\TestCase;

class ServerCriterionEvaluatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config()->set('ai.execution.php_binary', '');
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

    public function test_php_version_candidates_are_derived_from_php_fpm_binary_name(): void
    {
        $evaluator = app(ServerCriterionEvaluator::class);
        $reflection = new ReflectionClass($evaluator);
        $method = $reflection->getMethod('phpVersionCandidates');
        $method->setAccessible(true);

        $candidates = $method->invoke($evaluator, '/usr/sbin/php-fpm8.4');

        $this->assertSame(['php8.4', 'php84'], $candidates);
    }

    public function test_resolves_method_count_and_crud_server_side_for_php_controller(): void
    {
        Storage::disk('public')->put('fixtures/controller.php', <<<'PHP'
<?php

class DemoController
{
    public function index() {}
    public function store() {}
    public function show() {}
    public function update() {}
    public function destroy() {}
    public function helperOne() {}
    public function helperTwo() {}
}
PHP);

        $file = new File([
            'path' => 'fixtures/controller.php',
            'original_name' => 'controller.php',
            'extension' => 'php',
        ]);

        $criteria = [
            [
                'id' => 'method_count',
                'label' => 'Количество методов больше 6',
                'description' => 'Определи количество методов или функций в файле. Критерий проходит, если их больше шести.',
                'checks' => ['functions > 6'],
                'weight' => 30,
            ],
            [
                'id' => 'crud',
                'label' => 'Наличие CRUD методов',
                'description' => 'Проверь наличие базовых CRUD методов контроллера: index, store, show, update, destroy.',
                'checks' => ['crud methods', 'index store show update destroy'],
                'weight' => 30,
            ],
            [
                'id' => 'logic',
                'label' => 'Логика решения',
                'description' => 'Проверь основную логику.',
                'checks' => ['Проанализируй код'],
                'weight' => 40,
            ],
        ];

        $result = app(ServerCriterionEvaluator::class)->evaluate($file, $criteria);
        $resultsById = [];
        foreach ($result['criterion_results'] as $criterionResult) {
            $resultsById[$criterionResult->criterionId] = $criterionResult;
        }

        $this->assertCount(2, $result['criterion_results']);
        $this->assertCount(1, $result['llm_criteria']);
        $this->assertSame('logic', $result['llm_criteria'][0]['id']);
        $this->assertSame('passed', $resultsById['method_count']->status);
        $this->assertSame('server', $resultsById['method_count']->source);
        $this->assertStringContainsString('function_like_count=7', $resultsById['method_count']->evidence[0]);
        $this->assertSame('passed', $resultsById['crud']->status);
        $this->assertSame('server', $resultsById['crud']->source);
        $this->assertStringContainsString('index, store, show, update, destroy', $resultsById['crud']->evidence[0]);
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
