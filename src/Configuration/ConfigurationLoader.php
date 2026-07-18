<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Configuration;

use Crosseno\Compiler\Exception\InvalidConfiguration;
use Crosseno\Compiler\Import\ImportLimits;
use Crosseno\Lexicon\Exception\InvalidLexiconValue;
use Crosseno\Lexicon\Identity\StableKeyAlgorithmVersion;
use Crosseno\Lexicon\Language\LanguageCode;
use Crosseno\Lexicon\Manifest\LanguagePackMetadata;
use Crosseno\Lexicon\Manifest\SourceRecord;
use Crosseno\Lexicon\Serialization\DuplicateJsonKeyGuard;

final readonly class ConfigurationLoader
{
    public function load(string $path): CompilerConfiguration
    {
        $json = file_get_contents($path);
        try {
            if (!\is_string($json)) {
                throw new InvalidConfiguration('Compiler configuration could not be read.');
            }
            DuplicateJsonKeyGuard::assertNone($json);
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException|InvalidLexiconValue $exception) {
            throw new InvalidConfiguration('Compiler configuration is invalid JSON.', previous: $exception);
        }
        if (!\is_array($data) || array_is_list($data)) {
            throw new InvalidConfiguration('Compiler configuration root must be an object.');
        }
        $this->exactKeys($data, ['schema', 'version', 'metadata', 'stableKeyNamespace', 'compatibility', 'compilerVersion', 'sources', 'limits', 'failOnRejection', 'ordinalSpaceId']);
        if (($data['schema'] ?? null) !== 'crosseno-compiler-config' || ($data['version'] ?? null) !== 1) {
            throw new InvalidConfiguration('Unsupported compiler configuration schema.');
        }
        $metadata = $this->object($data, 'metadata');
        $compatibility = $this->object($data, 'compatibility');
        $limits = isset($data['limits']) ? $this->object($data, 'limits') : [];
        $this->exactKeys($metadata, ['packId', 'answerLanguage', 'dataVersion', 'normalizationProfileId', 'tokenizationProfileId', 'stableKeyAlgorithmVersion']);
        $this->exactKeys($compatibility, ['minimumCoreVersion', 'minimumLexiconVersion']);
        $this->exactKeys($limits, ['maximumSourceBytes', 'maximumRecords', 'maximumFields', 'maximumFieldBytes', 'maximumJsonDepth']);
        $sourceData = $data['sources'] ?? null;
        if (!\is_array($sourceData) || !array_is_list($sourceData) || $sourceData === []) {
            throw new InvalidConfiguration('Configuration sources must be a non-empty list.');
        }
        $base = \dirname(realpath($path) ?: $path);
        $sources = [];
        foreach ($sourceData as $source) {
            if (!\is_array($source) || array_is_list($source)) {
                throw new InvalidConfiguration('Each source must be an object.');
            }
            $this->exactKeys($source, ['path', 'format', 'provenance', 'sqliteTable']);
            $provenance = $this->object($source, 'provenance');
            $this->exactKeys($provenance, ['id', 'url', 'versionOrDate', 'sha256', 'licenseExpression', 'attribution', 'transformation', 'redistributionStatus']);
            $localPath = $this->string($source, 'path');
            if (!str_starts_with($localPath, '/')) {
                $localPath = $base . '/' . $localPath;
            }
            $sources[] = new SourceSpecification(
                $localPath,
                $this->string($source, 'format'),
                new SourceRecord(
                    $this->string($provenance, 'id'),
                    $this->string($provenance, 'url'),
                    $this->string($provenance, 'versionOrDate'),
                    $this->string($provenance, 'sha256'),
                    $this->string($provenance, 'licenseExpression'),
                    $this->string($provenance, 'attribution'),
                    $this->string($provenance, 'transformation'),
                    $this->string($provenance, 'redistributionStatus'),
                ),
                isset($source['sqliteTable']) ? $this->string($source, 'sqliteTable') : null,
            );
        }

        return new CompilerConfiguration(
            new LanguagePackMetadata(
                $this->string($metadata, 'packId'),
                new LanguageCode($this->string($metadata, 'answerLanguage')),
                $this->string($metadata, 'dataVersion'),
                $this->string($metadata, 'normalizationProfileId'),
                $this->string($metadata, 'tokenizationProfileId'),
                new StableKeyAlgorithmVersion($this->integer($metadata, 'stableKeyAlgorithmVersion', 1)),
            ),
            $this->string($data, 'stableKeyNamespace'),
            $this->string($compatibility, 'minimumCoreVersion'),
            $this->string($compatibility, 'minimumLexiconVersion'),
            $this->string($data, 'compilerVersion'),
            $sources,
            new ImportLimits(
                $this->integer($limits, 'maximumSourceBytes', 268_435_456),
                $this->integer($limits, 'maximumRecords', 2_000_000),
                $this->integer($limits, 'maximumFields', 128),
                $this->integer($limits, 'maximumFieldBytes', 1_048_576),
                $this->integer($limits, 'maximumJsonDepth', 32),
            ),
            isset($data['failOnRejection']) ? $this->boolean($data, 'failOnRejection') : false,
            isset($data['ordinalSpaceId']) ? $this->string($data, 'ordinalSpaceId') : 'catalog-v1',
        );
    }

    /**
     * @param array<mixed, mixed> $data
     * @return array<mixed, mixed>
     */
    private function object(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!\is_array($value) || array_is_list($value)) {
            throw new InvalidConfiguration($key . ' must be an object.');
        }

        return $value;
    }

    /** @param array<mixed, mixed> $data */
    private function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!\is_string($value) || $value === '') {
            throw new InvalidConfiguration($key . ' must be a non-empty string.');
        }

        return $value;
    }

    /** @param array<mixed, mixed> $data */
    private function integer(array $data, string $key, int $default): int
    {
        $value = $data[$key] ?? $default;
        if (!\is_int($value)) {
            throw new InvalidConfiguration($key . ' must be an integer.');
        }

        return $value;
    }

    /** @param array<mixed, mixed> $data */
    private function boolean(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;
        if (!\is_bool($value)) {
            throw new InvalidConfiguration($key . ' must be a boolean.');
        }

        return $value;
    }

    /**
     * @param array<mixed, mixed> $data
     * @param list<string> $allowed
     */
    private function exactKeys(array $data, array $allowed): void
    {
        foreach (array_keys($data) as $key) {
            if (!\in_array($key, $allowed, true)) {
                throw new InvalidConfiguration('Unknown configuration field: ' . $key . '.');
            }
        }
    }
}
