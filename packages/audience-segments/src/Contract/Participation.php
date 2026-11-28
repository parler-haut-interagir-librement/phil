<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Contract;

/**
 * What the events of an application know of a member: tickets bought, dates attended, selections made by organisers.
 */
interface Participation
{
    /**
     * Whether the member holds a ticket of the event, of one of the ticket types when any is given.
     *
     * @param list<string> $ticketTypes
     */
    public function holdsTicket(string $member, string $event, array $ticketTypes): bool;

    /**
     * Whether the member took part in the date, the occurrence of an event.
     */
    public function attended(string $member, string $occurrence): bool;

    /**
     * Whether an organiser chose the member in the selection, such as the speakers or the volunteers of an edition.
     */
    public function inSelection(string $member, string $selection): bool;
}
