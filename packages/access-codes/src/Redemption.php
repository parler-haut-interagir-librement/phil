<?php

declare(strict_types=1);

namespace Phil\AccessCodes;

use DateTimeImmutable;

/**
 * The answer to a redemption: its outcome, the batch of the code when one was found — what the application unlocks
 * from —, when it was redeemed, and when the caller may try again after spending its attempts.
 */
final readonly class Redemption
{
    private function __construct(
        public RedemptionOutcome $outcome,
        public ?string $batchId = null,
        public ?DateTimeImmutable $redeemedAt = null,
        public ?DateTimeImmutable $retryAfter = null,
    ) {
    }

    public static function redeemed(string $batchId, DateTimeImmutable $at): self
    {
        return new self(RedemptionOutcome::Redeemed, $batchId, $at);
    }

    public static function refused(RedemptionOutcome $outcome, ?string $batchId = null, ?DateTimeImmutable $redeemedAt = null): self
    {
        return new self($outcome, $batchId, $redeemedAt);
    }

    public static function tooManyAttempts(DateTimeImmutable $retryAfter): self
    {
        return new self(RedemptionOutcome::TooManyAttempts, retryAfter: $retryAfter);
    }

    public function isRedeemed(): bool
    {
        return $this->outcome->isRedeemed();
    }
}
