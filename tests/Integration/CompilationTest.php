<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Tests\Integration;

use Crosseno\Compiler\Application\CompilerFactory;
use Crosseno\Compiler\Configuration\CompilerConfiguration;
use Crosseno\Compiler\Configuration\SourceSpecification;
use Crosseno\Compiler\Import\ImportLimits;
use Crosseno\Core\ResourceLimits;
use Crosseno\Lexicon\Identity\StableKeyAlgorithmVersion;
use Crosseno\Lexicon\Language\LanguageCode;
use Crosseno\Lexicon\Manifest\LanguagePackMetadata;
use Crosseno\Lexicon\Manifest\SourceRecord;
use Crosseno\LexiconSqlite\Catalog\SchemaInspector;
use Crosseno\LexiconSqlite\CatalogLimits;
use Crosseno\LexiconSqlite\Connection\ReadOnlyConnectionFactory;
use Crosseno\LexiconSqlite\Pack\PackLoader;
use PHPUnit\Framework\TestCase;

final class CompilationTest extends TestCase
{
    private string $directory;
    private string $sourcePath;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/crosseno-compile-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0o700);
        $this->sourcePath = $this->directory . '/source.json';
        file_put_contents($this->sourcePath, json_encode([
            ['answer' => 'alpha', 'language' => 'en', 'part_of_speech' => 'noun', 'sense_id' => '1', 'definition' => 'The first letter.', 'frequency' => 100, 'difficulty' => 10, 'themes' => ['letters'], 'clues' => [['language' => 'en', 'text' => 'Greek first', 'difficulty' => 5]]],
            ['answer' => 'beta', 'language' => 'en', 'part_of_speech' => 'noun', 'sense_id' => '2', 'definition' => 'The second letter.', 'frequency' => 50],
            ['answer' => 'x', 'language' => 'en'],
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        $this->remove($this->directory);
    }

    public function testCatalogManifestHashesAttributionSchemaAndReaderAcceptance(): void
    {
        $output = $this->directory . '/pack';
        $manifest = CompilerFactory::generic()->compile($this->configuration(), $output);

        self::assertSame(2, $manifest->recordCount);
        self::assertSame(1, $manifest->rejectionCount);
        self::assertSame('Fixture source', $manifest->sources()[0]->attribution);
        foreach ($manifest->artifacts() as $artifact) {
            self::assertSame($artifact->sha256, hash_file('sha256', $output . '/' . $artifact->path));
        }
        $pdo = new \PDO('sqlite:' . $output . '/catalog.sqlite', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        self::assertSame(1_129_467_731, $pdo->query('PRAGMA application_id')->fetchColumn());
        self::assertSame('Fixture source', $pdo->query('SELECT attribution FROM source')->fetchColumn());
        self::assertSame(2, $pdo->query('SELECT COUNT(*) FROM answer')->fetchColumn());

        $loaded = $this->loader()->load($output);
        self::assertSame('fixture.en', $loaded->manifest->metadata->packId);
    }

    public function testCompilationIsByteReproducible(): void
    {
        $first = $this->directory . '/first';
        $second = $this->directory . '/second';
        CompilerFactory::generic()->compile($this->configuration(), $first);
        CompilerFactory::generic()->compile($this->configuration(), $second);

        foreach (['catalog.sqlite', 'compilation-report.json', 'manifest.json'] as $path) {
            self::assertSame(hash_file('sha256', $first . '/' . $path), hash_file('sha256', $second . '/' . $path), $path);
        }
    }

    private function configuration(): CompilerConfiguration
    {
        $hash = hash_file('sha256', $this->sourcePath);
        self::assertIsString($hash);

        return new CompilerConfiguration(
            new LanguagePackMetadata('fixture.en', new LanguageCode('en'), '2026.07.1', 'nfc-v1', 'grapheme-v1', StableKeyAlgorithmVersion::v1()),
            'fixture',
            '0.1.0',
            '0.1.0',
            '0.1.0',
            [new SourceSpecification($this->sourcePath, 'json', new SourceRecord('fixture', 'https://example.com/source.json', '2026-07', $hash, 'CC-BY-4.0', 'Fixture source', 'NFC normalized', 'redistributable'))],
            new ImportLimits(),
            false,
            'fixture-en-2026-07-1',
        );
    }

    private function loader(): PackLoader
    {
        return new PackLoader(
            new ReadOnlyConnectionFactory(),
            new SchemaInspector(),
            CatalogLimits::standard(),
            ResourceLimits::standard(),
            false,
        );
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (new \FilesystemIterator($path) as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            if ($item->isDir() && !$item->isLink()) {
                $this->remove($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($path);
    }
}
