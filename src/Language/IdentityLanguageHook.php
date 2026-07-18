<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Language;

use Crosseno\Compiler\Import\RawLexicalRecord;

final readonly class IdentityLanguageHook implements LanguageHookInterface
{
    public function transform(string $normalizedAnswer, RawLexicalRecord $record): string
    {
        return $normalizedAnswer;
    }
}
