<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Application;

use Crosseno\Compiler\Artifact\ArtifactValidator;
use Crosseno\Compiler\Artifact\ArtifactWriterInterface;
use Crosseno\Compiler\Artifact\AtomicPublisher;
use Crosseno\Compiler\Artifact\CatalogWriter;
use Crosseno\Compiler\Configuration\CompilerConfiguration;
use Crosseno\Compiler\Exception\ArtifactFailure;
use Crosseno\Compiler\Import\ImporterRegistry;
use Crosseno\Compiler\Import\RawLexicalRecord;
use Crosseno\Compiler\Import\SourceInput;
use Crosseno\Compiler\Pipeline\LexicalPipeline;
use Crosseno\Lexicon\Manifest\ArtifactMetadata;
use Crosseno\Lexicon\Manifest\LanguagePackManifest;

final readonly class CompilationService
{
    /** @var list<ArtifactWriterInterface> */
    private array $additionalWriters;

    /** @param list<ArtifactWriterInterface> $additionalWriters */
    public function __construct(
        private ImporterRegistry $importers,
        private LexicalPipeline $pipeline,
        private AtomicPublisher $publisher = new AtomicPublisher(),
        private ArtifactValidator $validator = new ArtifactValidator(),
        array $additionalWriters = [],
    ) {
        $this->additionalWriters = $additionalWriters;
    }

    public function compile(CompilerConfiguration $configuration, string $destination): LanguagePackManifest
    {
        $this->assertProfiles($configuration);
        $manifest = null;
        $this->publisher->publish($destination, function (string $build) use ($configuration, &$manifest): void {
            $records = $this->records($configuration);
            $sources = array_map(static fn($source) => $source->provenance, $configuration->sources);
            $result = $this->pipeline->compile($records, $sources, $configuration->stableKeyNamespace, $configuration->metadata->answerLanguage, $configuration->failOnRejection);

            $paths = (new CatalogWriter())->write($result->catalog, $configuration, $build);
            foreach ($this->additionalWriters as $writer) {
                $paths = [...$paths, ...$writer->write($result->catalog, $configuration, $build)];
            }
            $report = [
                'schema' => 'crosseno-compilation-report',
                'version' => 1,
                'compilerVersion' => $configuration->compilerVersion,
                'configurationSha256' => hash('sha256', $this->canonicalJson($configuration->canonicalPayload())),
                'sourceRecords' => $result->inputRecords,
                'acceptedAnswers' => \count($result->catalog->answers),
                'rejections' => $result->rejectionReasons,
                'artifacts' => $this->artifactHashMap($build, $paths),
            ];
            file_put_contents($build . '/compilation-report.json', $this->canonicalJson($report));
            $paths[] = 'compilation-report.json';
            sort($paths, SORT_STRING);
            if (\count($paths) !== \count(array_unique($paths))) {
                throw new ArtifactFailure('Artifact writers returned duplicate paths.');
            }
            $artifacts = [];
            foreach ($paths as $path) {
                $artifacts[] = $this->metadata($build, $path);
            }
            $manifest = new LanguagePackManifest(
                $configuration->metadata,
                $configuration->minimumCoreVersion,
                $configuration->minimumLexiconVersion,
                $configuration->compilerVersion,
                $sources,
                \count($result->catalog->answers),
                $result->rejectionCount(),
                $artifacts,
                $result->catalog->stableKeyDigest(),
                $configuration->ordinalSpaceId,
            );
            file_put_contents($build . '/manifest.json', $manifest->toJson());
            $this->validator->validate($build);
        });
        if (!$manifest instanceof LanguagePackManifest) {
            throw new ArtifactFailure('Compilation did not produce a manifest.');
        }

        return $manifest;
    }

    /** @return iterable<RawLexicalRecord> */
    private function records(CompilerConfiguration $configuration): iterable
    {
        foreach ($configuration->sources as $source) {
            $actualHash = hash_file('sha256', $source->path);
            if (!\is_string($actualHash) || !hash_equals($source->provenance->sha256, $actualHash)) {
                throw new ArtifactFailure('Source checksum mismatch: ' . $source->provenance->id . '.');
            }
            $importer = $this->importers->forFormat($source->format, $source->sqliteTable);
            yield from $importer->import(new SourceInput($source->path, $source->provenance->id), $configuration->limits);
        }
    }

    private function assertProfiles(CompilerConfiguration $configuration): void
    {
        if ($this->pipeline->normalizationProfileId() !== $configuration->metadata->normalizationProfileId
            || $this->pipeline->tokenizationProfileId() !== $configuration->metadata->tokenizationProfileId) {
            throw new ArtifactFailure('Configured language profiles do not match the injected services.');
        }
    }

    /**
     * @param list<string> $paths
     * @return array<string, array{byteLength: int, sha256: string}>
     */
    private function artifactHashMap(string $build, array $paths): array
    {
        $result = [];
        sort($paths, SORT_STRING);
        foreach ($paths as $path) {
            $metadata = $this->metadata($build, $path);
            $result[$path] = ['byteLength' => $metadata->byteLength, 'sha256' => $metadata->sha256];
        }

        return $result;
    }

    private function metadata(string $build, string $relative): ArtifactMetadata
    {
        if ($relative === '' || str_starts_with($relative, '/') || str_contains($relative, '\\') || str_contains($relative, '..')) {
            throw new ArtifactFailure('Artifact writer returned an unsafe path.');
        }
        $path = $build . '/' . $relative;
        if (!is_file($path) || is_link($path)) {
            throw new ArtifactFailure('Artifact writer did not create a regular file: ' . $relative . '.');
        }
        $size = filesize($path);
        $hash = hash_file('sha256', $path);
        if (!\is_int($size) || !\is_string($hash)) {
            throw new ArtifactFailure('Artifact metadata could not be calculated.');
        }

        return new ArtifactMetadata($relative, $size, $hash);
    }

    /** @param array<string, mixed> $value */
    private function canonicalJson(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
