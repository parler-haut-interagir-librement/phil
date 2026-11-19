<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Unit\Fixture;

use Override;
use Phil\ReservationBundle\Application\Contract\ReminderSender;

final class RecordingSender implements ReminderSender
{
    /** @var list<array{string, int}> */
    public array $sent = [];

    #[Override]
    public function send(string $reservationId, int $offset): void
    {
        $this->sent[] = [$reservationId, $offset];
    }
}
