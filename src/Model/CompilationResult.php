<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Model;

final readonly class CompilationResult
{
    /** @param array<string, int> $rejectionReasons */
    public function __construct(public CatalogData $catalog, public int $inputRecords, public array $rejectionReasons) {}

    public function rejectionCount(): int
    {
        return array_sum($this->rejectionReasons);
    }
}
