<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Configuration;

use Crosseno\Compiler\Exception\InvalidConfiguration;
use Crosseno\Compiler\Import\ImportLimits;
use Crosseno\Lexicon\Language\LanguageCode;
use Crosseno\Lexicon\Manifest\LanguagePackMetadata;

final readonly class CompilerConfiguration
{
    /** @param non-empty-list<SourceSpecification> $sources */
    public function __construct(
        public LanguagePackMetadata $metadata,
        public string $stableKeyNamespace,
        public string $minimumCoreVersion,
        public string $minimumLexiconVersion,
        public string $compilerVersion,
        public array $sources,
        public ImportLimits $limits = new ImportLimits(),
        public bool $failOnRejection = false,
        public string $ordinalSpaceId = 'catalog-v1',
    ) {
        if ($sources === [] || !array_is_list($sources)) {
            throw new InvalidConfiguration('Compiler requires at least one source.');
        }
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,62}$/D', $stableKeyNamespace) !== 1) {
            throw new InvalidConfiguration('Stable-key namespace is invalid.');
        }
        foreach ([$minimumCoreVersion, $minimumLexiconVersion, $compilerVersion] as $version) {
            if (preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:[-+][0-9A-Za-z.-]+)?$/D', $version) !== 1) {
                throw new InvalidConfiguration('Compatibility and compiler versions must be semantic versions.');
            }
        }
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/D', $ordinalSpaceId) !== 1) {
            throw new InvalidConfiguration('Ordinal-space ID is invalid.');
        }
    }

    /** @return array<string, mixed> Deterministic semantics only; local paths are intentionally absent. */
    public function canonicalPayload(): array
    {
        $sources = [];
        foreach ($this->sources as $source) {
            $sources[] = [
                'id' => $source->provenance->id,
                'format' => $source->format,
                'sqliteTable' => $source->sqliteTable,
                'url' => $source->provenance->url,
                'versionOrDate' => $source->provenance->versionOrDate,
                'sha256' => $source->provenance->sha256,
                'licenseExpression' => $source->provenance->licenseExpression,
                'attribution' => $source->provenance->attribution,
                'transformation' => $source->provenance->transformation,
                'redistributionStatus' => $source->provenance->redistributionStatus,
            ];
        }
        usort($sources, static fn(array $left, array $right): int => strcmp($left['id'], $right['id']));

        return [
            'metadata' => [
                'packId' => $this->metadata->packId,
                'answerLanguage' => $this->metadata->answerLanguage->value,
                'dataVersion' => $this->metadata->dataVersion,
                'normalizationProfileId' => $this->metadata->normalizationProfileId,
                'tokenizationProfileId' => $this->metadata->tokenizationProfileId,
                'stableKeyAlgorithmVersion' => $this->metadata->stableKeyAlgorithmVersion->major,
            ],
            'stableKeyNamespace' => $this->stableKeyNamespace,
            'compatibility' => ['core' => $this->minimumCoreVersion, 'lexicon' => $this->minimumLexiconVersion],
            'compilerVersion' => $this->compilerVersion,
            'sources' => $sources,
            'limits' => get_object_vars($this->limits),
            'failOnRejection' => $this->failOnRejection,
            'ordinalSpaceId' => $this->ordinalSpaceId,
        ];
    }
}
