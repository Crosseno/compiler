<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Language;

use Crosseno\Compiler\Exception\InvalidSourceRecord;
use Crosseno\Core\Grid\CellSymbol;
use Crosseno\Lexicon\Language\CellTokenizerInterface;

final readonly class GraphemeTokenizer implements CellTokenizerInterface
{
    public function __construct(private string $id = 'grapheme-v1') {}

    public function profileId(): string
    {
        return $this->id;
    }

    public function tokenize(string $normalizedAnswer): array
    {
        $length = grapheme_strlen($normalizedAnswer);
        if (!\is_int($length) || $length < 1) {
            throw new InvalidSourceRecord('Normalized answer must contain at least one grapheme.');
        }
        $cells = [];
        for ($index = 0; $index < $length; ++$index) {
            $cell = grapheme_substr($normalizedAnswer, $index, 1);
            if (!\is_string($cell) || $cell === '') {
                throw new InvalidSourceRecord('Normalized answer could not be tokenized.');
            }
            $cells[] = new CellSymbol($cell);
        }

        return $cells;
    }
}
