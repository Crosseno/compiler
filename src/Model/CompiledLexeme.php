<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Model;

final readonly class CompiledLexeme
{
    public function __construct(public string $key, public string $lemma, public string $language, public ?string $partOfSpeech) {}
}
