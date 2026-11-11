<?php

declare(strict_types=1);

namespace Phil\UploadScan;

/**
 * What the application does with a scanned file: accept it, accept it flagged as not scanned, or reject it.
 */
enum Decision: string
{
    case Accept = 'accept';
    case Flag = 'flag';
    case Reject = 'reject';

    /**
     * Whether the file is kept: accepted, flagged or not.
     */
    public function keeps(): bool
    {
        return self::Reject !== $this;
    }
}
