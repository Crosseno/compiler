<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Artifact;

use Crosseno\Compiler\Exception\ArtifactFailure;

final readonly class AtomicPublisher
{
    /** @param callable(string): void $build */
    public function publish(string $destination, callable $build): void
    {
        if (file_exists($destination) || is_link($destination)) {
            throw new ArtifactFailure('Immutable output destination already exists.');
        }
        $parent = \dirname($destination);
        if (!is_dir($parent) && !mkdir($parent, 0o755, true) && !is_dir($parent)) {
            throw new ArtifactFailure('Output parent could not be created.');
        }
        $temporary = $parent . '/.' . basename($destination) . '.tmp-' . bin2hex(random_bytes(8));
        if (!mkdir($temporary, 0o700)) {
            throw new ArtifactFailure('Temporary sibling directory could not be created.');
        }
        try {
            $build($temporary);
            if (!rename($temporary, $destination)) {
                throw new ArtifactFailure('Atomic output publication failed.');
            }
        } catch (\Throwable $exception) {
            $this->remove($temporary);
            throw $exception;
        }
    }

    private function remove(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (new \FilesystemIterator($directory) as $item) {
            if (!$item instanceof \SplFileInfo) {
                throw new ArtifactFailure('Temporary directory traversal failed.');
            }
            if ($item->isDir() && !$item->isLink()) {
                $this->remove($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($directory);
    }
}
