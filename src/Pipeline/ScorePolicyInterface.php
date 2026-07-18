<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Pipeline;

interface ScorePolicyInterface
{
    public function rank(int $frequency, ?int $difficulty, int $senseCount, int $clueCount): int;
}
