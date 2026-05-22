<?php

namespace App\AI\Services;

use App\Models\File;
use DOMDocument;
use DOMElement;
use DOMXPath;
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
        if (! is_file($path)) {
            throw new RuntimeException('Submission file is missing on disk.');
        }

        $extension = strtolower((string) ($file->extension ?: pathinfo($path, PATHINFO_EXTENSION)));

        if (! in_array($extension, config('ai.supported_extensions', []), true)) {
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
            'rar', '7z' => $this->extractUnsupportedArchive($path, $file, $extension),
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
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return $this->officeExtractionFailure('docx', $path, $file, 'Unable to open DOCX file.');
        }

        $documentXml = $zip->getFromName('word/document.xml');
        if ($documentXml === false) {
            $zip->close();

            return $this->officeExtractionFailure('docx', $path, $file, 'DOCX document.xml is missing.');
        }

        $text = $this->extractDocxText($zip, $documentXml);
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
            'notes' => trim($text) === '' ? ['DOCX text content is empty or could not be extracted.'] : [],
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
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return $this->officeExtractionFailure('xlsx', $path, $file, 'Unable to open XLSX file.');
        }

        $sharedStrings = $this->extractSharedStrings($zip);
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $workbookRelationshipsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $sheetEntries = $this->extractWorkbookSheetEntries($workbookXml ?: '', $workbookRelationshipsXml ?: '');
        $sheets = [];

        foreach ($sheetEntries as $index => $sheetEntry) {
            $sheetXml = $zip->getFromName($sheetEntry['path']);
            if ($sheetXml === false) {
                $fallbackSheetXml = $zip->getFromName('xl/worksheets/sheet'.($index + 1).'.xml');
                if ($fallbackSheetXml === false) {
                    continue;
                }

                $sheetXml = $fallbackSheetXml;
            }

            $parsedSheet = $this->parseSheetXml($sheetEntry['name'], $sheetXml, $sharedStrings);
            if ($parsedSheet['row_count'] === 0 && $parsedSheet['preview_rows'] === []) {
                continue;
            }

            $sheets[] = $parsedSheet;
        }

        $zip->close();

        return [
            'kind' => 'xlsx',
            'path' => $file->original_name ?: basename($path),
            'sheet_count' => count($sheets),
            'sheets' => $sheets,
            'notes' => count($sheets) === 0 ? ['XLSX workbook contains no extractable sheets.'] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractUnsupportedArchive(string $path, File $file, string $extension): array
    {
        return [
            'kind' => 'unsupported_archive',
            'path' => $file->original_name ?: basename($path),
            'extension' => $extension,
            'size_bytes' => is_file($path) ? filesize($path) : null,
            'notes' => [
                strtoupper($extension).' archives are accepted safely, but archive extraction is not available in v1. Upload ZIP for structured archive analysis.',
            ],
            'unsupported_files' => [$file->original_name ?: basename($path)],
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
        $zip = new ZipArchive;
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
                if (! in_array($extension, $supportedExtensions, true)) {
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
                    'rar', '7z' => $this->extractUnsupportedArchive($fullPath, $virtualFile, $extension),
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

    private function extractDocxText(ZipArchive $zip, string $documentXml): string
    {
        $parts = [];
        $documentText = $this->extractWordprocessingText($documentXml);
        if ($documentText !== '') {
            $parts[] = $documentText;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'] ?? '';
            if (! is_string($name) || ! preg_match('#^word/(?:header|footer|footnotes|endnotes|comments)\d*\.xml$#', $name)) {
                continue;
            }

            $xml = $zip->getFromIndex($i);
            if ($xml === false) {
                continue;
            }

            $partText = $this->extractWordprocessingText($xml);
            if ($partText !== '') {
                $parts[] = $partText;
            }
        }

        return $this->cleanExtractedText(implode("\n\n", $parts));
    }

    private function extractWordprocessingText(string $xml): string
    {
        $document = $this->loadXmlDocument($xml);
        if (! $document) {
            return $this->cleanExtractedText(strip_tags($xml));
        }

        $xpath = new DOMXPath($document);
        $paragraphs = [];
        $paragraphNodes = $xpath->query('//*[local-name() = "p"]');

        if ($paragraphNodes === false) {
            return $this->cleanExtractedText(strip_tags($xml));
        }

        foreach ($paragraphNodes as $paragraphNode) {
            if (! $paragraphNode instanceof DOMElement) {
                continue;
            }

            $pieces = [];
            $textNodes = $xpath->query('.//*[local-name() = "t" or local-name() = "tab" or local-name() = "br" or local-name() = "cr"]', $paragraphNode);
            if ($textNodes === false) {
                continue;
            }

            foreach ($textNodes as $textNode) {
                if (! $textNode instanceof DOMElement) {
                    continue;
                }

                $pieces[] = match ($textNode->localName) {
                    'tab' => "\t",
                    'br', 'cr' => "\n",
                    default => $textNode->textContent,
                };
            }

            $paragraph = $this->cleanExtractedText(implode('', $pieces));
            if ($paragraph !== '') {
                $paragraphs[] = $paragraph;
            }
        }

        if ($paragraphs === []) {
            return $this->cleanExtractedText($document->textContent);
        }

        return implode("\n", $paragraphs);
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

        $document = $this->loadXmlDocument($xml);
        if (! $document) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $items = $xpath->query('//*[local-name() = "si"]');
        if ($items === false) {
            return [];
        }

        $strings = [];
        foreach ($items as $item) {
            if (! $item instanceof DOMElement) {
                continue;
            }

            $strings[] = trim($this->collectDescendantText($item, 't'));
        }

        return $strings;
    }

    /**
     * @return array<int, array{name: string, path: string}>
     */
    private function extractWorkbookSheetEntries(string $workbookXml, string $relationshipsXml): array
    {
        if ($workbookXml === '') {
            return [];
        }

        $document = $this->loadXmlDocument($workbookXml);
        if (! $document) {
            return [];
        }

        $relationships = $this->extractWorkbookRelationships($relationshipsXml);
        $xpath = new DOMXPath($document);
        $sheetNodes = $xpath->query('//*[local-name() = "sheets"]/*[local-name() = "sheet"]');
        if ($sheetNodes === false) {
            return [];
        }

        $entries = [];
        foreach ($sheetNodes as $index => $sheetNode) {
            if (! $sheetNode instanceof DOMElement) {
                continue;
            }

            $relationshipId = $sheetNode->getAttributeNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                'id',
            ) ?: $sheetNode->getAttribute('r:id');

            $target = $relationships[$relationshipId] ?? 'worksheets/sheet'.($index + 1).'.xml';
            $entries[] = [
                'name' => $sheetNode->getAttribute('name') ?: 'Sheet '.($index + 1),
                'path' => $this->normalizeWorkbookTargetPath($target),
            ];
        }

        return $entries;
    }

    /**
     * @return array<string, string>
     */
    private function extractWorkbookRelationships(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        $document = $this->loadXmlDocument($xml);
        if (! $document) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $relationshipNodes = $xpath->query('//*[local-name() = "Relationship"]');
        if ($relationshipNodes === false) {
            return [];
        }

        $relationships = [];
        foreach ($relationshipNodes as $relationshipNode) {
            if (! $relationshipNode instanceof DOMElement) {
                continue;
            }

            $id = $relationshipNode->getAttribute('Id');
            $target = $relationshipNode->getAttribute('Target');
            if ($id !== '' && $target !== '') {
                $relationships[$id] = $target;
            }
        }

        return $relationships;
    }

    private function normalizeWorkbookTargetPath(string $target): string
    {
        $target = str_replace('\\', '/', $target);

        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }

        if (str_starts_with($target, 'xl/')) {
            return $target;
        }

        return 'xl/'.ltrim($target, '/');
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array<string, mixed>
     */
    private function parseSheetXml(string $sheetName, string $xml, array $sharedStrings): array
    {
        $previewRows = [];
        $maxRows = (int) config('ai.max_sheet_preview_rows', 20);
        $maxColumns = (int) config('ai.max_sheet_preview_columns', 12);
        $rowCount = 0;

        $document = $this->loadXmlDocument($xml);
        if (! $document) {
            return [
                'name' => $sheetName,
                'row_count' => 0,
                'preview_rows' => [],
            ];
        }

        $xpath = new DOMXPath($document);
        $rowNodes = $xpath->query('//*[local-name() = "sheetData"]/*[local-name() = "row"]');
        if ($rowNodes === false) {
            return [
                'name' => $sheetName,
                'row_count' => 0,
                'preview_rows' => [],
            ];
        }

        foreach ($rowNodes as $rowNode) {
            if (! $rowNode instanceof DOMElement) {
                continue;
            }

            $rowCount++;
            if (count($previewRows) >= $maxRows) {
                continue;
            }

            $parsedRow = [];
            $cellNodes = $xpath->query('./*[local-name() = "c"]', $rowNode);
            if ($cellNodes === false) {
                continue;
            }

            foreach ($cellNodes as $cellNode) {
                if (! $cellNode instanceof DOMElement) {
                    continue;
                }

                $columnIndex = $this->columnIndexFromCellReference($cellNode->getAttribute('r'));
                if ($columnIndex !== null && $columnIndex >= $maxColumns) {
                    continue;
                }

                $value = $this->extractSpreadsheetCellValue($cellNode, $sharedStrings);
                if ($columnIndex === null) {
                    if (count($parsedRow) >= $maxColumns) {
                        break;
                    }

                    $parsedRow[] = $value;
                } else {
                    $parsedRow[$columnIndex] = $value;
                }
            }

            if ($parsedRow !== []) {
                ksort($parsedRow);
                $previewRows[] = $this->compactSpreadsheetRow($parsedRow, $maxColumns);
            } else {
                $previewRows[] = [];
            }
        }

        return [
            'name' => $sheetName,
            'row_count' => $rowCount,
            'preview_rows' => $previewRows,
        ];
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function extractSpreadsheetCellValue(DOMElement $cell, array $sharedStrings): string
    {
        $type = $cell->getAttribute('t');
        $value = trim($this->firstDescendantText($cell, 'v'));

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }

        if ($type === 'inlineStr' || ($value === '' && $this->hasDescendant($cell, 'is'))) {
            return trim($this->collectDescendantText($cell, 't'));
        }

        return $value;
    }

    private function columnIndexFromCellReference(string $cellReference): ?int
    {
        if ($cellReference === '' || preg_match('/^([A-Z]+)/i', $cellReference, $matches) !== 1) {
            return null;
        }

        $column = strtoupper($matches[1]);
        $index = 0;
        for ($i = 0; $i < strlen($column); $i++) {
            $index = ($index * 26) + (ord($column[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }

    /**
     * @param  array<int, string>  $row
     * @return array<int, string>
     */
    private function compactSpreadsheetRow(array $row, int $maxColumns): array
    {
        $compacted = [];
        for ($index = 0; $index < $maxColumns; $index++) {
            if (! array_key_exists($index, $row)) {
                continue;
            }

            $compacted[] = $row[$index];
        }

        while ($compacted !== [] && end($compacted) === '') {
            array_pop($compacted);
        }

        return $compacted;
    }

    private function firstDescendantText(DOMElement $element, string $localName): string
    {
        foreach ($element->getElementsByTagName('*') as $node) {
            if ($node instanceof DOMElement && $node->localName === $localName) {
                return $node->textContent;
            }
        }

        return '';
    }

    private function collectDescendantText(DOMElement $element, string $localName): string
    {
        $parts = [];
        foreach ($element->getElementsByTagName('*') as $node) {
            if ($node instanceof DOMElement && $node->localName === $localName) {
                $parts[] = $node->textContent;
            }
        }

        return implode('', $parts);
    }

    private function hasDescendant(DOMElement $element, string $localName): bool
    {
        foreach ($element->getElementsByTagName('*') as $node) {
            if ($node instanceof DOMElement && $node->localName === $localName) {
                return true;
            }
        }

        return false;
    }

    private function loadXmlDocument(string $xml): ?DOMDocument
    {
        if (trim($xml) === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $document : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function officeExtractionFailure(string $kind, string $path, File $file, string $message): array
    {
        $result = [
            'kind' => $kind,
            'path' => $file->original_name ?: basename($path),
            'notes' => [$message],
            'unsupported_files' => [$file->original_name ?: basename($path)],
        ];

        if ($kind === 'docx') {
            $result['text_excerpt'] = '';
            $result['metadata'] = [];
        }

        if ($kind === 'xlsx') {
            $result['sheet_count'] = 0;
            $result['sheets'] = [];
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function summarizeLines(array $lines): string
    {
        $meaningful = array_values(array_filter(array_map('trim', $lines), static fn ($line) => $line !== ''));

        return $this->truncate(implode("\n", array_slice($meaningful, 0, 20)), 1200);
    }

    private function cleanExtractedText(string $text): string
    {
        $text = $this->normalizeUtf8($text);
        $text = str_replace(["\xC2\xA0", "\r\n", "\r"], [' ', "\n", "\n"], $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
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
