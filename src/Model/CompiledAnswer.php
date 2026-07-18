<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Model;

final class CompiledAnswer
{
    /**
     * @param non-empty-list<string> $cells
     * @param list<string> $answerClasses
     * @param list<string> $dialects
     * @param list<string> $themes
     * @param list<string> $senseKeys
     */
    public function __construct(
        public readonly string $key,
        public string $displayText,
        public readonly array $cells,
        public readonly string $lexemeKey,
        public int $frequency,
        public ?int $difficulty,
        public string $properName,
        public string $abbreviation,
        public array $answerClasses,
        public array $dialects,
        public array $themes,
        public array $senseKeys,
        public int $rank = 0,
        public int $difficultyTotal = 0,
        public int $difficultyCount = 0,
    ) {
        if ($difficulty !== null && $difficultyCount === 0) {
            $this->difficultyTotal = $difficulty;
            $this->difficultyCount = 1;
        }
    }
}
