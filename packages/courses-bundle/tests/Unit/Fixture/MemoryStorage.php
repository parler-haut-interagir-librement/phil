<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Fixture;

use Override;
use Phil\CoursesBundle\Application\Contract\ContentStorage;
use RuntimeException;

/**
 * A content storage in memory.
 */
final class MemoryStorage implements ContentStorage
{
    /** @var array<string, string> */
    public array $files = [];

    /** Whether a write fails, as on a full disk. */
    public bool $full = false;

    #[Override]
    public function write(string $path, string $contents): void
    {
        if ($this->full) {
            throw new RuntimeException('The disk is full.');
        }

        $this->files[$path] = $contents;
    }

    #[Override]
    public function exists(string $path): bool
    {
        return isset($this->files[$path]);
    }

    #[Override]
    public function read(string $path): ?string
    {
        return $this->files[$path] ?? null;
    }

    #[Override]
    public function removeDirectory(string $directory): void
    {
        foreach (array_keys($this->files) as $path) {
            if (str_starts_with($path, $directory.'/')) {
                unset($this->files[$path]);
            }
        }
    }
}
