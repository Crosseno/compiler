<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Import;

/** Adapter for application-provided arrays or streaming records. */
final readonly class IterableImporter
{
    public function __construct(private RecordMapper $mapper = new RecordMapper()) {}

    /**
     * @param iterable<array<string, mixed>> $records
     * @return iterable<RawLexicalRecord>
     */
    public function import(iterable $records, string $sourceId, ImportLimits $limits): iterable
    {
        $number = 0;
        foreach ($records as $record) {
            ++$number;
            if ($number > $limits->maximumRecords) {
                throw new \Crosseno\Compiler\Exception\ResourceLimitExceeded('Stream exceeds the configured record limit.');
            }
            yield $this->mapper->map($record, $sourceId, $number, $limits);
        }
    }
}
