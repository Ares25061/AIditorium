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

        $zip = new \ZipArchive();
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

    public function test_extracts_xlsx_sheet_preview(): void
    {
        $this->markTestSkippedIfZipExtensionMissing();

        $path = Storage::disk('public')->path('fixtures/table.xlsx');
        LocalFile::ensureDirectoryExists(dirname($path));

        $zip = new \ZipArchive();
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

    public function test_rejects_zip_path_traversal(): void
    {
        $this->markTestSkippedIfZipExtensionMissing();

        $path = Storage::disk('public')->path('fixtures/danger.zip');
        LocalFile::ensureDirectoryExists(dirname($path));

        $zip = new \ZipArchive();
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
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('zip extension is required for this test.');
        }
    }
}
