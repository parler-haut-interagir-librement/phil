<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Application\Contract;

use Phil\UploadScanBundle\Application\QuarantinedFile;

/**
 * What an operator reads of a quarantine, and the files it deletes once looked at: the records only, never the
 * contents, which no screen shows and no route serves. A quarantine that can be listed implements it beside
 * `Quarantine`.
 */
interface QuarantineInventory
{
    /**
     * The files kept, the most recent first.
     *
     * @param int<1, max> $limit
     * @param int<0, max> $offset
     *
     * @return list<QuarantinedFile>
     */
    public function files(int $limit, int $offset = 0): array;

    /**
     * The file of an identifier; `null` when there is none, or the identifier is not one the quarantine gives.
     */
    public function file(string $id): ?QuarantinedFile;

    /**
     * Deletes the file of an identifier and its record for good; `false` when there was none.
     */
    public function delete(string $id): bool;
}
