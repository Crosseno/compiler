<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Language;

use Crosseno\Compiler\Import\RawLexicalRecord;

interface LanguageHookInterface
{
    /** Return a transformed normalized answer, or throw to reject the record. */
    public function transform(string $normalizedAnswer, RawLexicalRecord $record): string;
}
