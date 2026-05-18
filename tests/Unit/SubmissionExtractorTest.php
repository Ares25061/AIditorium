<?php

namespace Tests\Unit;

use App\AI\Services\SubmissionExtractor;
use App\Models\File;
use Illuminate\Support\Facades\File as LocalFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionExtractorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_normalizes_windows_1251_text_into_utf8(): void
    {
        $content = mb_convert_encoding("print('Привет, мир')\n", 'Windows-1251', 'UTF-8');
        Storage::disk('public')->put('fixtures/hello.py', $content);

        $file = new File([
            'path' => 'fixtures/hello.py',
            'original_name' => 'hello.py',
            'extension' => 'py',
        ]);

        $result = app(SubmissionExtractor::class)->extract($file);

        $this->assertSame('code', $result['kind']);
        $this->assertStringContainsString('Привет', $result['snippet']);
    }

    public function test_extracts_docx_text(): void
    {
        $this->markTestSkippedIfZipExtensionMissing();

        $path = Storage::disk('public')->path('fixtures/report.docx');
        LocalFile::ensureDirectoryExists(dirname($path));

        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', '<document><body><p><t>Отчет по лабораторной работе</t></p></body></document>');
        $zip->addFromString('docProps/core.xml', '<coreProperties><title>Лаба</title></coreProperties>');
        $zip->close();

        $file = new File([
            'path' => 'fixtures/report.docx',
            'original_name' => 'report.docx',
            'extension' => 'docx',
        ]);

        $result = app(SubmissionExtractor::class)->extract($file);

        $this->assertSame('docx', $result['kind']);
        $this->assertStringContainsString('Отчет по лабораторной работе', $result['text_excerpt']);
    }

    public function test_extracts_namespaced_docx_text_with_utf8_spacing(): void
    {
        $this->markTestSkippedIfZipExtensionMissing();

        $path = Storage::disk('public')->path('fixtures/namespaced-report.docx');
        LocalFile::ensureDirectoryExists(dirname($path));

        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Заголовок</w:t></w:r></w:p>
    <w:p><w:r><w:t xml:space="preserve">Первый абзац </w:t><w:t>с пробелом</w:t></w:r></w:p>
    <w:p><w:r><w:t>Второй</w:t><w:tab/><w:t>абзац</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        $file = new File([
            'path' => 'fixtures/namespaced-report.docx',
            'original_name' => 'отчет.docx',
            'extension' => 'docx',
        ]);

        $result = app(SubmissionExtractor::class)->extract($file);

        $this->assertSame('docx', $result['kind']);
        $this->assertStringContainsString("Заголовок\nПервый абзац с пробелом\nВторой абзац", $result['text_excerpt']);
    }

    public function test_extracts_xlsx_sheet_preview(): void
    {
        $this->markTestSkippedIfZipExtensionMissing();

        $path = Storage::disk('public')->path('fixtures/table.xlsx');
        LocalFile::ensureDirectoryExists(dirname($path));

        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook><sheets><sheet name="Лист1"/></sheets></workbook>');
        $zip->addFromString('xl/sharedStrings.xml', '<sst><si><t>Имя</t></si><si><t>Баллы</t></si><si><t>Анна</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet><sheetData><row><c t="s"><v>0</v></c><c t="s"><v>1</v></c></row><row><c t="s"><v>2</v></c><c><v>95</v></c></row></sheetData></worksheet>');
        $zip->close();

        $file = new File([
            'path' => 'fixtures/table.xlsx',
            'original_name' => 'table.xlsx',
            'extension' => 'xlsx',
        ]);

        $result = app(SubmissionExtractor::class)->extract($file);

        $this->assertSame('xlsx', $result['kind']);
        $this->assertSame('Лист1', $result['sheets'][0]['name']);
        $this->assertSame(['Имя', 'Баллы'], $result['sheets'][0]['preview_rows'][0]);
    }

    public function test_extracts_xlsx_inline_strings(): void
    {
        $this->markTestSkippedIfZipExtensionMissing();

        $path = Storage::disk('public')->path('fixtures/inline-table.xlsx');
        LocalFile::ensureDirectoryExists(dirname($path));

        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Журнал" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Журнал группы</t></is></c><c r="B1" t="inlineStr"><is><t>25ИС1-6</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>Предмет</t></is></c><c r="B2" t="inlineStr"><is><t>Моделирование физических процессов</t></is></c></row></sheetData></worksheet>');
        $zip->close();

        $file = new File([
            'path' => 'fixtures/inline-table.xlsx',
            'original_name' => 'журнал.xlsx',
            'extension' => 'xlsx',
        ]);

        $result = app(SubmissionExtractor::class)->extract($file);

        $this->assertSame('xlsx', $result['kind']);
        $this->assertSame('Журнал', $result['sheets'][0]['name']);
        $this->assertSame(['Журнал группы', '25ИС1-6'], $result['sheets'][0]['preview_rows'][0]);
        $this->assertSame(['Предмет', 'Моделирование физических процессов'], $result['sheets'][0]['preview_rows'][1]);
    }

    public function test_corrupted_docx_returns_notes_without_throwing(): void
    {
        Storage::disk('public')->put('fixtures/corrupted.docx', 'not a zip container');

        $file = new File([
            'path' => 'fixtures/corrupted.docx',
            'original_name' => 'битый.docx',
            'extension' => 'docx',
        ]);

        $result = app(SubmissionExtractor::class)->extract($file);

        $this->assertSame('docx', $result['kind']);
        $this->assertSame('', $result['text_excerpt']);
        $this->assertStringContainsString('Unable to open DOCX file', $result['notes'][0]);
    }

    public function test_rar_is_reported_as_unsupported_archive_without_binary_text_extraction(): void
    {
        Storage::disk('public')->put('fixtures/archive.rar', "Rar!\x1A\x07\x00binary");

        $file = new File([
            'path' => 'fixtures/archive.rar',
            'original_name' => 'тест.rar',
            'extension' => 'rar',
        ]);

        $result = app(SubmissionExtractor::class)->extract($file);

        $this->assertSame('unsupported_archive', $result['kind']);
        $this->assertSame('rar', $result['extension']);
        $this->assertSame(['тест.rar'], $result['unsupported_files']);
    }

    public function test_extracts_zip_with_docx_code_and_csv_artifacts(): void
    {
        $this->markTestSkippedIfZipExtensionMissing();

        $path = Storage::disk('public')->path('fixtures/submission-bundle.zip');
        LocalFile::ensureDirectoryExists(dirname($path));

        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('report.docx', $this->buildDocxBytes('Текст отчета из архива'));
        $zip->addFromString('src/solution.php', "<?php\n\nfunction solve(): string\n{\n    return 'ok';\n}\n");
        $zip->addFromString('data/result.csv', "name,score\nАнна,95\n");
        $zip->addFromString('assets/image.bin', "\x00\x01binary");
        $zip->close();

        $file = new File([
            'path' => 'fixtures/submission-bundle.zip',
            'original_name' => 'submission-bundle.zip',
            'extension' => 'zip',
        ]);

        $result = app(SubmissionExtractor::class)->extract($file);

        $this->assertSame('zip', $result['kind']);
        $this->assertContains('report.docx', $result['tree']);
        $this->assertContains('src/solution.php', $result['tree']);
        $this->assertContains('data/result.csv', $result['tree']);
        $this->assertContains('assets/image.bin', $result['unsupported_files']);
        $this->assertSame(['docx', 'code', 'csv'], array_column($result['artifacts'], 'kind'));
        $this->assertStringContainsString('Текст отчета из архива', $result['artifacts'][0]['text_excerpt']);
        $this->assertStringContainsString('function solve', $result['artifacts'][1]['snippet']);
        $this->assertSame(['name', 'score'], $result['artifacts'][2]['preview_rows'][0]);
    }

    public function test_rejects_zip_path_traversal(): void
    {
        $this->markTestSkippedIfZipExtensionMissing();

        $path = Storage::disk('public')->path('fixtures/danger.zip');
        LocalFile::ensureDirectoryExists(dirname($path));

        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('../evil.php', '<?php echo 1;');
        $zip->close();

        $file = new File([
            'path' => 'fixtures/danger.zip',
            'original_name' => 'danger.zip',
            'extension' => 'zip',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('path traversal');

        app(SubmissionExtractor::class)->extract($file);
    }

    private function markTestSkippedIfZipExtensionMissing(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('zip extension is required for this test.');
        }
    }

    private function buildDocxBytes(string $text): string
    {
        $path = Storage::disk('public')->path('fixtures/inner-docx-'.uniqid().'.docx');
        LocalFile::ensureDirectoryExists(dirname($path));

        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString(
            'word/document.xml',
            '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'
            .htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8')
            .'</w:t></w:r></w:p></w:body></w:document>',
        );
        $zip->close();

        $bytes = LocalFile::get($path);
        LocalFile::delete($path);

        return $bytes;
    }
}
