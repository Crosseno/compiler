<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Pipeline;

use Crosseno\Compiler\Exception\InvalidSourceRecord;
use Crosseno\Compiler\Exception\StableKeyCollision;
use Crosseno\Compiler\Import\RawLexicalRecord;
use Crosseno\Compiler\Language\LanguageHookInterface;
use Crosseno\Compiler\Model\CatalogData;
use Crosseno\Compiler\Model\CompilationResult;
use Crosseno\Compiler\Model\CompiledAnswer;
use Crosseno\Compiler\Model\CompiledClue;
use Crosseno\Compiler\Model\CompiledLexeme;
use Crosseno\Compiler\Model\CompiledSense;
use Crosseno\Lexicon\Identity\StableKeyAlgorithmVersion;
use Crosseno\Lexicon\Identity\StableKeyFactory;
use Crosseno\Lexicon\Language\AnswerNormalizerInterface;
use Crosseno\Lexicon\Language\CellTokenizerInterface;
use Crosseno\Lexicon\Language\LanguageCode;
use Crosseno\Lexicon\Manifest\SourceRecord;

final readonly class LexicalPipeline
{
    public function __construct(
        private AnswerNormalizerInterface $normalizer,
        private CellTokenizerInterface $tokenizer,
        private LanguageHookInterface $languageHook,
        private EligibilityPolicyInterface $eligibility,
        private ScorePolicyInterface $scores,
        private StableKeyFactory $keys = new StableKeyFactory(),
        private AuxiliaryKeyFactory $auxiliaryKeys = new AuxiliaryKeyFactory(),
    ) {}

    public function normalizationProfileId(): string
    {
        return $this->normalizer->profileId();
    }

    public function tokenizationProfileId(): string
    {
        return $this->tokenizer->profileId();
    }

    /**
     * @param iterable<RawLexicalRecord> $records
     * @param list<SourceRecord> $sources
     */
    public function compile(iterable $records, array $sources, string $namespace, LanguageCode $answerLanguage, bool $failOnRejection): CompilationResult
    {
        /** @var array<string, CompiledAnswer> $answers */
        $answers = [];
        /** @var array<string, CompiledLexeme> $lexemes */
        $lexemes = [];
        /** @var array<string, CompiledSense> $senses */
        $senses = [];
        /** @var array<string, CompiledClue> $clues */
        $clues = [];
        /** @var array<string, string> $topics */
        $topics = [];
        /** @var array<string, int> $rejections */
        $rejections = [];
        $inputCount = 0;
        foreach ($records as $record) {
            ++$inputCount;
            try {
                if ($record->language !== $answerLanguage->value) {
                    throw new InvalidSourceRecord('record_language_mismatch');
                }
                $this->accept($record, $namespace, $answerLanguage, $answers, $lexemes, $senses, $clues, $topics);
            } catch (InvalidSourceRecord $exception) {
                $reason = preg_match('/^[a-z0-9_]+$/D', $exception->getMessage()) === 1 ? $exception->getMessage() : 'invalid_record';
                if ($failOnRejection) {
                    throw new InvalidSourceRecord(\sprintf('%s record %d rejected: %s.', $record->sourceId, $record->sourceRecordNumber, $reason), previous: $exception);
                }
                $rejections[$reason] = ($rejections[$reason] ?? 0) + 1;
            }
        }

        ksort($answers, SORT_STRING);
        ksort($lexemes, SORT_STRING);
        ksort($senses, SORT_STRING);
        ksort($clues, SORT_STRING);
        ksort($topics, SORT_STRING);
        ksort($rejections, SORT_STRING);
        foreach ($answers as $answer) {
            sort($answer->answerClasses, SORT_STRING);
            sort($answer->dialects, SORT_STRING);
            sort($answer->themes, SORT_STRING);
            sort($answer->senseKeys, SORT_STRING);
            $answer->difficulty = $answer->difficultyCount === 0
                ? null
                : (int) round($answer->difficultyTotal / $answer->difficultyCount, 0, PHP_ROUND_HALF_EVEN);
            $answer->rank = $this->scores->rank(
                $answer->frequency,
                $answer->difficulty,
                \count($answer->senseKeys),
                $this->clueCount($answer->senseKeys, $clues),
            );
        }

        return new CompilationResult(new CatalogData($answers, $lexemes, $senses, $clues, $topics, $sources), $inputCount, $rejections);
    }

    /**
     * @param array<string, CompiledAnswer> $answers
     * @param array<string, CompiledLexeme> $lexemes
     * @param array<string, CompiledSense> $senses
     * @param array<string, CompiledClue> $clues
     * @param array<string, string> $topics
     */
    private function accept(
        RawLexicalRecord $record,
        string $namespace,
        LanguageCode $answerLanguage,
        array &$answers,
        array &$lexemes,
        array &$senses,
        array &$clues,
        array &$topics,
    ): void {
        $normalized = $this->languageHook->transform($this->normalizer->normalize($record->answer), $record);
        $normalized = $this->nfc($normalized, 'answer');
        $lemma = $this->nfc($record->lemma, 'lemma');
        $definition = $record->definition === null ? null : $this->nfc($record->definition, 'definition');
        $cells = $this->tokenizer->tokenize($normalized);
        if (($reason = $this->eligibility->rejectionReason($normalized, $cells, $record)) !== null) {
            throw new InvalidSourceRecord($reason);
        }
        new LanguageCode($record->language);
        foreach ($record->dialects as $dialect) {
            new LanguageCode($dialect);
        }
        foreach ($record->clues as $clue) {
            new LanguageCode($clue->language);
        }

        $version = StableKeyAlgorithmVersion::v1();
        $answerKey = $this->keys->answer($namespace, [$normalized], $version)->coreKey->value;
        $lexemeKey = $this->keys->lexeme($namespace, [$lemma, $record->language, $record->partOfSpeech ?? ''], $version)->value;
        $lexeme = new CompiledLexeme($lexemeKey, $lemma, $record->language, $record->partOfSpeech);
        $this->insertIdentical($lexemes, $lexemeKey, $lexeme, 'lexeme');

        $senseKey = null;
        if ($definition !== null || $record->senseId !== null || $record->clues !== []) {
            $identity = $record->senseId === null ? ['definition', $definition ?? ''] : ['source-id', $record->sourceId, $this->nfc($record->senseId, 'sense ID')];
            $senseKey = $this->keys->sense($namespace, [$lexemeKey, ...$identity], $version)->coreKey->value;
            $sense = new CompiledSense($senseKey, $lexemeKey, $definition ?? '', $record->sourceId, $record->themes);
            $this->insertIdentical($senses, $senseKey, $sense, 'sense');
            foreach ($record->themes as $topic) {
                $topics[$topic] = $topic;
            }
            foreach ($record->clues as $clue) {
                $text = $this->nfc($clue->text, 'clue');
                $clueKey = $this->auxiliaryKeys->clue($namespace, [$senseKey, $clue->language, $text]);
                $this->insertIdentical($clues, $clueKey, new CompiledClue($clueKey, $senseKey, $clue->language, $text, $clue->difficulty), 'clue');
            }
        }

        $cellValues = array_map(static fn($cell): string => $cell->value, $cells);
        if (!isset($answers[$answerKey])) {
            $answers[$answerKey] = new CompiledAnswer(
                $answerKey,
                $record->answer,
                $cellValues,
                $lexemeKey,
                $record->frequency,
                $record->difficulty,
                $record->properName,
                $record->abbreviation,
                $record->answerClasses,
                $record->dialects,
                $record->themes,
                $senseKey === null ? [] : [$senseKey],
            );

            return;
        }

        $answer = $answers[$answerKey];
        if ($answer->cells !== $cellValues || $answer->lexemeKey !== $lexemeKey) {
            throw new StableKeyCollision('Answer stable-key collision or incompatible homograph merge.');
        }
        $answer->displayText = strcmp($answer->displayText, $record->answer) <= 0 ? $answer->displayText : $record->answer;
        if ($answer->frequency > PHP_INT_MAX - $record->frequency) {
            throw new InvalidSourceRecord('frequency_overflow');
        }
        $answer->frequency += $record->frequency;
        if ($record->difficulty !== null) {
            $answer->difficultyTotal += $record->difficulty;
            ++$answer->difficultyCount;
        }
        $answer->properName = $this->mergeFlag($answer->properName, $record->properName);
        $answer->abbreviation = $this->mergeFlag($answer->abbreviation, $record->abbreviation);
        $answer->answerClasses = $this->union($answer->answerClasses, $record->answerClasses);
        $answer->dialects = $this->union($answer->dialects, $record->dialects);
        $answer->themes = $this->union($answer->themes, $record->themes);
        if ($senseKey !== null) {
            $answer->senseKeys = $this->union($answer->senseKeys, [$senseKey]);
        }
    }

    private function nfc(string $value, string $label): string
    {
        if (preg_match('//u', $value) !== 1 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1) {
            throw new InvalidSourceRecord('invalid_' . str_replace(' ', '_', strtolower($label)));
        }
        $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);

        return \is_string($normalized) ? $normalized : throw new InvalidSourceRecord('normalization_failed');
    }

    /**
     * @template T of object
     * @param array<string, T> $records
     * @param T $record
     * @param-out array<string, T> $records
     */
    private function insertIdentical(array &$records, string $key, object $record, string $type): void
    {
        if (!isset($records[$key])) {
            $records[$key] = $record;

            return;
        }
        if (serialize($records[$key]) !== serialize($record)) {
            throw new StableKeyCollision(ucfirst($type) . ' stable-key collision with non-identical canonical records.');
        }
    }

    private function mergeFlag(string $left, string $right): string
    {
        if ($left === $right || $right === 'unknown') {
            return $left;
        }
        if ($left === 'unknown') {
            return $right;
        }

        throw new InvalidSourceRecord('conflicting_flags');
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     * @return list<string>
     */
    private function union(array $left, array $right): array
    {
        $values = array_fill_keys([...$left, ...$right], true);
        ksort($values, SORT_STRING);

        return array_keys($values);
    }

    /**
     * @param list<string> $senseKeys
     * @param array<string, CompiledClue> $clues
     */
    private function clueCount(array $senseKeys, array $clues): int
    {
        $set = array_fill_keys($senseKeys, true);
        $count = 0;
        foreach ($clues as $clue) {
            if (isset($set[$clue->senseKey])) {
                ++$count;
            }
        }

        return $count;
    }
}
