<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Export;

use Override;
use Phil\BenefitDeliverables\Contract\ExportFileStore;
use Phil\BenefitDeliverables\Exception\ExportRefused;
use Phil\BenefitDeliverables\Exception\InvalidDeliverable;
use Throwable;

/**
 * The export files in a directory of the server, `<id>.csv`, readable by the owner only (`0600`), written to a
 * temporary file of the same directory then renamed, so that a reader never sees half a file.
 *
 * The directory must stay out of the web root: a file is handed out through a signed link, never served as is.
 */
final readonly class LocalExportFileStore implements ExportFileStore
{
    public function __construct(
        private string $directory,
    ) {
    }

    #[Override]
    public function write(string $id, callable $writer): void
    {
        $path = $this->path($id);

        if (!is_dir($this->directory) || !is_writable($this->directory)) {
            throw ExportRefused::storage(\sprintf('the directory "%s" is not a writable directory.', $this->directory));
        }

        // tempnam() creates the file with the mode 0600, in the directory given when it exists.
        $temporary = tempnam($this->directory, 'export-');

        if (false === $temporary) {
            throw ExportRefused::storage('no temporary file could be created.');
        }

        try {
            $stream = fopen($temporary, 'w');

            if (false === $stream) {
                throw ExportRefused::storage('the temporary file could not be opened.');
            }

            try {
                $writer($stream);
            } finally {
                fclose($stream);
            }

            if (!chmod($temporary, 0o600) || !rename($temporary, $path)) {
                throw ExportRefused::storage('the file could not be put in place.');
            }
        } catch (Throwable $throwable) {
            if (is_file($temporary)) {
                unlink($temporary);
            }

            throw $throwable;
        }
    }

    #[Override]
    public function open(string $id): mixed
    {
        $path = $this->path($id);

        if (!is_file($path)) {
            return null;
        }

        $stream = fopen($path, 'r');

        return false === $stream ? null : $stream;
    }

    #[Override]
    public function delete(string $id): void
    {
        $path = $this->path($id);

        if (is_file($path) && !unlink($path) && is_file($path)) {
            throw ExportRefused::storage('the file could not be erased.');
        }
    }

    private function path(string $id): string
    {
        if (!ExportRecord::isId($id)) {
            throw InvalidDeliverable::because('An export is identified by 32 lower-case hexadecimal digits.');
        }

        return rtrim($this->directory, '/').'/'.$id.'.csv';
    }
}
