<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Model;

use Crosseno\Lexicon\Manifest\SourceRecord;

final readonly class CatalogData
{
    /**
     * @param array<string, CompiledAnswer> $answers
     * @param array<string, CompiledLexeme> $lexemes
     * @param array<string, CompiledSense> $senses
     * @param array<string, CompiledClue> $clues
     * @param array<string, string> $topics
     * @param list<SourceRecord> $sources
     */
    public function __construct(
        public array $answers,
        public array $lexemes,
        public array $senses,
        public array $clues,
        public array $topics,
        public array $sources,
    ) {}

    public function stableKeyDigest(): string
    {
        return hash('sha256', implode("\n", array_keys($this->answers)));
    }
}
