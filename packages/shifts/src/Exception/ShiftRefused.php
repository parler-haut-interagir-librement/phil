<?php

declare(strict_types=1);

namespace Phil\Shifts\Exception;

use RuntimeException;

/**
 * An operation a roster or a swap offer refuses in its state: a sign-up on a full or started shift, a role the
 * volunteer lacks, a swap taken by the one who offered it. The reason is one of the constants, which a screen
 * translates.
 */
final class ShiftRefused extends RuntimeException implements ShiftsException
{
    public const string CLOSED = 'shift.closed';

    public const string FULL = 'shift.full';

    public const string WRONG_MODE = 'shift.wrong_mode';

    public const string ROLE_REQUIRED = 'shift.role_required';

    public const string ALREADY_ON_ROSTER = 'shift.already_on_roster';

    public const string NOT_ON_ROSTER = 'shift.not_on_roster';

    public const string NOT_CONFIRMED = 'shift.not_confirmed';

    public const string CONFLICT = 'shift.conflict';

    public const string SWAP_NOT_OPEN = 'swap.not_open';

    public const string SWAP_NOT_PENDING = 'swap.not_pending';

    public const string SWAP_OWN_OFFER = 'swap.own_offer';

    public const string SWAP_NOT_YOURS = 'swap.not_yours';

    public const string SWAP_OTHER_SHIFT = 'swap.other_shift';

    public const string SWAP_STALE = 'swap.stale';

    public function __construct(
        public readonly string $reason,
    ) {
        parent::__construct(\sprintf('The operation is refused: %s.', $reason));
    }

    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
