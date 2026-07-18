<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Import;

final readonly class RawClue
{
    public function __construct(public string $language, public string $text, public ?int $difficulty) {}
}
