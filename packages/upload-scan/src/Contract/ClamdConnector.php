<?php

declare(strict_types=1);

namespace Phil\UploadScan\Contract;

use Phil\UploadScan\Exception\ScannerUnavailable;

/**
 * How `ClamdScanner` reaches clamd: one new connection per command, closed by the scanner once answered.
 */
interface ClamdConnector
{
    /**
     * @return resource a connected stream, blocking
     *
     * @throws ScannerUnavailable when clamd cannot be reached
     */
    public function connect(): mixed;
}
