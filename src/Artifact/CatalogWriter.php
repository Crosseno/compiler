<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Artifact;

use Crosseno\Compiler\Configuration\CompilerConfiguration;
use Crosseno\Compiler\Exception\ArtifactFailure;
use Crosseno\Compiler\Model\CatalogData;

final readonly class CatalogWriter implements ArtifactWriterInterface
{
    public function __construct(private string $schemaPath = __DIR__ . '/../../resources/schema/catalog-v1.sql') {}

    public function write(CatalogData $catalog, CompilerConfiguration $configuration, string $buildDirectory): array
    {
        $path = $buildDirectory . '/catalog.sqlite';
        $schema = file_get_contents($this->schemaPath);
        if (!\is_string($schema)) {
            throw new ArtifactFailure('Catalog schema could not be read.');
        }
        try {
            $pdo = new \PDO('sqlite:' . $path, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
            $pdo->exec('PRAGMA page_size = 4096');
            $pdo->exec('PRAGMA auto_vacuum = NONE');
            $pdo->exec('PRAGMA journal_mode = OFF');
            $pdo->exec('PRAGMA synchronous = OFF');
            $pdo->exec('PRAGMA temp_store = MEMORY');
            $pdo->exec('PRAGMA encoding = "UTF-8"');
            $pdo->exec($schema);
            $pdo->beginTransaction();
            $this->populate($pdo, $catalog, $configuration);
            $pdo->commit();
            $pdo->exec('PRAGMA optimize');
            $pdo->exec('VACUUM');
            $pdo = null;
        } catch (\PDOException $exception) {
            throw new ArtifactFailure('Catalog SQLite writing failed.', previous: $exception);
        }

        return ['catalog.sqlite'];
    }

    private function populate(\PDO $pdo, CatalogData $catalog, CompilerConfiguration $configuration): void
    {
        $digest = $catalog->stableKeyDigest();
        $metadata = $pdo->prepare('INSERT INTO package_metadata VALUES (1, 1, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $metadata->execute([
            $configuration->metadata->packId,
            $configuration->metadata->dataVersion,
            $configuration->metadata->answerLanguage->value,
            $configuration->metadata->normalizationProfileId,
            $configuration->metadata->tokenizationProfileId,
            $configuration->metadata->stableKeyAlgorithmVersion->major,
            $digest,
            $configuration->ordinalSpaceId,
            \count($catalog->answers),
            $configuration->metadata->packId,
            $configuration->metadata->dataVersion,
            \count($catalog->answers),
            $digest,
            $configuration->metadata->tokenizationProfileId,
        ]);

        $insertSource = $pdo->prepare('INSERT INTO source VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($catalog->sources as $source) {
            $insertSource->execute([$source->id, $source->url, $source->versionOrDate, $source->licenseExpression, $source->attribution, $source->transformation, $source->redistributionStatus]);
        }
        $insertLexeme = $pdo->prepare('INSERT INTO lexeme VALUES (?, ?, ?, ?)');
        foreach ($catalog->lexemes as $lexeme) {
            $insertLexeme->execute([$lexeme->key, $lexeme->lemma, $lexeme->language, $lexeme->partOfSpeech]);
        }
        $insertSense = $pdo->prepare('INSERT INTO sense VALUES (?, ?, ?, ?)');
        foreach ($catalog->senses as $sense) {
            $insertSense->execute([$sense->key, $sense->lexemeKey, $sense->definition, $sense->sourceId]);
        }
        $insertAnswer = $pdo->prepare('INSERT INTO answer VALUES (?, ?, ?, ?, ?, ?)');
        $insertCell = $pdo->prepare('INSERT INTO answer_cell VALUES (?, ?, ?)');
        $insertClass = $pdo->prepare('INSERT INTO answer_class VALUES (?, ?)');
        $insertDialect = $pdo->prepare('INSERT INTO answer_dialect VALUES (?, ?)');
        $insertCoverage = $pdo->prepare('INSERT INTO answer_clue_coverage VALUES (?, ?)');
        $insertTheme = $pdo->prepare('INSERT INTO answer_theme VALUES (?, ?)');
        $insertAnswerLexeme = $pdo->prepare('INSERT INTO answer_lexeme VALUES (?, ?)');
        $insertAnswerSense = $pdo->prepare('INSERT INTO answer_sense VALUES (?, ?)');
        foreach ($catalog->answers as $answer) {
            $insertAnswer->execute([$answer->key, $answer->displayText, $answer->difficulty, $answer->properName, $answer->abbreviation, $answer->rank]);
            foreach ($answer->cells as $position => $cell) {
                $insertCell->execute([$answer->key, $position, $cell]);
            }
            foreach ($answer->answerClasses as $class) {
                $insertClass->execute([$answer->key, $class]);
            }
            foreach ($answer->dialects as $dialect) {
                $insertDialect->execute([$answer->key, $dialect]);
            }
            foreach ($this->coverage($answer->senseKeys, $catalog) as $language) {
                $insertCoverage->execute([$answer->key, $language]);
            }
            foreach ($answer->themes as $theme) {
                $insertTheme->execute([$answer->key, $theme]);
            }
            $insertAnswerLexeme->execute([$answer->key, $answer->lexemeKey]);
            foreach ($answer->senseKeys as $senseKey) {
                $insertAnswerSense->execute([$answer->key, $senseKey]);
            }
        }
        $insertSenseTopic = $pdo->prepare('INSERT INTO sense_topic VALUES (?, ?)');
        $insertClue = $pdo->prepare('INSERT INTO clue VALUES (?, ?, ?, ?, ?)');
        foreach ($catalog->clues as $clue) {
            $insertClue->execute([$clue->key, $clue->senseKey, $clue->language, $clue->text, $clue->difficulty]);
        }
        $insertTopic = $pdo->prepare('INSERT INTO topic VALUES (?, ?)');
        foreach ($catalog->topics as $id => $label) {
            $insertTopic->execute([$id, $label]);
        }
        foreach ($catalog->senses as $sense) {
            foreach ($sense->topics as $topic) {
                $insertSenseTopic->execute([$sense->key, $topic]);
            }
        }
    }

    /**
     * @param list<string> $senseKeys
     * @return list<string>
     */
    private function coverage(array $senseKeys, CatalogData $catalog): array
    {
        $senseSet = array_fill_keys($senseKeys, true);
        $languages = [];
        foreach ($catalog->clues as $clue) {
            if (isset($senseSet[$clue->senseKey])) {
                $languages[$clue->language] = true;
            }
        }
        ksort($languages, SORT_STRING);

        return array_keys($languages);
    }
}
