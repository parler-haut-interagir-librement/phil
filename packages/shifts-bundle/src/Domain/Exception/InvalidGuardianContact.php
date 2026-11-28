<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Domain\Exception;

use InvalidArgumentException;
use Phil\Shifts\Exception\ShiftsException;

/**
 * The contact of a guardian built with a value outside its bounds, with one of the constants as reason, which a form
 * translates. The message names the field, never its value.
 */
final class InvalidGuardianContact extends InvalidArgumentException implements ShiftsException
{
    public const string NAME = 'shifts.guardian.invalid_name';

    public const string PHONE = 'shifts.guardian.invalid_phone';

    public const string EMAIL = 'shifts.guardian.invalid_email';

    public const string NO_WAY_TO_REACH = 'shifts.guardian.no_way_to_reach';

    public const string PERSON = 'shifts.guardian.invalid_person';

    public function __construct(
        public readonly string $reason,
    ) {
        parent::__construct(\sprintf('The contact of the guardian is refused: %s.', $reason));
    }
}
