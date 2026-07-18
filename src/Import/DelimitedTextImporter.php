<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Import;

use Crosseno\Compiler\Exception\InvalidConfiguration;
use Crosseno\Compiler\Exception\InvalidSourceRecord;

final readonly class DelimitedTextImporter extends AbstractFileImporter
{
    public function __construct(private string $delimiter, private RecordMapper $mapper = new RecordMapper())
    {
        if (!\in_array($delimiter, [',', "\t"], true)) {
            throw new InvalidConfiguration('Delimited importer supports comma and tab delimiters only.');
        }
    }

    public static function csv(): self
    {
        return new self(',');
    }

    public static function tsv(): self
    {
        return new self("\t");
    }

    public function import(SourceInput $source, ImportLimits $limits): iterable
    {
        $this->assertSize($source, $limits);
        $handle = fopen($source->path, 'rb');
        if (!\is_resource($handle)) {
            throw new InvalidSourceRecord('Could not open delimited source.');
        }

        try {
            $maximumLineBytes = $this->maximumLineBytes($limits);
            $header = fgetcsv($handle, $maximumLineBytes, $this->delimiter, '"', '');
            if (!\is_array($header) || \count($header) > $limits->maximumFields) {
                throw new InvalidSourceRecord('Delimited source requires a bounded header row.');
            }
            /** @var list<string> $headerNames */
            $headerNames = [];
            foreach ($header as $name) {
                if (!\is_string($name)) {
                    throw new InvalidSourceRecord('Delimited source header names must be strings.');
                }
                $headerNames[] = $name;
            }
            $firstHeader = $headerNames[0] ?? throw new InvalidSourceRecord('Delimited source requires a header row.');
            $headerNames[0] = preg_replace('/^\xEF\xBB\xBF/', '', $firstHeader) ?? $firstHeader;
            if (\count(array_unique($headerNames)) !== \count($headerNames)) {
                throw new InvalidSourceRecord('Delimited source header names must be unique.');
            }
            foreach ($headerNames as $name) {
                if (!\is_string($name) || preg_match('/^[a-z][a-z0-9_]*$/D', $name) !== 1) {
                    throw new InvalidSourceRecord('Delimited source contains an invalid header name.');
                }
            }

            $number = 0;
            while (($row = fgetcsv($handle, $maximumLineBytes, $this->delimiter, '"', '')) !== false) {
                ++$number;
                $this->assertRecord($number, $limits);
                if (\count($row) !== \count($headerNames)) {
                    throw new InvalidSourceRecord(\sprintf('%s record %d has the wrong field count.', $source->sourceId, $number));
                }
                // Formula prefixes are lexical data: preserve them exactly, never evaluate them.
                /** @var array<string, mixed> $data */
                $data = array_combine($headerNames, $row);
                yield $this->mapper->map($data, $source->sourceId, $number, $limits);
            }
        } finally {
            fclose($handle);
        }
    }

    /** @return positive-int */
    private function maximumLineBytes(ImportLimits $limits): int
    {
        $value = $limits->maximumFieldBytes > intdiv(PHP_INT_MAX, $limits->maximumFields)
            ? PHP_INT_MAX
            : $limits->maximumFieldBytes * $limits->maximumFields;
        if ($value < 1) {
            throw new InvalidConfiguration('Delimited line limit must be positive.');
        }

        return $value;
    }
}
