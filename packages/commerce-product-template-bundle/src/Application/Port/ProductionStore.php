<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Application\Port;

use RuntimeException;

/**
 * Where print files and production orders are written, by paths relative to the store: segments of letters, digits,
 * ".", "_" and "-", never "." nor "..". They hold what buyers typed, so the store is private: never served as it is.
 *
 * `Infrastructure\Filesystem\DirectoryProductionStore` keeps them in a directory readable by its owner only.
 */
interface ProductionStore
{
    /**
     * The bytes of the file, or `null` when the store has none at that path.
     */
    public function read(string $path): ?string;

    public function has(string $path): bool;

    /**
     * Writes the file whole, replacing the one at that path; a reader never sees half of it.
     *
     * @throws RuntimeException when it cannot be written
     */
    public function write(string $path, string $bytes): void;
}
