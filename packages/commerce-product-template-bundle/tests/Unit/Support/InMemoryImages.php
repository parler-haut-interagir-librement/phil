<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceProductTemplateBundle\Application\Port\ImageStore;

final class InMemoryImages implements ImageStore
{
    /** @var array<string, string> */
    public array $images = [];

    /** @var list<string> */
    public array $reads = [];

    #[Override]
    public function read(string $path): ?string
    {
        $this->reads[] = $path;

        return $this->images[$path] ?? null;
    }

    #[Override]
    public function has(string $path): bool
    {
        return isset($this->images[$path]);
    }

    #[Override]
    public function write(string $path, string $bytes): void
    {
        $this->images[$path] = $bytes;
    }
}
