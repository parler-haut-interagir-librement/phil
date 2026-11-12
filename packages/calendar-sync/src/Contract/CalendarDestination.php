<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Contract;

use Phil\CalendarSync\CalendarConnection;
use Phil\CalendarSync\Credentials;
use Phil\CalendarSync\Exception\ProviderFailed;
use Phil\CalendarSync\OutboundEvent;

/**
 * An adapter that writes the site's events into a calendar of someone's. Writing an event again, by the same
 * identifier, replaces it; removing an event that is not there is not an error.
 */
interface CalendarDestination
{
    public function name(): string;

    /**
     * @throws ProviderFailed
     */
    public function put(CalendarConnection $connection, Credentials $credentials, OutboundEvent $event): void;

    /**
     * @throws ProviderFailed
     */
    public function remove(CalendarConnection $connection, Credentials $credentials, string $uid): void;
}
