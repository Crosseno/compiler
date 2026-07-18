<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Tests\Unit;

use Crosseno\Compiler\Exception\StableKeyCollision;
use Crosseno\Compiler\Import\ImportLimits;
use Crosseno\Compiler\Import\IterableImporter;
use Crosseno\Compiler\Language\GraphemeTokenizer;
use Crosseno\Compiler\Language\IdentityLanguageHook;
use Crosseno\Compiler\Language\NfcNormalizer;
use Crosseno\Compiler\Pipeline\DefaultEligibilityPolicy;
use Crosseno\Compiler\Pipeline\LexicalPipeline;
use Crosseno\Compiler\Pipeline\LogFrequencyScorePolicy;
use Crosseno\Lexicon\Language\LanguageCode;
use Crosseno\Lexicon\Manifest\SourceRecord;
use PHPUnit\Framework\TestCase;

final class NormalizationMergeAndScoreTest extends TestCase
{
    public function testNormalizationStableKeysMergingAndScoresAreDeterministic(): void
    {
        $records = (new IterableImporter())->import([
            ['answer' => "cafe\u{0301}", 'lemma' => 'café', 'language' => 'fr', 'sense_id' => 'n1', 'definition' => 'A coffee shop.', 'frequency' => 10, 'difficulty' => 20, 'clue' => 'Coffee place', 'clue_language' => 'en'],
            ['answer' => 'café', 'lemma' => 'café', 'language' => 'fr', 'sense_id' => 'n1', 'definition' => 'A coffee shop.', 'frequency' => 15, 'difficulty' => 40, 'clue' => 'Coffee place', 'clue_language' => 'en'],
        ], 'fixture', new ImportLimits());

        $result = $this->pipeline()->compile($records, [$this->source()], 'fixture', new LanguageCode('fr'), true);

        self::assertCount(1, $result->catalog->answers);
        $answer = array_values($result->catalog->answers)[0];
        self::assertSame(['c', 'a', 'f', 'é'], $answer->cells);
        self::assertSame(25, $answer->frequency);
        self::assertSame(30, $answer->difficulty);
        self::assertGreaterThan(0, $answer->rank);
        self::assertCount(1, $result->catalog->senses);
        self::assertCount(1, $result->catalog->clues);
        self::assertMatchesRegularExpression('/^xk1:answer:fixture:[a-f0-9]{64}$/', $answer->key);
    }

    public function testConflictingSenseWithSameSourceIdentityFails(): void
    {
        $records = (new IterableImporter())->import([
            ['answer' => 'planet', 'language' => 'en', 'sense_id' => 'same', 'definition' => 'A celestial body.'],
            ['answer' => 'planet', 'language' => 'en', 'sense_id' => 'same', 'definition' => 'An unrelated definition.'],
        ], 'fixture', new ImportLimits());

        $this->expectException(StableKeyCollision::class);
        $this->pipeline()->compile($records, [$this->source()], 'fixture', new LanguageCode('en'), false);
    }

    public function testInvalidRecordsCanBeCountedByReason(): void
    {
        $records = (new IterableImporter())->import([
            ['answer' => 'x', 'language' => 'en'],
            ['answer' => 'valid', 'language' => 'en'],
        ], 'fixture', new ImportLimits());

        $result = $this->pipeline()->compile($records, [$this->source()], 'fixture', new LanguageCode('en'), false);

        self::assertSame(['answer_too_short' => 1], $result->rejectionReasons);
        self::assertCount(1, $result->catalog->answers);
    }

    private function pipeline(): LexicalPipeline
    {
        return new LexicalPipeline(
            new NfcNormalizer(),
            new GraphemeTokenizer(),
            new IdentityLanguageHook(),
            new DefaultEligibilityPolicy(),
            new LogFrequencyScorePolicy(),
        );
    }

    private function source(): SourceRecord
    {
        return new SourceRecord('fixture', 'https://example.com/data', '1', str_repeat('0', 64), 'CC0-1.0', 'Fixture', 'None', 'redistributable');
    }
}
