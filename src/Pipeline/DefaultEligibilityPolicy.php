<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Pipeline;

use Crosseno\Compiler\Exception\InvalidConfiguration;
use Crosseno\Compiler\Import\RawLexicalRecord;

final readonly class DefaultEligibilityPolicy implements EligibilityPolicyInterface
{
    public function __construct(private int $minimumCells = 2, private int $maximumCells = 64, private int $maximumAnswerBytes = 1024)
    {
        if ($minimumCells < 1 || $maximumCells < $minimumCells || $maximumAnswerBytes < 1) {
            throw new InvalidConfiguration('Eligibility limits are invalid.');
        }
    }

    public function rejectionReason(string $normalizedAnswer, array $cells, RawLexicalRecord $record): ?string
    {
        if (\strlen($normalizedAnswer) > $this->maximumAnswerBytes) {
            return 'answer_too_large';
        }
        if (\count($cells) < $this->minimumCells) {
            return 'answer_too_short';
        }
        if (\count($cells) > $this->maximumCells) {
            return 'answer_too_long';
        }
        if (preg_match('/[\p{C}\p{Z}]/u', $normalizedAnswer) === 1) {
            return 'ineligible_character';
        }

        return null;
    }
}
