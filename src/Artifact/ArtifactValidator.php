<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Artifact;

use Crosseno\Compiler\Exception\ArtifactFailure;
use Crosseno\Lexicon\Manifest\LanguagePackManifest;

final readonly class ArtifactValidator
{
    public function validate(string $root): LanguagePackManifest
    {
        $resolved = realpath($root);
        if ($resolved === false || !is_dir($resolved)) {
            throw new ArtifactFailure('Pack root does not exist.');
        }
        $manifestPath = $this->contained($resolved, 'manifest.json');
        $json = file_get_contents($manifestPath);
        if (!\is_string($json)) {
            throw new ArtifactFailure('Manifest could not be read.');
        }
        try {
            $manifest = LanguagePackManifest::fromJson($json, 2_097_152, 10_000, 128);
        } catch (\Throwable $exception) {
            throw new ArtifactFailure('Manifest validation failed.', previous: $exception);
        }
        foreach ($manifest->artifacts() as $artifact) {
            $path = $this->contained($resolved, $artifact->path);
            $size = filesize($path);
            $hash = hash_file('sha256', $path);
            if ($size !== $artifact->byteLength || !\is_string($hash) || !hash_equals($artifact->sha256, $hash)) {
                throw new ArtifactFailure('Artifact metadata mismatch: ' . $artifact->path . '.');
            }
        }
        $declared = ['manifest.json' => true];
        foreach ($manifest->artifacts() as $artifact) {
            $declared[$artifact->path] = true;
        }
        foreach ($this->files($resolved, $resolved) as $relative) {
            if (!isset($declared[$relative])) {
                throw new ArtifactFailure('Pack root contains an undeclared entry.');
            }
        }
        $catalog = $this->contained($resolved, 'catalog.sqlite');
        try {
            $pdo = new \PDO('sqlite:' . $catalog, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $applicationId = $this->scalar($pdo, 'PRAGMA application_id');
            $integrity = $this->scalar($pdo, 'PRAGMA integrity_check');
            $foreignKey = $this->scalar($pdo, 'PRAGMA foreign_key_check');
            $statement = $pdo->query('SELECT pack_id, data_version, answer_count, stable_key_digest FROM package_metadata WHERE singleton = 1');
            $metadata = $statement instanceof \PDOStatement ? $statement->fetch() : false;
            if ($applicationId !== 1_129_467_731 || $integrity !== 'ok' || $foreignKey !== false || !\is_array($metadata)
                || $metadata['pack_id'] !== $manifest->metadata->packId
                || $metadata['data_version'] !== $manifest->metadata->dataVersion
                || $metadata['answer_count'] !== $manifest->recordCount
                || $metadata['stable_key_digest'] !== $manifest->stableKeyDigest) {
                throw new ArtifactFailure('Catalog integrity or manifest agreement failed.');
            }
        } catch (\PDOException $exception) {
            throw new ArtifactFailure('Catalog integrity validation failed.', previous: $exception);
        }

        return $manifest;
    }

    private function contained(string $root, string $relative): string
    {
        $candidate = $root . '/' . $relative;
        if (is_link($candidate)) {
            throw new ArtifactFailure('Pack files cannot be symbolic links.');
        }
        $path = realpath($candidate);
        if ($path === false || !is_file($path) || !str_starts_with($path, $root . '/')) {
            throw new ArtifactFailure('Artifact path escapes the pack root or is missing.');
        }

        return $path;
    }

    private function scalar(\PDO $pdo, string $sql): mixed
    {
        $statement = $pdo->query($sql);

        return $statement instanceof \PDOStatement ? $statement->fetchColumn() : null;
    }

    /** @return list<string> */
    private function files(string $root, string $directory): array
    {
        $files = [];
        foreach (new \FilesystemIterator($directory) as $item) {
            if (!$item instanceof \SplFileInfo || $item->isLink()) {
                throw new ArtifactFailure('Pack traversal found an unsupported entry.');
            }
            if ($item->isDir()) {
                $files = [...$files, ...$this->files($root, $item->getPathname())];
                continue;
            }
            if (!$item->isFile()) {
                throw new ArtifactFailure('Pack traversal found a non-regular entry.');
            }
            $files[] = substr($item->getPathname(), \strlen($root) + 1);
        }

        return $files;
    }
}
