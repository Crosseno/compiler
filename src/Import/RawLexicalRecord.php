<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Import;

final readonly class RawLexicalRecord
{
    /**
     * @param list<string> $answerClasses
     * @param list<string> $dialects
     * @param list<string> $themes
     * @param list<RawClue> $clues
     */
    public function __construct(
        public string $sourceId,
        public int $sourceRecordNumber,
        public string $answer,
        public string $lemma,
        public string $language,
        public ?string $partOfSpeech,
        public ?string $senseId,
        public ?string $definition,
        public int $frequency,
        public ?int $difficulty,
        public string $properName,
        public string $abbreviation,
        public array $answerClasses,
        public array $dialects,
        public array $themes,
        public array $clues,
    ) {}
}
