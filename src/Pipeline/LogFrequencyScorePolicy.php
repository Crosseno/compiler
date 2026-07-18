<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Pipeline;

final readonly class LogFrequencyScorePolicy implements ScorePolicyInterface
{
    public function rank(int $frequency, ?int $difficulty, int $senseCount, int $clueCount): int
    {
        $frequencyScore = (int) round(log1p($frequency) * 100_000, 0, PHP_ROUND_HALF_EVEN);
        $quality = $senseCount * 1_000 + $clueCount * 100;
        $difficultyPenalty = ($difficulty ?? 50) * 10;

        return max(PHP_INT_MIN, min(PHP_INT_MAX, $frequencyScore + $quality - $difficultyPenalty));
    }
}
