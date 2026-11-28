<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo;

/**
 * What a person is to an event, as the application decides it.
 */
enum Role: string
{
    /** Designated to read the safety information of the participants during the access window. */
    case Responsible = 'responsible';

    /** On the team of the event: reads only by breaking the glass, with a reason, and the responsible people are told. */
    case Staff = 'staff';

    /** Anybody else: reads nothing but their own. */
    case None = 'none';
}
