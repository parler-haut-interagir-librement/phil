<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Contract;

/**
 * Where the files of the shipping exports wait for their download, readable by the application only.
 *
 * `LocalExportFileStore` keeps them in a directory; an application whose servers share no disk implements this over
 * its own storage, private and never served as is.
 */
interface ExportFileStore
{
    /**
     * Writes the file of the export through `$writer`, which receives an open stream, then makes it readable as a
     * whole: a failure leaves no file behind.
     *
     * @param callable(resource): void $writer
     */
    public function write(string $id, callable $writer): void;

    /**
     * @return resource|null an open stream on the file, read from the start; `null` when there is none
     */
    public function open(string $id): mixed;

    /**
     * Erases the file; erasing a file already gone does nothing.
     */
    public function delete(string $id): void;
}
