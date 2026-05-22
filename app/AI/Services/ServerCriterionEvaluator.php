<?php

namespace App\AI\Services;

use App\AI\DTO\CriterionResult;
use App\Models\File;
use Illuminate\Support\Facades\File as LocalFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ServerCriterionEvaluator
{
    private const STRUCTURAL_METHOD_KEYWORDS = [
        'количество методов',
        'кол-во методов',
        'количество функций',
        'кол-во функций',
        'method count',
        'methods >',
        'methods <',
        'methods >=',
        'methods <=',
        'functions >',
        'functions <',
        'functions >=',
        'functions <=',
    ];

    private const COMPILE_KEYWORDS = [
        'compile',
        'compilation',
        'build',
        'syntax',
        'lint',
        'компил',
        'собер',
        'синтакс',
        'линт',
    ];

    private const CONTROLLER_IDENTITY_PATTERNS = [
        '/(?:^|\s)(?:файл|класс)\s*(?:-|:)?\s*(?:является\s+)?(?:laravel[-\s]*)?контроллер(?:ом)?\b/u',
        '/(?:^|\s)(?:file|class)\s*(?:-|:)?\s*(?:is\s+)?(?:a\s+)?(?:laravel\s+)?controller\b/u',
        '/\b(?:является|быть|is|be|extends)\s+(?:a\s+)?(?:laravel\s+)?controller\b/u',
        '/\bclass\s+extends\s+controller\b/u',
    ];

    private const REQUIRED_CRUD_METHODS = [
        'index',
        'store',
        'show',
        'update',
        'destroy',
    ];

    /**
     * @param array<int, array<string, mixed>> $criteria
     * @return array{
     *     criterion_results: array<int, CriterionResult>,
     *     llm_criteria: array<int, array<string, mixed>>,
     *     unsupported_checks: array<int, string>
     * }
     */
    public function evaluate(File $file, array $criteria): array
    {
        $criterionResults = [];
        $llmCriteria = [];
        $unsupportedChecks = [];

        foreach ($criteria as $criterion) {
            $result = $this->evaluateServerCriterion($file, $criterion);
            if (!$result instanceof CriterionResult) {
                $llmCriteria[] = $criterion;
                continue;
            }

            $criterionResults[] = $result;

            if ($result->status === 'unsupported') {
                $unsupportedChecks[] = $result->label;
            }
        }

        return [
            'criterion_results' => $criterionResults,
            'llm_criteria' => $llmCriteria,
            'unsupported_checks' => array_values(array_unique($unsupportedChecks)),
        ];
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function evaluateServerCriterion(File $file, array $criterion): ?CriterionResult
    {
        if ($this->isCompilationCriterion($criterion)) {
            return $this->evaluateCompilationCriterion($file, $criterion);
        }

        if ($this->isMethodCountCriterion($criterion)) {
            return $this->evaluateMethodCountCriterion($file, $criterion);
        }

        if ($this->isCrudCriterion($criterion)) {
            return $this->evaluateCrudCriterion($file, $criterion);
        }

        if ($this->isControllerCriterion($criterion)) {
            return $this->evaluateControllerCriterion($file, $criterion);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function isCompilationCriterion(array $criterion): bool
    {
        $texts = [
            $criterion['label'] ?? '',
            $criterion['description'] ?? '',
            $criterion['instructions'] ?? '',
            ...($criterion['checks'] ?? []),
        ];

        foreach ($texts as $text) {
            $normalized = mb_strtolower((string) $text, 'UTF-8');
            foreach (self::COMPILE_KEYWORDS as $keyword) {
                if (str_contains($normalized, mb_strtolower($keyword, 'UTF-8'))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function isMethodCountCriterion(array $criterion): bool
    {
        $expectation = $this->resolveMethodCountExpectation($criterion);
        if ($expectation === null) {
            return false;
        }

        $text = $this->normalizeCriterionText($criterion);
        if (preg_match('/метод|функц|method|function/u', $text) === 1) {
            return true;
        }

        foreach (self::STRUCTURAL_METHOD_KEYWORDS as $keyword) {
            if (str_contains($text, mb_strtolower($keyword, 'UTF-8'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function isCrudCriterion(array $criterion): bool
    {
        $text = $this->normalizeCriterionText($criterion);
        if (str_contains($text, 'crud')) {
            return true;
        }

        $mentionedMethods = 0;
        foreach (self::REQUIRED_CRUD_METHODS as $method) {
            if (preg_match('/\b'.preg_quote($method, '/').'\b/u', $text) === 1) {
                $mentionedMethods++;
            }
        }

        return $mentionedMethods >= 3 && preg_match('/метод|method|контроллер|controller/u', $text) === 1;
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function isControllerCriterion(array $criterion): bool
    {
        $text = $this->normalizeCriterionText($criterion);

        foreach (self::CONTROLLER_IDENTITY_PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function evaluateCompilationCriterion(File $file, array $criterion): CriterionResult
    {
        if (!config('ai.execution.enabled', true)) {
            return $this->unsupportedResult($criterion, 'Серверное выполнение проверок отключено.');
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($file->path)) {
            throw new RuntimeException('Submission file is missing on disk.');
        }
        $absolutePath = $disk->path($file->path);

        $extension = strtolower((string) ($file->extension ?: pathinfo($absolutePath, PATHINFO_EXTENSION)));

        return match ($extension) {
            'php' => $this->runPhpCompilation($criterion, $absolutePath),
            'py' => $this->runPythonCompilation($criterion, $absolutePath),
            'js', 'mjs', 'cjs' => $this->runNodeSyntaxCheck($criterion, $absolutePath),
            'cpp', 'cc', 'cxx', 'hpp', 'hh', 'hxx', 'h' => $this->runCppSyntaxCheck($criterion, $absolutePath, $extension),
            'cs' => $this->runCSharpCompilation($criterion, $absolutePath),
            'html', 'htm' => $this->validateHtmlMarkup($criterion, $absolutePath),
            'css' => $this->validateCssSyntax($criterion, $absolutePath),
            default => $this->unsupportedResult(
                $criterion,
                "Проверка компиляции для .{$extension} пока не поддерживается серверным evaluator.",
            ),
        };
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function evaluateMethodCountCriterion(File $file, array $criterion): CriterionResult
    {
        $expectation = $this->resolveMethodCountExpectation($criterion);
        if ($expectation === null) {
            return $this->unsupportedResult($criterion, 'Не удалось определить ожидаемое количество методов из критерия.');
        }

        $context = $this->loadStructuralFileContext($file);
        if (is_string($context)) {
            return $this->unsupportedResult($criterion, $context);
        }

        $callableNames = $this->extractCallableNames($context['content'], $context['extension']);
        $count = count($callableNames);
        $passed = $this->compareNumericExpectation($count, $expectation['operator'], $expectation['threshold']);
        $evidence = [
            "function_like_count={$count}; condition {$expectation['operator']} {$expectation['threshold']}.",
        ];

        if ($passed) {
            return $this->passedResult(
                $criterion,
                $evidence,
                "Найдено {$count} методов/функций; условие {$expectation['operator']} {$expectation['threshold']} выполнено.",
            );
        }

        return $this->failedResult(
            $criterion,
            $evidence,
            "Найдено {$count} методов/функций; условие {$expectation['operator']} {$expectation['threshold']} не выполнено.",
        );
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function evaluateCrudCriterion(File $file, array $criterion): CriterionResult
    {
        $context = $this->loadStructuralFileContext($file);
        if (is_string($context)) {
            return $this->unsupportedResult($criterion, $context);
        }

        $callableNames = $this->extractCallableNames($context['content'], $context['extension']);
        $callableMap = array_fill_keys($callableNames, true);
        $found = [];
        $missing = [];

        foreach (self::REQUIRED_CRUD_METHODS as $method) {
            if (isset($callableMap[$method])) {
                $found[] = $method;
            } else {
                $missing[] = $method;
            }
        }

        $evidence = [];
        if ($found !== []) {
            $evidence[] = 'Найдены методы: '.implode(', ', $found).'.';
        }
        if ($missing !== []) {
            $evidence[] = 'Отсутствуют методы: '.implode(', ', $missing).'.';
        }

        if ($missing === []) {
            return $this->passedResult(
                $criterion,
                $evidence !== [] ? $evidence : ['Все базовые CRUD-методы найдены.'],
                'Все базовые CRUD-методы присутствуют.',
            );
        }

        return $this->failedResult(
            $criterion,
            $evidence !== [] ? $evidence : ['Не удалось подтвердить наличие базовых CRUD-методов.'],
            'Не все базовые CRUD-методы найдены в файле.',
        );
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function evaluateControllerCriterion(File $file, array $criterion): CriterionResult
    {
        $context = $this->loadStructuralFileContext($file);
        if (is_string($context)) {
            return $this->unsupportedResult($criterion, $context);
        }

        $filename = (string) ($file->original_name ?: basename($context['path']));
        $className = $this->extractFirstClassName($context['content']);
        $parentClass = $this->extractParentClassName($context['content']);
        $namespace = $context['extension'] === 'php'
            ? $this->extractPhpNamespace($context['content'])
            : null;

        $classLooksController = $className !== null && preg_match('/Controller$/i', $className) === 1;
        $parentLooksController = $parentClass !== null && preg_match('/(?:^|\\\\)Controller$/i', $parentClass) === 1;
        $namespaceLooksController = $namespace !== null && str_starts_with($namespace, 'App\\Http\\Controllers');
        $filenameLooksController = preg_match('/Controller\.[A-Za-z0-9]+$/i', $filename) === 1;

        $evidence = [];
        if ($className !== null) {
            $evidence[] = "class={$className}.";
        }
        if ($parentClass !== null) {
            $evidence[] = "extends={$parentClass}.";
        }
        if ($namespace !== null) {
            $evidence[] = "namespace={$namespace}.";
        }
        $evidence[] = "filename={$filename}.";

        if ($classLooksController && ($parentLooksController || $namespaceLooksController || $filenameLooksController)) {
            return $this->passedResult(
                $criterion,
                $evidence,
                'Файл распознан как контроллер серверной структурной проверкой.',
            );
        }

        return $this->failedResult(
            $criterion,
            $evidence !== [] ? $evidence : ['Не удалось найти признаки контроллера в файле.'],
            'Файл не удалось подтвердить как контроллер серверной структурной проверкой.',
        );
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function runPhpCompilation(array $criterion, string $absolutePath): CriterionResult
    {
        $commands = $this->resolvePhpLintCommands($absolutePath);
        if ($commands === []) {
            return $this->unsupportedResult($criterion, 'PHP CLI runtime не найден на сервере.');
        }

        $lastFailure = null;
        foreach ($commands as $command) {
            $result = $this->runProcessCriterion($criterion, $command, 'PHP lint passed without syntax errors.');
            if ($result->status === 'passed') {
                return $result;
            }

            $lastFailure = $result;
        }

        return $lastFailure ?? $this->unsupportedResult($criterion, 'PHP CLI runtime не найден на сервере.');
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function resolvePhpLintCommands(string $absolutePath): array
    {
        $commands = [];

        foreach ($this->resolvePhpExecutables() as $binary) {
            $commands[] = [$binary, '-l', $absolutePath];
        }

        return $commands;
    }

    /**
     * @return array<int, string>
     */
    private function resolvePhpExecutables(): array
    {
        $executables = [];
        $configuredBinary = trim((string) config('ai.execution.php_binary', ''));

        if ($configuredBinary !== '' && $this->isCliPhpBinary($configuredBinary)) {
            $this->appendUniqueExecutable($executables, $configuredBinary);
        }

        if ($this->isCliPhpBinary(PHP_BINARY)) {
            $this->appendUniqueExecutable($executables, PHP_BINARY);
        }

        $candidateNames = array_values(array_unique([
            'php',
            'php-cli',
            ...$this->phpVersionCandidates($configuredBinary),
            ...$this->phpVersionCandidates(PHP_BINARY),
        ]));

        foreach ($candidateNames as $candidate) {
            $resolved = $this->findExecutable([$candidate]);
            if ($resolved && $this->isCliPhpBinary($resolved)) {
                $this->appendUniqueExecutable($executables, $resolved);
            }
        }

        return $executables;
    }

    /**
     * @return array<int, string>
     */
    private function phpVersionCandidates(string $binary): array
    {
        if ($binary === '') {
            return [];
        }

        $basename = strtolower(basename(str_replace('\\', '/', $binary)));
        if (preg_match('/php(?:-fpm)?[^\d]*(\d+(?:\.\d+)?)/', $basename, $matches) !== 1) {
            return [];
        }

        $version = $matches[1];
        $normalized = str_replace('.', '', $version);

        return array_values(array_unique([
            'php'.$version,
            'php'.$normalized,
        ]));
    }

    private function isCliPhpBinary(string $binary): bool
    {
        $basename = strtolower(basename(str_replace('\\', '/', trim($binary))));
        if ($basename === '') {
            return false;
        }

        if (!str_contains($basename, 'php')) {
            return false;
        }

        return !str_contains($basename, 'fpm');
    }

    /**
     * @param array<int, string> $executables
     */
    private function appendUniqueExecutable(array &$executables, string $binary): void
    {
        if (!in_array($binary, $executables, true)) {
            $executables[] = $binary;
        }
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function runPythonCompilation(array $criterion, string $absolutePath): CriterionResult
    {
        $python = (new ExecutableFinder())->find('python') ?? (new ExecutableFinder())->find('py');
        if (!$python) {
            return $this->unsupportedResult($criterion, 'Python runtime не найден на сервере.');
        }

        $command = str_ends_with(strtolower($python), 'py.exe')
            ? [$python, '-3', '-m', 'py_compile', $absolutePath]
            : [$python, '-m', 'py_compile', $absolutePath];

        return $this->runProcessCriterion($criterion, $command, 'Python compilation check passed.');
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function runNodeSyntaxCheck(array $criterion, string $absolutePath): CriterionResult
    {
        $node = (new ExecutableFinder())->find('node');
        if (!$node) {
            return $this->unsupportedResult($criterion, 'Node.js runtime не найден на сервере.');
        }

        return $this->runProcessCriterion($criterion, [$node, '--check', $absolutePath], 'Node.js syntax check passed.');
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function runCppSyntaxCheck(array $criterion, string $absolutePath, string $extension): CriterionResult
    {
        $compiler = $this->findExecutable(['g++', 'clang++', 'c++']);
        if ($compiler) {
            $command = [$compiler, '-std=c++17', '-fsyntax-only'];
            if (in_array($extension, ['h', 'hpp', 'hh', 'hxx'], true)) {
                $command[] = '-x';
                $command[] = 'c++';
            }
            $command[] = $absolutePath;

            return $this->runProcessCriterion($criterion, $command, 'C++ syntax check passed.');
        }

        $cl = $this->findExecutable(['cl']);
        if ($cl) {
            $command = [$cl, '/nologo', '/Zs'];
            if (in_array($extension, ['h', 'hpp', 'hh', 'hxx'], true)) {
                $command[] = '/TP';
            }
            $command[] = $absolutePath;

            return $this->runProcessCriterion($criterion, $command, 'C++ syntax check passed.');
        }

        return $this->unsupportedResult($criterion, 'C++ compiler не найден на сервере.');
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function runCSharpCompilation(array $criterion, string $absolutePath): CriterionResult
    {
        $outputType = $this->guessCSharpOutputType($absolutePath);
        $tempDirectory = storage_path('app/private/ai-review-compile/'.Str::uuid());
        LocalFile::ensureDirectoryExists($tempDirectory);
        $outputPath = $tempDirectory.'/submission.'.($outputType === 'exe' ? 'exe' : 'dll');

        try {
            $csc = $this->findExecutable(['csc']);
            if ($csc) {
                return $this->runProcessCriterion(
                    $criterion,
                    [$csc, '/nologo', '/target:'.$outputType, '/out:'.$outputPath, $absolutePath],
                    'C# compilation check passed.',
                );
            }

            $mcs = $this->findExecutable(['mcs']);
            if ($mcs) {
                return $this->runProcessCriterion(
                    $criterion,
                    [$mcs, '-target:'.$outputType, '-out:'.$outputPath, $absolutePath],
                    'C# compilation check passed.',
                );
            }

            $dotnet = $this->findExecutable(['dotnet']);
            if ($dotnet) {
                return $this->runDotnetCSharpCompilation($criterion, $absolutePath, $outputType, $dotnet, $tempDirectory);
            }

            return $this->unsupportedResult($criterion, 'C# compiler/runtime не найден на сервере.');
        } finally {
            LocalFile::deleteDirectory($tempDirectory);
        }
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function runDotnetCSharpCompilation(
        array $criterion,
        string $absolutePath,
        string $outputType,
        string $dotnet,
        string $tempDirectory,
    ): CriterionResult {
        $sourceTarget = $tempDirectory.'/Submission.cs';
        $projectPath = $tempDirectory.'/Submission.csproj';

        LocalFile::copy($absolutePath, $sourceTarget);
        LocalFile::put($projectPath, $this->buildTemporaryCSharpProject($outputType, $this->resolveDotnetTargetFramework($dotnet)));

        return $this->runProcessCriterion(
            $criterion,
            [$dotnet, 'build', $projectPath, '--nologo', '--verbosity', 'quiet'],
            'C# compilation check passed.',
        );
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function validateHtmlMarkup(array $criterion, string $absolutePath): CriterionResult
    {
        $content = $this->normalizeUtf8((string) LocalFile::get($absolutePath));
        if (trim($content) === '') {
            return $this->failedResult($criterion, ['HTML file is empty.'], 'HTML-файл пустой.');
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="utf-8" ?>'.$content,
            LIBXML_NONET | LIBXML_COMPACT | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        $errors = array_values(array_filter(
            libxml_get_errors(),
            fn (\LibXMLError $error) => $error->level >= LIBXML_ERR_ERROR && !$this->isIgnorableHtmlParseError($error),
        ));

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || $errors !== []) {
            $messages = $errors !== []
                ? array_map(
                    static fn (\LibXMLError $error) => trim($error->message).' (line '.$error->line.')',
                    array_slice($errors, 0, 5),
                )
                : ['HTML parser could not load the document.'];

            return $this->failedResult($criterion, $messages, 'HTML-разметка содержит ошибки парсинга.');
        }

        return $this->passedResult($criterion, ['HTML markup parsed without libxml errors.'], 'Серверная проверка HTML выполнена успешно.');
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function validateCssSyntax(array $criterion, string $absolutePath): CriterionResult
    {
        $content = $this->normalizeUtf8((string) LocalFile::get($absolutePath));
        $trimmed = trim($content);
        if ($trimmed === '') {
            return $this->failedResult($criterion, ['CSS file is empty.'], 'CSS-файл пустой.');
        }

        $sanitized = preg_replace('!/\*.*?\*/!su', '', $trimmed) ?? $trimmed;
        $structureError = $this->findCssStructureError($sanitized);
        if ($structureError !== null) {
            return $this->failedResult($criterion, [$structureError], 'CSS-структура содержит синтаксические ошибки.');
        }

        $hasRuleBlock = preg_match('/[^{}]+\{[^{}]*\}/u', $sanitized) === 1;
        $hasStandaloneAtRule = preg_match('/^\s*@[^;]+;\s*$/mu', $sanitized) === 1;

        if (!$hasRuleBlock && !$hasStandaloneAtRule) {
            return $this->failedResult(
                $criterion,
                ['No valid CSS rule blocks were detected.'],
                'CSS не содержит корректных правил или блоков деклараций.',
            );
        }

        return $this->passedResult($criterion, ['CSS structural validation passed.'], 'Серверная проверка CSS выполнена успешно.');
    }

    /**
     * @param array<string, mixed> $criterion
     * @param array<int, string> $command
     */
    protected function runProcessCriterion(array $criterion, array $command, string $successEvidence): CriterionResult
    {
        $process = new Process($command);
        $process->setTimeout((int) config('ai.execution.timeout', 15));
        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $timeout = (int) config('ai.execution.timeout', 15);

            return $this->failedResult(
                $criterion,
                ['Process exceeded timeout of '.$timeout.' seconds: '.$this->truncate(implode(' ', $command))],
                'Серверная проверка компиляции превысила лимит времени.',
            );
        }

        $output = trim($process->getOutput()."\n".$process->getErrorOutput());
        if ($process->isSuccessful()) {
            return $this->passedResult($criterion, [$successEvidence], 'Серверная проверка компиляции выполнена успешно.');
        }

        $evidence = $output !== '' ? [$this->truncate($output)] : ['Compilation check failed without detailed output.'];

        return $this->failedResult($criterion, $evidence, 'Серверная проверка компиляции завершилась ошибкой.');
    }

    /**
     * @param array<string, mixed> $criterion
     * @param array<int, string> $evidence
     */
    private function passedResult(array $criterion, array $evidence, string $feedback): CriterionResult
    {
        $weight = $this->resolveWeight($criterion);

        return new CriterionResult(
            criterionId: (string) ($criterion['id'] ?? 'server_compile'),
            label: (string) ($criterion['label'] ?? 'Compilation'),
            passed: true,
            score: $weight,
            evidence: $evidence,
            feedback: $feedback,
            status: 'passed',
            source: 'server',
            weight: $weight,
        );
    }

    /**
     * @param array<string, mixed> $criterion
     * @param array<int, string> $evidence
     */
    private function failedResult(array $criterion, array $evidence, string $feedback): CriterionResult
    {
        return new CriterionResult(
            criterionId: (string) ($criterion['id'] ?? 'server_compile'),
            label: (string) ($criterion['label'] ?? 'Compilation'),
            passed: false,
            score: 0,
            evidence: $evidence,
            feedback: $feedback,
            status: 'failed',
            source: 'server',
            weight: $this->resolveWeight($criterion),
        );
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function unsupportedResult(array $criterion, string $message): CriterionResult
    {
        return new CriterionResult(
            criterionId: (string) ($criterion['id'] ?? 'server_compile'),
            label: (string) ($criterion['label'] ?? 'Compilation'),
            passed: false,
            score: null,
            evidence: [$message],
            feedback: $message,
            status: 'unsupported',
            source: 'server',
            weight: $this->resolveWeight($criterion),
        );
    }

    protected function findExecutable(array $candidates): ?string
    {
        $finder = new ExecutableFinder();

        foreach ($candidates as $candidate) {
            $resolved = $finder->find($candidate);
            if ($resolved) {
                return $resolved;
            }
        }

        return null;
    }

    private function guessCSharpOutputType(string $absolutePath): string
    {
        $content = $this->normalizeUtf8((string) LocalFile::get($absolutePath));
        if (preg_match('/\bstatic\s+void\s+Main\b/u', $content) === 1) {
            return 'exe';
        }

        if (preg_match('/\b(class|record|struct|interface|namespace)\b/u', $content) === 1) {
            return 'library';
        }

        return 'exe';
    }

    private function buildTemporaryCSharpProject(string $outputType, string $targetFramework): string
    {
        return <<<XML
<Project Sdk="Microsoft.NET.Sdk">
  <PropertyGroup>
    <TargetFramework>{$targetFramework}</TargetFramework>
    <OutputType>{$outputType}</OutputType>
    <LangVersion>latest</LangVersion>
    <Nullable>disable</Nullable>
    <ImplicitUsings>disable</ImplicitUsings>
    <EnableDefaultCompileItems>false</EnableDefaultCompileItems>
  </PropertyGroup>
  <ItemGroup>
    <Compile Include="Submission.cs" />
  </ItemGroup>
</Project>
XML;
    }

    private function resolveDotnetTargetFramework(string $dotnet): string
    {
        $process = new Process([$dotnet, '--version']);
        $process->setTimeout((int) config('ai.execution.timeout', 15));
        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            return 'net8.0';
        }

        $version = trim($process->getOutput());
        if (preg_match('/^(\d+)/', $version, $matches) === 1) {
            return 'net'.$matches[1].'.0';
        }

        return 'net8.0';
    }

    private function findCssStructureError(string $content): ?string
    {
        $braceBalance = 0;
        $parenBalance = 0;
        $bracketBalance = 0;
        $quote = null;
        $escape = false;

        $length = mb_strlen($content, 'UTF-8');
        for ($index = 0; $index < $length; $index++) {
            $char = mb_substr($content, $index, 1, 'UTF-8');

            if ($quote !== null) {
                if ($escape) {
                    $escape = false;
                    continue;
                }

                if ($char === '\\') {
                    $escape = true;
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            match ($char) {
                '{' => $braceBalance++,
                '}' => $braceBalance--,
                '(' => $parenBalance++,
                ')' => $parenBalance--,
                '[' => $bracketBalance++,
                ']' => $bracketBalance--,
                default => null,
            };

            if ($braceBalance < 0 || $parenBalance < 0 || $bracketBalance < 0) {
                return 'CSS contains mismatched closing delimiters.';
            }
        }

        if ($quote !== null) {
            return 'CSS contains an unterminated string literal.';
        }

        if ($braceBalance !== 0 || $parenBalance !== 0 || $bracketBalance !== 0) {
            return 'CSS contains unbalanced braces, parentheses, or brackets.';
        }

        return null;
    }

    private function normalizeCriterionText(array $criterion): string
    {
        $texts = [
            $criterion['label'] ?? '',
            $criterion['description'] ?? '',
            $criterion['instructions'] ?? '',
            ...($criterion['checks'] ?? []),
        ];

        return mb_strtolower(implode(' ', array_map(static fn ($item) => (string) $item, $texts)), 'UTF-8');
    }

    /**
     * @param array<string, mixed> $criterion
     * @return array{operator: string, threshold: int}|null
     */
    private function resolveMethodCountExpectation(array $criterion): ?array
    {
        $text = $this->normalizeCriterionText($criterion);

        $symbolicPatterns = [
            '/(?:methods?|functions?|методов|функций)[^\d<>]=*\s*(>=|<=|>|<|=)\s*(\d+)/u',
            '/(>=|<=|>|<|=)\s*(\d+)/u',
        ];

        foreach ($symbolicPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                return [
                    'operator' => $matches[1],
                    'threshold' => (int) $matches[2],
                ];
            }
        }

        $verbalPatterns = [
            '/(?:не\s+меньше|at\s+least)\s+(\d+)/u' => '>=',
            '/(?:не\s+больше|at\s+most)\s+(\d+)/u' => '<=',
            '/(?:больше|more\s+than)\s+(\d+)/u' => '>',
            '/(?:меньше|less\s+than)\s+(\d+)/u' => '<',
            '/(?:ровно|exactly)\s+(\d+)/u' => '=',
        ];

        foreach ($verbalPatterns as $pattern => $operator) {
            if (preg_match($pattern, $text, $matches) === 1) {
                return [
                    'operator' => $operator,
                    'threshold' => (int) $matches[1],
                ];
            }
        }

        return null;
    }

    private function compareNumericExpectation(int $value, string $operator, int $threshold): bool
    {
        return match ($operator) {
            '>' => $value > $threshold,
            '>=' => $value >= $threshold,
            '<' => $value < $threshold,
            '<=' => $value <= $threshold,
            '=' => $value === $threshold,
            default => false,
        };
    }

    /**
     * @return array{path: string, extension: string, content: string}|string
     */
    private function loadStructuralFileContext(File $file): array|string
    {
        $disk = Storage::disk('public');
        if (!$disk->exists($file->path)) {
            throw new RuntimeException('Submission file is missing on disk.');
        }

        $absolutePath = $disk->path($file->path);
        $extension = strtolower((string) ($file->extension ?: pathinfo($absolutePath, PATHINFO_EXTENSION)));

        if (!in_array($extension, config('ai.code_extensions', []), true)) {
            return "Серверная структурная проверка для .{$extension} пока не поддерживается.";
        }

        return [
            'path' => $absolutePath,
            'extension' => $extension,
            'content' => $this->normalizeUtf8((string) LocalFile::get($absolutePath)),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function extractCallableNames(string $content, string $extension): array
    {
        $patterns = match ($extension) {
            'php' => [
                '/\bfunction\s+(?P<name>[A-Za-z_][A-Za-z0-9_]*)\s*\(/u',
            ],
            'py' => [
                '/^\s*def\s+(?P<name>[A-Za-z_][A-Za-z0-9_]*)\s*\(/mu',
            ],
            'js', 'jsx', 'ts', 'tsx', 'vue' => [
                '/\bfunction\s+(?P<name>[A-Za-z_$][A-Za-z0-9_$]*)\s*\(/u',
                '/^\s*(?:(?:public|private|protected|static|async|get|set)\s+)*(?P<name>[A-Za-z_$][A-Za-z0-9_$]*)\s*\([^;\n{}]*\)\s*\{/mu',
                '/\b(?P<name>[A-Za-z_$][A-Za-z0-9_$]*)\s*=\s*(?:async\s*)?\([^)]*\)\s*=>/u',
            ],
            default => [
                '/^\s*(?:(?:public|private|protected|internal|static|virtual|override|sealed|abstract|final|inline|constexpr|friend|extern|async|synchronized)\s+)+(?:[A-Za-z_\\\\][A-Za-z0-9_\\\\<>\[\],:&?]*\s+)+(?P<name>[A-Za-z_][A-Za-z0-9_]*)\s*\([^;\n{}]*\)\s*(?:const\s*)?(?:\{|=>)/mu',
                '/\bfunction\s+(?P<name>[A-Za-z_][A-Za-z0-9_]*)\s*\(/u',
                '/^\s*def\s+(?P<name>[A-Za-z_][A-Za-z0-9_]*)\s*\(/mu',
            ],
        };

        $callables = [];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
                continue;
            }

            foreach ($matches as $match) {
                if (!isset($match['name'][0], $match['name'][1])) {
                    continue;
                }

                $callables[(int) $match['name'][1]] = mb_strtolower((string) $match['name'][0], 'UTF-8');
            }
        }

        ksort($callables);

        return array_values($callables);
    }

    private function extractFirstClassName(string $content): ?string
    {
        if (preg_match('/\bclass\s+(?P<class>[A-Za-z_][A-Za-z0-9_]*)\b/u', $content, $matches) !== 1) {
            return null;
        }

        return (string) $matches['class'];
    }

    private function extractParentClassName(string $content): ?string
    {
        if (preg_match(
            '/\bclass\s+[A-Za-z_][A-Za-z0-9_]*\s+extends\s+(?P<parent>\\\\?[A-Za-z_\\\\][A-Za-z0-9_\\\\]*)\b/u',
            $content,
            $matches,
        ) !== 1) {
            return null;
        }

        return ltrim((string) $matches['parent'], '\\');
    }

    private function extractPhpNamespace(string $content): ?string
    {
        if (preg_match('/^\s*namespace\s+(?P<namespace>[A-Za-z_\\\\][A-Za-z0-9_\\\\]*)\s*;/mu', $content, $matches) !== 1) {
            return null;
        }

        return (string) $matches['namespace'];
    }

    private function isIgnorableHtmlParseError(\LibXMLError $error): bool
    {
        $message = trim($error->message);

        return preg_match(
            '/^Tag (main|section|article|header|footer|nav|aside|figure|figcaption|video|audio|source|template|time|mark|summary|details) invalid$/i',
            $message,
        ) === 1;
    }

    private function normalizeUtf8(string $content): string
    {
        if ($content === '') {
            return '';
        }

        if (mb_check_encoding($content, 'UTF-8')) {
            return $content;
        }

        $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1251', 'CP1251', 'ISO-8859-1', 'ASCII'], true);
        if ($encoding === false) {
            return mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        }

        return mb_convert_encoding($content, 'UTF-8', $encoding);
    }

    /**
     * @param array<string, mixed> $criterion
     */
    private function resolveWeight(array $criterion): ?int
    {
        return isset($criterion['weight']) && is_numeric($criterion['weight'])
            ? max(1, (int) $criterion['weight'])
            : null;
    }

    private function truncate(string $text, int $limit = 1200): string
    {
        if (mb_strlen($text, 'UTF-8') <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit, 'UTF-8')).'…';
    }
}
