<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Pipeline;

use Crosseno\Compiler\Import\RawLexicalRecord;
use Crosseno\Core\Grid\CellSymbol;

interface EligibilityPolicyInterface
{
    /** @param non-empty-list<CellSymbol> $cells */
    public function rejectionReason(string $normalizedAnswer, array $cells, RawLexicalRecord $record): ?string;
}
