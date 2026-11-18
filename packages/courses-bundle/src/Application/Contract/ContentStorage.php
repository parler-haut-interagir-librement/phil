<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Contract;

/**
 * Where the files of the imported packages are kept, apart from the application: the origin that serves them is not
 * the application's. Paths are relative, `/`-separated, and checked by the import before they reach the storage.
 */
interface ContentStorage
{
    public function write(string $path, string $contents): void;

    public function exists(string $path): bool;

    /**
     * The contents of the file, or null when there is none.
     */
    public function read(string $path): ?string;

    /**
     * Removes every file under that directory.
     */
    public function removeDirectory(string $directory): void;
}
