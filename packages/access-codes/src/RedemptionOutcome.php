<?php

declare(strict_types=1);

namespace Phil\AccessCodes;

/**
 * What became of a redemption.
 */
enum RedemptionOutcome: string
{
    /** The code was free, and is now this redeemer's. */
    case Redeemed = 'redeemed';

    /** No such code: mistyped, failing its check symbol, or never created. */
    case Unknown = 'unknown';

    /** The code was redeemed before, by this redeemer or another. */
    case AlreadyRedeemed = 'already_redeemed';

    /** The code's batch has expired. */
    case Expired = 'expired';

    /** The code's batch was revoked. */
    case Revoked = 'revoked';

    /** The caller spent its attempts: nothing was looked up. */
    case TooManyAttempts = 'too_many_attempts';

    public function isRedeemed(): bool
    {
        return self::Redeemed === $this;
    }
}
