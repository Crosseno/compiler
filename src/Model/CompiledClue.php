<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Model;

final readonly class CompiledClue
{
    public function __construct(public string $key, public string $senseKey, public string $language, public string $text, public ?int $difficulty) {}
}
