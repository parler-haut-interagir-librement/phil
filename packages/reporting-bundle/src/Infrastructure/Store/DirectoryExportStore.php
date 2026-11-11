<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Store;

use Override;
use Phil\ReportingBundle\Application\Contract\ExportStore;
use Phil\ReportingBundle\Application\Exception\JobUnavailable;
use Phil\ReportingBundle\Application\Report\ReportStream;
use Phil\ReportingBundle\Application\Report\StoredExport;
use RuntimeException;

/**
 * The exported files in a directory readable by its owner only, each under an identifier of its own; the name and the
 * media type travel in the `StoredExport`, never in the file's name.
 */
final readonly class DirectoryExportStore implements ExportStore
{
    private PrivateDirectory $directory;

    public function __construct(string $directory)
    {
        $this->directory = new PrivateDirectory($directory, '.export');
    }

    #[Override]
    public function keep(ReportStream $content, string $filename, string $mediaType, int $rows): StoredExport
    {
        [$id, $file] = $this->directory->create();
        $target = @fopen($file, 'x');

        if (false === $target) {
            throw new RuntimeException(\sprintf('The export cannot be written to "%s".', $file));
        }

        chmod($file, 0o600);
        $target = ReportStream::of($target);

        try {
            $size = $content->copyTo($target);
        } finally {
            $target->close();
        }

        return new StoredExport($id, $filename, $mediaType, $size, $rows);
    }

    #[Override]
    public function open(string $id): ReportStream
    {
        $file = $this->directory->file($id);
        $stream = is_file($file) ? fopen($file, 'r') : false;

        return false === $stream ? throw JobUnavailable::of($id, 'no export is kept under it') : ReportStream::of($stream);
    }

    #[Override]
    public function remove(string $id): void
    {
        $this->directory->remove($id);
    }
}
