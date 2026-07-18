<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Import;

use Crosseno\Compiler\Exception\InvalidConfiguration;

final readonly class SourceInput
{
    public function __construct(public string $path, public string $sourceId)
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/D', $sourceId) !== 1) {
            throw new InvalidConfiguration('Source ID must be a lowercase portable identifier.');
        }
        if (str_contains($path, "\0") || !is_file($path) || is_link($path) || !is_readable($path)) {
            throw new InvalidConfiguration('Source path must be a readable local regular file and cannot be a symlink.');
        }
    }
}
