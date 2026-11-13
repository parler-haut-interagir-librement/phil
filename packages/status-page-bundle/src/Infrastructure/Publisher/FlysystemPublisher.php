<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Infrastructure\Publisher;

use League\Flysystem\Config;
use League\Flysystem\FilesystemOperator;
use Override;
use Phil\StatusPageBundle\Application\Contract\SnapshotPublisher;
use Phil\StatusPageBundle\Application\Snapshot\Snapshot;

/**
 * Writes the snapshot to a Flysystem storage — an object storage bucket served as a website, a server reached by
 * SFTP —, under `$prefix`, each file with its media type and, when given, its visibility.
 */
final readonly class FlysystemPublisher implements SnapshotPublisher
{
    public function __construct(
        private FilesystemOperator $storage,
        private string $prefix = '',
        private ?string $visibility = null,
    ) {
    }

    #[Override]
    public function publish(Snapshot $snapshot): void
    {
        $prefix = trim($this->prefix, '/');

        foreach ($snapshot->files as $file) {
            $config = ['mimetype' => $file->mediaType];

            if (null !== $this->visibility) {
                $config[Config::OPTION_VISIBILITY] = $this->visibility;
            }

            $this->storage->write('' === $prefix ? $file->path : $prefix.'/'.$file->path, $file->contents, $config);
        }
    }
}
