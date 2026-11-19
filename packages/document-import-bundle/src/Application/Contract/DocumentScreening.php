<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Application\Contract;

use Phil\DocumentImport\Exception\InvalidDocument;

/**
 * A check of the bytes of a file before any reader opens it — an antivirus —, run by `DocumentImports` once the size
 * and the format are accepted.
 */
interface DocumentScreening
{
    /**
     * @param string $filename the name the file came with, for the logs and events of the check
     *
     * @throws InvalidDocument when the file is refused
     */
    public function screen(string $contents, string $filename): void;
}
