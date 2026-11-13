<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Tests\Unit\Fixture;

use Override;
use Phil\AccessLinks\Contract\ReminderSender;
use Phil\AccessLinks\IssuedLink;

/**
 * A sender that keeps the links it was given.
 */
final class RecordingSender implements ReminderSender
{
    /** @var list<IssuedLink> */
    public array $sent = [];

    #[Override]
    public function sendReminder(IssuedLink $link): void
    {
        $this->sent[] = $link;
    }
}
