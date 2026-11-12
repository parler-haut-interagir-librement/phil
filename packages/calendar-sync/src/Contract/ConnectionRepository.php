<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Contract;

use Phil\CalendarSync\CalendarConnection;

/**
 * Where the application keeps the calendars its members linked.
 */
interface ConnectionRepository
{
    public function find(string $id): ?CalendarConnection;
}
