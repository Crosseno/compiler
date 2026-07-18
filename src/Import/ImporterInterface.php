<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Import;

interface ImporterInterface
{
    /** @return iterable<RawLexicalRecord> */
    public function import(SourceInput $source, ImportLimits $limits): iterable;
}
