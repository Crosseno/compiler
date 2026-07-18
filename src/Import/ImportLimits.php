<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Import;

use Crosseno\Compiler\Exception\InvalidConfiguration;

final readonly class ImportLimits
{
    public function __construct(
        public int $maximumSourceBytes = 268_435_456,
        public int $maximumRecords = 2_000_000,
        public int $maximumFields = 128,
        public int $maximumFieldBytes = 1_048_576,
        public int $maximumJsonDepth = 32,
    ) {
        foreach (get_object_vars($this) as $name => $value) {
            if ($value < 1) {
                throw new InvalidConfiguration($name . ' must be positive.');
            }
        }
    }
}
