<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Application;

use Crosseno\Compiler\Import\ImporterRegistry;
use Crosseno\Compiler\Language\GraphemeTokenizer;
use Crosseno\Compiler\Language\IdentityLanguageHook;
use Crosseno\Compiler\Language\NfcNormalizer;
use Crosseno\Compiler\Pipeline\DefaultEligibilityPolicy;
use Crosseno\Compiler\Pipeline\LexicalPipeline;
use Crosseno\Compiler\Pipeline\LogFrequencyScorePolicy;

final readonly class CompilerFactory
{
    public static function generic(string $normalizationProfile = 'nfc-v1', string $tokenizationProfile = 'grapheme-v1'): CompilationService
    {
        return new CompilationService(
            new ImporterRegistry(),
            new LexicalPipeline(
                new NfcNormalizer($normalizationProfile),
                new GraphemeTokenizer($tokenizationProfile),
                new IdentityLanguageHook(),
                new DefaultEligibilityPolicy(),
                new LogFrequencyScorePolicy(),
            ),
        );
    }
}
