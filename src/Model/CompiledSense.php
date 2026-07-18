<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Model;

final readonly class CompiledSense
{
    /** @param list<string> $topics */
    public function __construct(
        public string $key,
        public string $lexemeKey,
        public string $definition,
        public ?string $sourceId,
        public array $topics,
    ) {}
}
