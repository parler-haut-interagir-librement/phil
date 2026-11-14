<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Fixture;

use Override;
use Phil\UploadScanBundle\Application\Contract\QuarantineInventory;
use Phil\UploadScanBundle\Application\QuarantinedFile;

/**
 * An inventory of the tests: records kept in memory, listed in the order given, the most recent first; the identifiers
 * named undeletable are never deleted, and each listing is counted.
 */
final class MemoryInventory implements QuarantineInventory
{
    /** @var array<string, QuarantinedFile> */
    public array $files = [];

    public int $listings = 0;

    /**
     * @param list<QuarantinedFile> $files
     * @param list<string>          $undeletable
     */
    public function __construct(array $files = [], private readonly array $undeletable = [])
    {
        foreach ($files as $file) {
            $this->files[$file->id] = $file;
        }
    }

    #[Override]
    public function files(int $limit, int $offset = 0): array
    {
        ++$this->listings;

        return \array_slice(array_values($this->files), $offset, $limit);
    }

    #[Override]
    public function file(string $id): ?QuarantinedFile
    {
        return $this->files[$id] ?? null;
    }

    #[Override]
    public function delete(string $id): bool
    {
        if (!isset($this->files[$id]) || \in_array($id, $this->undeletable, true)) {
            return false;
        }

        unset($this->files[$id]);

        return true;
    }
}
