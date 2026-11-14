<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Contract;

/**
 * Who is an established member — of a trust level the application chooses —, whom the restrictions of an incident
 * configured to spare them leave free. The member is named by the application's own identifier, as a string.
 */
interface EstablishedMembers
{
    public function isEstablished(string $member): bool;
}
