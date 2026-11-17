<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Contract;

use Phil\ElearningPackages\Exception\PackageRefused;

/**
 * A package opened for reading: the names of its files, checked to stay inside it, and their contents, each read
 * within the limits it was opened with.
 */
interface PackageArchive
{
    /**
     * @return list<string> the files of the package, directories left out
     */
    public function files(): array;

    public function has(string $name): bool;

    /**
     * @throws PackageRefused when the file is missing or past a limit
     */
    public function read(string $name): string;

    /**
     * Releases the archive; reading after it is refused.
     */
    public function close(): void;
}
