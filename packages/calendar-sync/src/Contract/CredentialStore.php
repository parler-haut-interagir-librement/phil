<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Contract;

use Phil\CalendarSync\CalendarConnection;
use Phil\CalendarSync\Credentials;

/**
 * Where the application keeps what opens each connection — encrypted, refreshed when a token expires —, asked once a
 * call. The package never stores what it is given.
 */
interface CredentialStore
{
    public function credentialsFor(CalendarConnection $connection): Credentials;
}
