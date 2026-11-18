<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Fixture;

use Closure;
use DateTimeImmutable;
use Override;
use Phil\PrivacyBundle\Application\Contract\ExportArchive;

final class MemoryExportArchive implements ExportArchive
{
    /**
     * @var array<string, array{string, DateTimeImmutable}>
     */
    public array $files = [];

    /**
     * Runs once a file is put: a write that lasts, or during which another worker takes the lock over.
     *
     * @var (Closure(): void)|null
     */
    public ?Closure $onPut = null;

    #[Override]
    public function put(string $reference, string $content, DateTimeImmutable $expiresAt): void
    {
        $this->files[$reference] = [$content, $expiresAt];

        if ($this->onPut instanceof Closure) {
            ($this->onPut)();
        }
    }

    #[Override]
    public function get(string $reference): ?string
    {
        return $this->files[$reference][0] ?? null;
    }

    #[Override]
    public function purgeExpired(DateTimeImmutable $now): int
    {
        $before = \count($this->files);
        $this->files = array_filter($this->files, static fn (array $file): bool => $file[1] > $now);

        return $before - \count($this->files);
    }
}
