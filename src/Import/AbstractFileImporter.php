<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Import;

use Crosseno\Compiler\Exception\ResourceLimitExceeded;

abstract readonly class AbstractFileImporter implements ImporterInterface
{
    final protected function assertSize(SourceInput $source, ImportLimits $limits): void
    {
        $size = filesize($source->path);
        if (!\is_int($size) || $size > $limits->maximumSourceBytes) {
            throw new ResourceLimitExceeded('Source exceeds the configured byte limit.');
        }
    }

    final protected function assertRecord(int $number, ImportLimits $limits): void
    {
        if ($number > $limits->maximumRecords) {
            throw new ResourceLimitExceeded('Source exceeds the configured record limit.');
        }
    }
}
