<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Import;

use Crosseno\Compiler\Exception\InvalidSourceRecord;

final readonly class RecordMapper
{
    /** @param array<mixed, mixed> $data */
    public function map(array $data, string $sourceId, int $number, ImportLimits $limits): RawLexicalRecord
    {
        if (\count($data) > $limits->maximumFields) {
            throw new InvalidSourceRecord($this->at($sourceId, $number, 'too many fields'));
        }
        $answer = $this->requiredString($data, 'answer', $sourceId, $number, $limits);
        $lemma = $this->optionalString($data, 'lemma', $limits) ?? $answer;
        $clues = [];
        if (isset($data['clues'])) {
            if (!\is_array($data['clues']) || !array_is_list($data['clues'])) {
                throw new InvalidSourceRecord($this->at($sourceId, $number, 'clues must be a list'));
            }
            foreach ($data['clues'] as $clue) {
                if (!\is_array($clue) || array_is_list($clue)) {
                    throw new InvalidSourceRecord($this->at($sourceId, $number, 'clue must be an object'));
                }
                $clues[] = new RawClue(
                    $this->requiredString($clue, 'language', $sourceId, $number, $limits),
                    $this->requiredString($clue, 'text', $sourceId, $number, $limits),
                    $this->nullableInteger($clue['difficulty'] ?? null, 'clue difficulty', 0, 100),
                );
            }
        } elseif (($clueText = $this->optionalString($data, 'clue', $limits)) !== null) {
            $clues[] = new RawClue(
                $this->optionalString($data, 'clue_language', $limits) ?? $this->requiredString($data, 'language', $sourceId, $number, $limits),
                $clueText,
                $this->nullableInteger($data['clue_difficulty'] ?? null, 'clue difficulty', 0, 100),
            );
        }

        return new RawLexicalRecord(
            $sourceId,
            $number,
            $answer,
            $lemma,
            $this->requiredString($data, 'language', $sourceId, $number, $limits),
            $this->optionalString($data, 'part_of_speech', $limits),
            $this->optionalString($data, 'sense_id', $limits),
            $this->optionalString($data, 'definition', $limits),
            $this->integer($data['frequency'] ?? 0, 'frequency', 0, PHP_INT_MAX),
            $this->nullableInteger($data['difficulty'] ?? null, 'difficulty', 0, 100),
            $this->enum($data['proper_name'] ?? 'unknown', 'proper_name'),
            $this->enum($data['abbreviation'] ?? 'unknown', 'abbreviation'),
            $this->stringList($data['answer_classes'] ?? ['standard'], 'answer_classes', $limits),
            $this->stringList($data['dialects'] ?? [], 'dialects', $limits),
            $this->stringList($data['themes'] ?? [], 'themes', $limits),
            $clues,
        );
    }

    /** @param array<mixed, mixed> $data */
    private function requiredString(array $data, string $key, string $source, int $number, ImportLimits $limits): string
    {
        $value = $this->optionalString($data, $key, $limits);
        if ($value === null) {
            throw new InvalidSourceRecord($this->at($source, $number, $key . ' is required'));
        }

        return $value;
    }

    /** @param array<mixed, mixed> $data */
    private function optionalString(array $data, string $key, ImportLimits $limits): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!\is_string($value)) {
            throw new InvalidSourceRecord($key . ' must be valid UTF-8 without control characters.');
        }
        if (\strlen($value) > $limits->maximumFieldBytes) {
            throw new InvalidSourceRecord($key . ' exceeds the field byte limit.');
        }
        if (preg_match('//u', $value) !== 1 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1) {
            throw new InvalidSourceRecord($key . ' must be valid UTF-8 without control characters.');
        }

        return $value;
    }

    private function integer(mixed $value, string $label, int $minimum, int $maximum): int
    {
        if (\is_string($value) && preg_match('/^(0|[1-9][0-9]*)$/D', $value) === 1) {
            $value = (int) $value;
        }
        if (!\is_int($value) || $value < $minimum || $value > $maximum) {
            throw new InvalidSourceRecord($label . ' is outside its allowed integer range.');
        }

        return $value;
    }

    private function nullableInteger(mixed $value, string $label, int $minimum, int $maximum): ?int
    {
        return $value === null || $value === '' ? null : $this->integer($value, $label, $minimum, $maximum);
    }

    private function enum(mixed $value, string $label): string
    {
        if (!\is_string($value) || !\in_array($value, ['yes', 'no', 'unknown'], true)) {
            throw new InvalidSourceRecord($label . ' must be yes, no, or unknown.');
        }

        return $value;
    }

    /** @return list<string> */
    private function stringList(mixed $value, string $label, ImportLimits $limits): array
    {
        if (\is_string($value)) {
            $value = $value === '' ? [] : explode('|', $value);
        }
        if (!\is_array($value) || !array_is_list($value)) {
            throw new InvalidSourceRecord($label . ' must be a list.');
        }
        $result = [];
        foreach ($value as $item) {
            if (!\is_string($item) || $item === '') {
                throw new InvalidSourceRecord($label . ' contains an invalid value.');
            }
            if (\strlen($item) > $limits->maximumFieldBytes) {
                throw new InvalidSourceRecord($label . ' contains a value that exceeds the field byte limit.');
            }
            if (preg_match('//u', $item) !== 1 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $item) === 1) {
                throw new InvalidSourceRecord($label . ' contains an invalid value.');
            }
            $result[$item] = $item;
        }
        ksort($result, SORT_STRING);

        return array_values($result);
    }

    private function at(string $source, int $number, string $message): string
    {
        return \sprintf('%s record %d: %s.', $source, $number, $message);
    }
}
