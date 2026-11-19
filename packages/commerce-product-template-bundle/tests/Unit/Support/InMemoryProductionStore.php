<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceProductTemplateBundle\Application\Port\ProductionStore;

final class InMemoryProductionStore implements ProductionStore
{
    /** @var array<string, string> */
    public array $files = [];

    /** @var list<string> */
    public array $writes = [];

    #[Override]
    public function read(string $path): ?string
    {
        return $this->files[$path] ?? null;
    }

    #[Override]
    public function has(string $path): bool
    {
        return isset($this->files[$path]);
    }

    #[Override]
    public function write(string $path, string $bytes): void
    {
        $this->writes[] = $path;
        $this->files[$path] = $bytes;
    }
}
