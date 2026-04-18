<?php

namespace App\AI\Services;

use App\AI\DTO\CriterionResult;
use App\Models\File;
use Illuminate\Support\Facades\File as LocalFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ServerCriterionEvaluator
{
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
            if (!$this->isCompilationCriterion($criterion)) {
                $llmCriteria[] = $criterion;
                continue;
            }

            $result = $this->evaluateCompilationCriterion($file, $criterion);
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
    private function evaluateCompilationCriterion(File $file, array $criterion): CriterionResult
    {
        if (!config('ai.execution.enabled', true)) {
            return $this->unsupportedResult($criterion, 'Серверное выполнение проверок отключено.');
        }

        $absolutePath = Storage::disk('public')->path($file->path);
        if (!is_file($absolutePath)) {
            throw new RuntimeException('Submission file is missing on disk.');
        }

        $extension = strtolower((string) ($file->extension ?: pathinfo($absolutePath, PATHINFO_EXTENSION)));

        return match ($extension) {
            'php' => $this->runProcessCriterion(
                $criterion,
                [PHP_BINARY, '-l', $absolutePath],
                'PHP lint passed without syntax errors.',
            ),
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
    private function runProcessCriterion(array $criterion, array $command, string $successEvidence): CriterionResult
    {
        $process = new Process($command);
        $process->setTimeout((int) config('ai.execution.timeout', 15));
        $process->run();

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

    private function findExecutable(array $candidates): ?string
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
        $process->run();

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
