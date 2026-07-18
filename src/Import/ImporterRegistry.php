<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Import;

use Crosseno\Compiler\Exception\InvalidConfiguration;

final readonly class ImporterRegistry
{
    public function forFormat(string $format, ?string $sqliteTable = null): ImporterInterface
    {
        return match ($format) {
            'csv' => DelimitedTextImporter::csv(),
            'tsv' => DelimitedTextImporter::tsv(),
            'json' => new JsonImporter(),
            'ndjson' => new JsonImporter(true),
            'sqlite' => new SqliteImporter($sqliteTable ?? 'entries'),
            'wordnet' => new WordNetImporter(),
            default => throw new InvalidConfiguration('Unsupported source format: ' . $format . '.'),
        };
    }
}
