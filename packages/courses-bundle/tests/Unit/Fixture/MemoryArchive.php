<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Fixture;

use Override;
use Phil\ElearningPackages\Contract\PackageArchive;
use Phil\ElearningPackages\Exception\PackageRefused;

/**
 * A package in memory, its files given by name.
 */
final class MemoryArchive implements PackageArchive
{
    public bool $closed = false;

    /**
     * @param array<string, string> $files
     */
    public function __construct(
        private readonly array $files,
    ) {
    }

    #[Override]
    public function files(): array
    {
        return array_map(strval(...), array_keys($this->files));
    }

    #[Override]
    public function has(string $name): bool
    {
        return isset($this->files[$name]);
    }

    #[Override]
    public function read(string $name): string
    {
        return $this->files[$name] ?? throw PackageRefused::because('No such file: '.$name);
    }

    #[Override]
    public function close(): void
    {
        $this->closed = true;
    }
}
