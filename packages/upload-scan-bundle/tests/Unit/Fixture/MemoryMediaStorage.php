<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Fixture;

use Override;
use Phil\MediaBundle\Application\MediaStorage;

final class MemoryMediaStorage implements MediaStorage
{
    /** @var array<string, string> */
    public array $files = [];

    /** @var list<string> */
    public array $deleted = [];

    #[Override]
    public function write(string $key, string $contents): void
    {
        $this->files[$key] = $contents;
    }

    #[Override]
    public function read(string $key): ?string
    {
        return $this->files[$key] ?? null;
    }

    #[Override]
    public function deletePrefix(string $prefix): void
    {
        $this->deleted[] = $prefix;
    }
}
