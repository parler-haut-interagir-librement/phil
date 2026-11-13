<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Unit\Fixture;

use Override;
use Phil\PagedMediaBundle\Application\Output;
use Phil\PagedMediaBundle\Application\Port\PdfStorage;

/**
 * A storage that keeps the files in memory, by path.
 */
final class RecordingStorage implements PdfStorage
{
    /** @var array<string, Output> */
    public array $stored = [];

    #[Override]
    public function store(string $path, Output $output): void
    {
        $this->stored[$path] = $output;
    }
}
