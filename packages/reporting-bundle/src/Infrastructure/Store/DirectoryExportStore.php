<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Store;

use Override;
use Phil\ReportingBundle\Application\Contract\ExportStore;
use Phil\ReportingBundle\Application\Exception\ExportFailed;
use Phil\ReportingBundle\Application\Exception\JobUnavailable;
use Phil\ReportingBundle\Application\Report\ReportStream;
use Phil\ReportingBundle\Application\Report\StoredExport;

/**
 * The exported files in a directory readable by its owner only, each under an identifier of its own; the name and the
 * media type travel in the `StoredExport`, never in the file's name.
 *
 * An export is readable by its owner from the moment its file exists, and takes its name once it is whole: a file cut
 * short is never one of the store. A directory open to other users is narrowed to its owner, or refused when
 * `tightenOpenDirectory` is false.
 */
final readonly class DirectoryExportStore implements ExportStore
{
    private PrivateDirectory $directory;

    public function __construct(string $directory, bool $tightenOpenDirectory = true)
    {
        $this->directory = new PrivateDirectory($directory, '.export', $tightenOpenDirectory);
    }

    #[Override]
    public function keep(ReportStream $content, string $filename, string $mediaType, int $rows): StoredExport
    {
        [$id, $size] = $this->directory->keep($content, static fn (string $file): ExportFailed => ExportFailed::because(\sprintf('"%s" cannot be created', $file)));

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
