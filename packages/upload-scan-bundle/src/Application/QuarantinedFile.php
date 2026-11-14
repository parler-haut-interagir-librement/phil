<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Application;

use DateTimeImmutable;

/**
 * The record of a file in the quarantine: its identifier, the name it was sent under, the signature that condemned it,
 * when it was kept and its size in bytes.
 */
final readonly class QuarantinedFile
{
    /**
     * @param int<0, max> $size
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $signature,
        public DateTimeImmutable $quarantinedAt,
        public int $size,
    ) {
    }
}
