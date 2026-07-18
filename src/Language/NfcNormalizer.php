<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Language;

use Crosseno\Compiler\Exception\InvalidSourceRecord;
use Crosseno\Lexicon\Language\AnswerNormalizerInterface;

final readonly class NfcNormalizer implements AnswerNormalizerInterface
{
    public function __construct(private string $id = 'nfc-v1') {}

    public function profileId(): string
    {
        return $this->id;
    }

    public function normalize(string $answer): string
    {
        if (preg_match('//u', $answer) !== 1) {
            throw new InvalidSourceRecord('Answer must be valid UTF-8.');
        }
        $normalized = \Normalizer::normalize($answer, \Normalizer::FORM_C);
        if (!\is_string($normalized)) {
            throw new InvalidSourceRecord('Answer could not be NFC-normalized.');
        }

        return $normalized;
    }
}
