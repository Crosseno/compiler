<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Import;

use Crosseno\Compiler\Exception\InvalidSourceRecord;
use Crosseno\Lexicon\Exception\InvalidLexiconValue;
use Crosseno\Lexicon\Serialization\DuplicateJsonKeyGuard;

final readonly class JsonImporter extends AbstractFileImporter
{
    public function __construct(private bool $newlineDelimited = false, private RecordMapper $mapper = new RecordMapper()) {}

    public function import(SourceInput $source, ImportLimits $limits): iterable
    {
        $this->assertSize($source, $limits);
        if ($this->newlineDelimited) {
            yield from $this->importLines($source, $limits);

            return;
        }
        $json = file_get_contents($source->path);
        if (!\is_string($json)) {
            throw new InvalidSourceRecord('Could not read JSON source.');
        }
        try {
            DuplicateJsonKeyGuard::assertNone($json);
            $records = json_decode($json, true, max(1, $limits->maximumJsonDepth), JSON_THROW_ON_ERROR);
        } catch (\JsonException|InvalidLexiconValue $exception) {
            throw new InvalidSourceRecord('JSON source is invalid.', previous: $exception);
        }
        if (!\is_array($records) || !array_is_list($records)) {
            throw new InvalidSourceRecord('JSON source root must be a list.');
        }
        foreach ($records as $index => $record) {
            $number = $index + 1;
            $this->assertRecord($number, $limits);
            if (!\is_array($record) || array_is_list($record)) {
                throw new InvalidSourceRecord(\sprintf('JSON record %d must be an object.', $number));
            }
            yield $this->mapper->map($record, $source->sourceId, $number, $limits);
        }
    }

    /** @return iterable<RawLexicalRecord> */
    private function importLines(SourceInput $source, ImportLimits $limits): iterable
    {
        $handle = fopen($source->path, 'rb');
        if (!\is_resource($handle)) {
            throw new InvalidSourceRecord('Could not open NDJSON source.');
        }
        try {
            $number = 0;
            $maximumLineBytes = $limits->maximumFieldBytes > intdiv(PHP_INT_MAX - 1, $limits->maximumFields)
                ? PHP_INT_MAX
                : max(1, $limits->maximumFieldBytes * $limits->maximumFields + 1);
            while (($line = fgets($handle, $maximumLineBytes)) !== false) {
                if (\strlen($line) >= $maximumLineBytes && !str_ends_with($line, "\n")) {
                    throw new InvalidSourceRecord('NDJSON line exceeds the configured bound.');
                }
                if (trim($line) === '') {
                    continue;
                }
                ++$number;
                $this->assertRecord($number, $limits);
                try {
                    DuplicateJsonKeyGuard::assertNone($line);
                    $record = json_decode($line, true, max(1, $limits->maximumJsonDepth), JSON_THROW_ON_ERROR);
                } catch (\JsonException|InvalidLexiconValue $exception) {
                    throw new InvalidSourceRecord(\sprintf('NDJSON record %d is invalid.', $number), previous: $exception);
                }
                if (!\is_array($record) || array_is_list($record)) {
                    throw new InvalidSourceRecord(\sprintf('NDJSON record %d must be an object.', $number));
                }
                yield $this->mapper->map($record, $source->sourceId, $number, $limits);
            }
        } finally {
            fclose($handle);
        }
    }
}
