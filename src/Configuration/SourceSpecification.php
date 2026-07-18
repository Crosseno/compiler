<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Configuration;

use Crosseno\Compiler\Exception\InvalidConfiguration;
use Crosseno\Lexicon\Manifest\SourceRecord;

final readonly class SourceSpecification
{
    public function __construct(
        public string $path,
        public string $format,
        public SourceRecord $provenance,
        public ?string $sqliteTable = null,
    ) {
        if (!\in_array($format, ['csv', 'tsv', 'json', 'ndjson', 'sqlite', 'wordnet'], true)) {
            throw new InvalidConfiguration('Unknown source format: ' . $format . '.');
        }
    }
}
