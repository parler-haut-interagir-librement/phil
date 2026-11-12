<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Profiler;

use Symfony\Contracts\Service\ResetInterface;

/**
 * What the media library did during one request, as the profiler shows it: the files read from the storage — found or
 * missing —, written and deleted, and the images rendered, with their format, type, size and time.
 */
final class MediaTrace implements ResetInterface
{
    /**
     * @var list<array{operation: string, key: string, bytes: int, found: bool}>
     */
    private array $storage = [];

    /**
     * @var list<array{format: string, type: string, bytes: int, milliseconds: float}>
     */
    private array $renders = [];

    public function stored(string $operation, string $key, int $bytes = 0, bool $found = true): void
    {
        $this->storage[] = ['operation' => $operation, 'key' => $key, 'bytes' => $bytes, 'found' => $found];
    }

    public function rendered(string $format, string $type, int $bytes, float $milliseconds): void
    {
        $this->renders[] = ['format' => $format, 'type' => $type, 'bytes' => $bytes, 'milliseconds' => $milliseconds];
    }

    /**
     * @return list<array{operation: string, key: string, bytes: int, found: bool}>
     */
    public function storage(): array
    {
        return $this->storage;
    }

    /**
     * @return list<array{format: string, type: string, bytes: int, milliseconds: float}>
     */
    public function renders(): array
    {
        return $this->renders;
    }

    public function reset(): void
    {
        $this->storage = [];
        $this->renders = [];
    }
}
