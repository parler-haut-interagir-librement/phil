<?php

declare(strict_types=1);

namespace Phil\UploadScan\Contract;

/**
 * A scanner that can say whether its engine answers, without scanning anything: what a health check asks.
 */
interface ScannerProbe
{
    public function ping(): bool;

    /**
     * The engine and the version of its signatures, as it states them, or `null` when it does not answer.
     */
    public function version(): ?string;
}
