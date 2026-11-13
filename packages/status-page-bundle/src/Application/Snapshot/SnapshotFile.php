<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Application\Snapshot;

/**
 * One file of a snapshot: its name, relative to where the snapshot is published, its contents and its media type.
 */
final readonly class SnapshotFile
{
    public function __construct(
        public string $path,
        public string $contents,
        public string $mediaType,
    ) {
    }
}
