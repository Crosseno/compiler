<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Application;

use Crosseno\Compiler\Artifact\ArtifactValidator;
use Crosseno\Compiler\Configuration\ConfigurationLoader;
use Crosseno\Compiler\Exception\CompilerException;
use Crosseno\Compiler\Import\ImporterRegistry;
use Crosseno\Compiler\Import\ImportLimits;
use Crosseno\Compiler\Import\RawClue;
use Crosseno\Compiler\Import\RawLexicalRecord;
use Crosseno\Compiler\Import\SourceInput;

final readonly class CommandLineApplication
{
    /** @param list<string> $arguments */
    public function run(array $arguments): int
    {
        try {
            $command = $arguments[1] ?? 'help';

            return match ($command) {
                'import' => $this->import($arguments),
                'compile' => $this->compile($arguments),
                'validate' => $this->validate($arguments),
                'inspect' => $this->inspect($arguments),
                'stats' => $this->stats($arguments),
                'help', '--help', '-h' => $this->help(),
                default => $this->usageError('Unknown command: ' . $command),
            };
        } catch (CompilerException|\InvalidArgumentException|\JsonException $exception) {
            fwrite(STDERR, 'Error: ' . $exception->getMessage() . "\n");

            return 1;
        }
    }

    /** @param list<string> $arguments */
    private function import(array $arguments): int
    {
        $format = $arguments[2] ?? throw new \InvalidArgumentException('Usage: import FORMAT INPUT OUTPUT [SOURCE_ID].');
        $input = $arguments[3] ?? throw new \InvalidArgumentException('Usage: import FORMAT INPUT OUTPUT [SOURCE_ID].');
        $output = $arguments[4] ?? throw new \InvalidArgumentException('Usage: import FORMAT INPUT OUTPUT [SOURCE_ID].');
        $sourceId = $arguments[5] ?? 'import';
        if (file_exists($output)) {
            throw new \InvalidArgumentException('Import output already exists.');
        }
        $handle = fopen($output, 'xb');
        if (!\is_resource($handle)) {
            throw new \InvalidArgumentException('Import output could not be created.');
        }
        try {
            $count = 0;
            $importer = (new ImporterRegistry())->forFormat($format);
            foreach ($importer->import(new SourceInput($input, $sourceId), new ImportLimits()) as $record) {
                fwrite($handle, json_encode($this->recordPayload($record), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
                ++$count;
            }
        } catch (\Throwable $exception) {
            fclose($handle);
            unlink($output);
            throw $exception;
        }
        fclose($handle);
        fwrite(STDOUT, \sprintf("Imported %d records.\n", $count));

        return 0;
    }

    /** @param list<string> $arguments */
    private function compile(array $arguments): int
    {
        $configurationPath = $arguments[2] ?? throw new \InvalidArgumentException('Usage: compile CONFIG OUTPUT.');
        $output = $arguments[3] ?? throw new \InvalidArgumentException('Usage: compile CONFIG OUTPUT.');
        $configuration = (new ConfigurationLoader())->load($configurationPath);
        $manifest = CompilerFactory::generic(
            $configuration->metadata->normalizationProfileId,
            $configuration->metadata->tokenizationProfileId,
        )->compile($configuration, $output);
        fwrite(STDOUT, \sprintf("Compiled %d answers to %s.\n", $manifest->recordCount, $output));

        return 0;
    }

    /** @param list<string> $arguments */
    private function validate(array $arguments): int
    {
        $root = $arguments[2] ?? throw new \InvalidArgumentException('Usage: validate PACK_ROOT.');
        $manifest = (new ArtifactValidator())->validate($root);
        fwrite(STDOUT, \sprintf("Valid catalog-only pack %s (%s).\n", $manifest->metadata->packId, $manifest->metadata->dataVersion));

        return 0;
    }

    /** @param list<string> $arguments */
    private function inspect(array $arguments): int
    {
        $root = $arguments[2] ?? throw new \InvalidArgumentException('Usage: inspect PACK_ROOT.');
        $manifest = (new ArtifactValidator())->validate($root);
        fwrite(STDOUT, $manifest->toJson() . "\n");

        return 0;
    }

    /** @param list<string> $arguments */
    private function stats(array $arguments): int
    {
        $root = $arguments[2] ?? throw new \InvalidArgumentException('Usage: stats PACK_ROOT.');
        $manifest = (new ArtifactValidator())->validate($root);
        $pdo = new \PDO('sqlite:' . realpath($root . '/catalog.sqlite'), null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $stats = [
            'answers' => $this->count($pdo, 'answer'),
            'lexemes' => $this->count($pdo, 'lexeme'),
            'senses' => $this->count($pdo, 'sense'),
            'clues' => $this->count($pdo, 'clue'),
            'rejections' => $manifest->rejectionCount,
        ];
        fwrite(STDOUT, json_encode($stats, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n");

        return 0;
    }

    private function help(): int
    {
        fwrite(STDOUT, "Crosseno Compiler\nCommands: import, compile, validate, inspect, stats\n");

        return 0;
    }

    private function usageError(string $message): int
    {
        fwrite(STDERR, $message . "\n");

        return 2;
    }

    /** @return array<string, mixed> */
    private function recordPayload(RawLexicalRecord $record): array
    {
        return [
            'answer' => $record->answer,
            'lemma' => $record->lemma,
            'language' => $record->language,
            'part_of_speech' => $record->partOfSpeech,
            'sense_id' => $record->senseId,
            'definition' => $record->definition,
            'frequency' => $record->frequency,
            'difficulty' => $record->difficulty,
            'proper_name' => $record->properName,
            'abbreviation' => $record->abbreviation,
            'answer_classes' => $record->answerClasses,
            'dialects' => $record->dialects,
            'themes' => $record->themes,
            'clues' => array_map(static fn(RawClue $clue): array => [
                'language' => $clue->language,
                'text' => $clue->text,
                'difficulty' => $clue->difficulty,
            ], $record->clues),
        ];
    }

    private function count(\PDO $pdo, string $table): int
    {
        $statement = $pdo->query('SELECT COUNT(*) FROM ' . $table);
        if (!$statement instanceof \PDOStatement) {
            throw new \InvalidArgumentException('Could not query catalog statistics.');
        }
        $value = $statement->fetchColumn();

        return \is_int($value) ? $value : throw new \InvalidArgumentException('Catalog statistics are invalid.');
    }
}
