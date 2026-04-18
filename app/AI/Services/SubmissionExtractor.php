<?php

namespace App\AI\Services;

use App\Models\File;
use Illuminate\Support\Facades\File as LocalFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class SubmissionExtractor
{
    /**
     * @return array<string, mixed>
     */
    public function extract(File $file): array
    {
        $path = Storage::disk('public')->path($file->path);
        if (!is_file($path)) {
            throw new RuntimeException('Submission file is missing on disk.');
        }

        $extension = strtolower((string) ($file->extension ?: pathinfo($path, PATHINFO_EXTENSION)));

        if (!in_array($extension, config('ai.supported_extensions', []), true)) {
            return [
                'kind' => 'unsupported',
                'path' => $file->original_name ?: basename($path),
                'notes' => [
                    "Unsupported file extension for v1 extraction: .{$extension}",
                ],
                'unsupported_files' => [$file->original_name ?: basename($path)],
            ];
        }

        return match ($extension) {
            'zip' => $this->extractZip($path, $file),
            'docx' => $this->extractDocx($path, $file),
            'doc' => $this->extractLegacyWord($path, $file),
            'xlsx' => $this->extractXlsx($path, $file),
            'xls' => $this->extractLegacyExcel($path, $file),
            'csv', 'tsv' => $this->extractDelimitedText($path, $file, $extension === 'tsv' ? "\t" : ','),
            default => $this->extractTextOrCode($path, $file, null),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function extractTextOrCode(string $path, File $file, ?string $relativePath): array
    {
        $content = LocalFile::get($path);
        $normalized = $this->normalizeUtf8($content);
        $extension = strtolower((string) ($file->extension ?: pathinfo($path, PATHINFO_EXTENSION)));
        $isCode = in_array($extension, config('ai.code_extensions', []), true);

        $snippet = $this->truncate($normalized, (int) config('ai.max_excerpt_chars', 8000));
        $lines = preg_split('/\R/u', $normalized) ?: [];
        $lineCount = count($lines);
        $todoCount = preg_match_all('/TODO|FIXME|HACK/ui', $normalized);
        $classCount = preg_match_all('/\b(class|interface|trait|struct)\b/ui', $normalized);
        $functionCount = preg_match_all('/\b(function|def|fn|public\s+function|private\s+function|protected\s+function)\b/ui', $normalized);

        return [
            'kind' => $isCode ? 'code' : 'text',
            'path' => $relativePath ?? ($file->original_name ?: basename($path)),
            'extension' => $extension,
            'line_count' => $lineCount,
            'statistics' => [
                'todo_count' => (int) $todoCount,
                'class_like_count' => (int) $classCount,
                'function_like_count' => (int) $functionCount,
            ],
            'snippet' => $snippet,
            'summary' => $this->summarizeLines($lines),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractDocx(string $path, File $file): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open DOCX file.');
        }

        $documentXml = $zip->getFromName('word/document.xml');
        if ($documentXml === false) {
            $zip->close();
            throw new RuntimeException('DOCX document.xml is missing.');
        }

        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($documentXml)) ?? '');
        $coreXml = $zip->getFromName('docProps/core.xml');
        $metadata = [];
        if ($coreXml !== false) {
            $metadata = $this->extractOfficeCoreMetadata($coreXml);
        }

        $zip->close();

        return [
            'kind' => 'docx',
            'path' => $file->original_name ?: basename($path),
            'text_excerpt' => $this->truncate($this->normalizeUtf8($text), (int) config('ai.max_extracted_chars', 60000)),
            'metadata' => $metadata,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractLegacyWord(string $path, File $file): array
    {
        return [
            'kind' => 'doc',
            'path' => $file->original_name ?: basename($path),
            'text_excerpt' => '',
            'metadata' => [],
            'notes' => [
                'Legacy .doc files are accepted but full text extraction is not available in v1.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractXlsx(string $path, File $file): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open XLSX file.');
        }

        $sharedStrings = $this->extractSharedStrings($zip);
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $sheetNames = $this->extractSheetNames($workbookXml ?: '');
        $sheets = [];

        for ($index = 0; $index < count($sheetNames); $index++) {
            $sheetXml = $zip->getFromName('xl/worksheets/sheet'.($index + 1).'.xml');
            if ($sheetXml === false) {
                continue;
            }

            $sheets[] = $this->parseSheetXml($sheetNames[$index], $sheetXml, $sharedStrings);
        }

        $zip->close();

        return [
            'kind' => 'xlsx',
            'path' => $file->original_name ?: basename($path),
            'sheet_count' => count($sheets),
            'sheets' => $sheets,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractLegacyExcel(string $path, File $file): array
    {
        return [
            'kind' => 'xls',
            'path' => $file->original_name ?: basename($path),
            'sheet_count' => 0,
            'sheets' => [],
            'notes' => [
                'Legacy .xls files are accepted but structured extraction is not available in v1.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractDelimitedText(string $path, File $file, string $delimiter): array
    {
        $content = $this->normalizeUtf8(LocalFile::get($path));
        $rows = preg_split('/\R/u', $content) ?: [];
        $previewRows = [];
        $maxRows = (int) config('ai.max_csv_preview_rows', 20);

        foreach (array_slice($rows, 0, $maxRows) as $row) {
            $previewRows[] = str_getcsv($row, $delimiter);
        }

        return [
            'kind' => $delimiter === "\t" ? 'tsv' : 'csv',
            'path' => $file->original_name ?: basename($path),
            'row_count' => count(array_filter($rows, static fn ($row) => trim($row) !== '')),
            'preview_rows' => $previewRows,
            'text_excerpt' => $this->truncate($content, (int) config('ai.max_extracted_chars', 60000)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractZip(string $path, File $file): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open ZIP archive.');
        }

        $maxEntries = (int) config('ai.zip.max_entries', 200);
        $maxTotalBytes = (int) config('ai.zip.max_total_uncompressed_bytes', 52428800);
        $supportedExtensions = config('ai.supported_extensions', []);
        $entryCount = $zip->numFiles;
        if ($entryCount > $maxEntries) {
            $zip->close();
            throw new RuntimeException("ZIP archive has too many entries: {$entryCount}.");
        }

        $totalBytes = 0;
        $tree = [];
        $artifacts = [];
        $unsupportedFiles = [];
        $tempRoot = storage_path('app/private/ai-review-temp/'.Str::uuid());
        LocalFile::ensureDirectoryExists($tempRoot);

        try {
            for ($i = 0; $i < $entryCount; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'] ?? '';
                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }

                $this->assertSafeArchivePath($name);

                $totalBytes += (int) ($stat['size'] ?? 0);
                if ($totalBytes > $maxTotalBytes) {
                    throw new RuntimeException('ZIP archive exceeds the configured uncompressed size limit.');
                }

                $normalizedPath = str_replace('\\', '/', $name);
                $tree[] = $normalizedPath;
                $extension = strtolower(pathinfo($normalizedPath, PATHINFO_EXTENSION));
                if (!in_array($extension, $supportedExtensions, true)) {
                    $unsupportedFiles[] = $normalizedPath;
                    continue;
                }

                $contents = $zip->getFromIndex($i);
                if ($contents === false) {
                    continue;
                }

                $fullPath = $tempRoot.'/'.str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
                LocalFile::ensureDirectoryExists(dirname($fullPath));
                LocalFile::put($fullPath, $contents);

                $virtualFile = new File([
                    'path' => $fullPath,
                    'original_name' => basename($normalizedPath),
                    'extension' => $extension,
                    'mime_type' => null,
                    'size_bytes' => strlen($contents),
                ]);

                $artifacts[] = match ($extension) {
                    'docx' => $this->extractDocx($fullPath, $virtualFile),
                    'doc' => $this->extractLegacyWord($fullPath, $virtualFile),
                    'xlsx' => $this->extractXlsx($fullPath, $virtualFile),
                    'xls' => $this->extractLegacyExcel($fullPath, $virtualFile),
                    'csv', 'tsv' => $this->extractDelimitedText($fullPath, $virtualFile, $extension === 'tsv' ? "\t" : ','),
                    default => $this->extractTextOrCode($fullPath, $virtualFile, $normalizedPath),
                };
            }
        } finally {
            $zip->close();
            LocalFile::deleteDirectory($tempRoot);
        }

        return [
            'kind' => 'zip',
            'path' => $file->original_name ?: basename($path),
            'tree' => $tree,
            'artifacts' => array_slice($artifacts, 0, (int) config('ai.max_files_per_review', 50)),
            'unsupported_files' => array_values(array_unique($unsupportedFiles)),
        ];
    }

    private function assertSafeArchivePath(string $path): void
    {
        $normalized = str_replace('\\', '/', $path);
        if (str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:/', $normalized)) {
            throw new RuntimeException('ZIP archive contains an absolute path.');
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '..') {
                throw new RuntimeException('ZIP archive contains path traversal entries.');
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function extractOfficeCoreMetadata(string $xml): array
    {
        try {
            $document = new SimpleXMLElement($xml);
            $namespaces = $document->getNamespaces(true);
            $core = [];
            foreach ($namespaces as $prefix => $namespace) {
                foreach ($document->children($namespace) as $key => $value) {
                    $core[$prefix.':'.$key] = trim((string) $value);
                }
            }

            return array_filter($core, static fn ($value) => $value !== '');
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        try {
            $document = new SimpleXMLElement($xml);
            $strings = [];
            foreach ($document->si as $item) {
                $strings[] = trim((string) $item->t);
            }

            return $strings;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractSheetNames(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        try {
            $document = new SimpleXMLElement($xml);
            $names = [];
            foreach ($document->sheets->sheet as $sheet) {
                $names[] = (string) $sheet['name'];
            }

            return $names;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<int, string> $sharedStrings
     * @return array<string, mixed>
     */
    private function parseSheetXml(string $sheetName, string $xml, array $sharedStrings): array
    {
        $previewRows = [];
        $maxRows = (int) config('ai.max_sheet_preview_rows', 20);
        $maxColumns = (int) config('ai.max_sheet_preview_columns', 12);
        $rowCount = 0;

        try {
            $document = new SimpleXMLElement($xml);
            foreach ($document->sheetData->row as $row) {
                $rowCount++;
                if (count($previewRows) >= $maxRows) {
                    continue;
                }

                $parsedRow = [];
                foreach ($row->c as $cell) {
                    if (count($parsedRow) >= $maxColumns) {
                        break;
                    }

                    $value = (string) $cell->v;
                    $type = (string) ($cell['t'] ?? '');
                    if ($type === 's' && isset($sharedStrings[(int) $value])) {
                        $value = $sharedStrings[(int) $value];
                    }
                    $parsedRow[] = $value;
                }

                $previewRows[] = $parsedRow;
            }
        } catch (\Throwable) {
            $previewRows = [];
        }

        return [
            'name' => $sheetName,
            'row_count' => $rowCount,
            'preview_rows' => $previewRows,
        ];
    }

    /**
     * @param array<int, string> $lines
     */
    private function summarizeLines(array $lines): string
    {
        $meaningful = array_values(array_filter(array_map('trim', $lines), static fn ($line) => $line !== ''));
        return $this->truncate(implode("\n", array_slice($meaningful, 0, 20)), 1200);
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

    private function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text, 'UTF-8') <= $maxLength) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $maxLength, 'UTF-8')).'…';
    }
}
