<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Tests\Unit;

use Crosseno\Compiler\Exception\InvalidSourceRecord;
use Crosseno\Compiler\Import\DelimitedTextImporter;
use Crosseno\Compiler\Import\ImportLimits;
use Crosseno\Compiler\Import\JsonImporter;
use Crosseno\Compiler\Import\RecordMapper;
use Crosseno\Compiler\Import\SourceInput;
use PHPUnit\Framework\TestCase;

final class ImporterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/crosseno-import-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0o700);
    }

    protected function tearDown(): void
    {
        foreach (new \FilesystemIterator($this->directory) as $item) {
            if ($item instanceof \SplFileInfo) {
                unlink($item->getPathname());
            }
        }
        rmdir($this->directory);
    }

    public function testCsvQuotingLineEndingsAndFormulaPrefixesRemainInert(): void
    {
        $path = $this->directory . '/source.csv';
        file_put_contents($path, "answer,language,definition\r\n=SUM(1+1),en,\"text, quoted\"\r\n");
        $records = iterator_to_array(DelimitedTextImporter::csv()->import(new SourceInput($path, 'fixture'), new ImportLimits()));

        self::assertSame('=SUM(1+1)', $records[0]->answer);
        self::assertSame('text, quoted', $records[0]->definition);
    }

    public function testJsonRejectsNonObjectRecords(): void
    {
        $path = $this->directory . '/source.json';
        file_put_contents($path, '["invalid"]');

        $this->expectException(InvalidSourceRecord::class);
        iterator_to_array((new JsonImporter())->import(new SourceInput($path, 'fixture'), new ImportLimits()));
    }

    public function testNestedClueTextHonorsTheFieldByteLimit(): void
    {
        $this->expectException(InvalidSourceRecord::class);
        $this->expectExceptionMessage('text exceeds the field byte limit');

        (new RecordMapper())->map([
            'answer' => 'CAT',
            'language' => 'en',
            'clues' => [['language' => 'en', 'text' => '12345']],
        ], 'fixture', 1, new ImportLimits(maximumFieldBytes: 4));
    }

    public function testIndividualListValuesHonorTheFieldByteLimit(): void
    {
        $this->expectException(InvalidSourceRecord::class);
        $this->expectExceptionMessage('answer_classes contains a value that exceeds the field byte limit');

        (new RecordMapper())->map([
            'answer' => 'CAT',
            'language' => 'en',
            'answer_classes' => ['12345'],
        ], 'fixture', 1, new ImportLimits(maximumFieldBytes: 4));
    }
}
