<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Artifact;

use Crosseno\Compiler\Configuration\CompilerConfiguration;
use Crosseno\Compiler\Model\CatalogData;

interface ArtifactWriterInterface
{
    /** @return list<string> Relative POSIX artifact paths created under $buildDirectory. */
    public function write(CatalogData $catalog, CompilerConfiguration $configuration, string $buildDirectory): array;
}
