<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Infrastructure\Publisher;

use Override;
use Phil\StatusPageBundle\Application\Contract\SnapshotPublisher;
use Phil\StatusPageBundle\Application\Snapshot\Snapshot;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Writes the snapshot into a directory — a mount of another machine, the root of a server that does not run the
 * application, or a folder a synchronisation copies away. Each file is written to a temporary file then renamed, so a
 * reader never sees half of one.
 */
final readonly class DirectoryPublisher implements SnapshotPublisher
{
    public function __construct(
        private string $directory,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    #[Override]
    public function publish(Snapshot $snapshot): void
    {
        foreach ($snapshot->files as $file) {
            $this->filesystem->dumpFile(rtrim($this->directory, '/').'/'.$file->path, $file->contents);
        }
    }
}
