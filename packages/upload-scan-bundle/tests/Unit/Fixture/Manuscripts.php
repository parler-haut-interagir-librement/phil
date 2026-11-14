<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Fixture;

use Override;
use Phil\UploadScanBundle\Application\Contract\RescanTarget;
use Phil\UploadScanBundle\Application\Inspection;
use RuntimeException;

/**
 * A rescan target of the tests: files by identifier, and the inspections it was given.
 */
final class Manuscripts implements RescanTarget
{
    /** @var array<string, Inspection> */
    public array $resolved = [];

    /**
     * @param array<string, string> $files the contents by identifier
     */
    public function __construct(
        private readonly array $files = [],
        private readonly string $name = 'manuscripts',
        private readonly bool $failsToResolve = false,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function open(string $fileId): mixed
    {
        if (!isset($this->files[$fileId])) {
            return null;
        }

        $stream = fopen('php://memory', 'r+');
        \assert(false !== $stream);
        fwrite($stream, $this->files[$fileId]);
        rewind($stream);

        return $stream;
    }

    #[Override]
    public function resolve(string $fileId, Inspection $inspection): void
    {
        if ($this->failsToResolve) {
            throw new RuntimeException('The target failed.');
        }

        $this->resolved[$fileId] = $inspection;
    }
}
