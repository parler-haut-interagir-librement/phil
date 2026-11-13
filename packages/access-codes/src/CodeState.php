<?php

declare(strict_types=1);

namespace Phil\AccessCodes;

use DateTimeImmutable;

/**
 * What a store knows of one code, read to tell why a redemption was refused: its batch, when it expires, when it was
 * redeemed and by whom, when it was revoked.
 */
final readonly class CodeState
{
    public function __construct(
        public string $batchId,
        public ?DateTimeImmutable $expiresAt = null,
        public ?DateTimeImmutable $redeemedAt = null,
        public ?string $redeemedBy = null,
        public ?DateTimeImmutable $revokedAt = null,
    ) {
    }
}
