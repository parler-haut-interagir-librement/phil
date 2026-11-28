<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Contract;

use Phil\ParticipantSafetyInfo\AccessWindow;

/**
 * The access window of each event, read from the application's calendar.
 */
interface AccessWindows
{
    /**
     * The window of the event, `null` when the event has none: then nobody submits or reads for it.
     */
    public function windowOf(string $event): ?AccessWindow;
}
