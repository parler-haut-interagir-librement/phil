<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Tests\Support;

use Phil\ParticipantSafetyInfo\EmergencyContact;
use Phil\ParticipantSafetyInfo\SafetyInfo;

final class Info
{
    public static function sample(): SafetyInfo
    {
        return new SafetyInfo(
            [new EmergencyContact('Alex Tremblay', '+1 514 555-0199', 'sibling')],
            'Peanuts: epinephrine pen in the left pocket.',
            'Wheelchair access to the main hall.',
            'Prefers to be called Sam.',
        );
    }
}
