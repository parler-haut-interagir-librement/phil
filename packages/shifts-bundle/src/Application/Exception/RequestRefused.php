<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Application\Exception;

use Phil\Shifts\Exception\ShiftsException;
use RuntimeException;

/**
 * An operation the bundle refuses, with one of the constants as reason, which a screen translates.
 */
final class RequestRefused extends RuntimeException implements ShiftsException
{
    public const string UNKNOWN_SHIFT = 'shifts.unknown_shift';

    public const string UNKNOWN_OFFER = 'shifts.unknown_offer';

    public const string EXISTS = 'shifts.exists';

    public const string BUSY = 'shifts.busy';

    public const string UNTRUSTED_REQUEST = 'shifts.untrusted_request';

    public const string GUARDIAN_CONSENT = 'shifts.guardian_consent';

    public const string NO_GUARDIAN = 'shifts.no_guardian';

    public const string ABSENCES = 'shifts.absences';

    public const string NOT_STARTED = 'shifts.not_started';

    public function __construct(
        public readonly string $reason,
    ) {
        parent::__construct(\sprintf('The operation is refused: %s.', $reason));
    }
}
