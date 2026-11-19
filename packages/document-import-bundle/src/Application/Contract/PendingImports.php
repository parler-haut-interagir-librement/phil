<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Application\Contract;

use Phil\DocumentImportBundle\Application\PendingImport;

/**
 * Where a file waits for its import on a worker: the message names it by its claim, and the file, its name and its
 * format stay here, never in the message.
 */
interface PendingImports
{
    /**
     * Keeps the file, and gives the claim that takes it back: 1 to 255 letters, digits, ".", "_" or "-".
     */
    public function keep(PendingImport $import): string;

    /**
     * The file kept under the claim, `null` once it was released or when no file was kept under it.
     */
    public function take(string $claim): ?PendingImport;

    /**
     * Forgets the file kept under the claim; a claim released twice is no error.
     */
    public function release(string $claim): void;
}
