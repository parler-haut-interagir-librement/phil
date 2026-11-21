<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use Override;
use Phil\NotificationsBundle\Application\Contract\DigestUnsubscriber;

/**
 * Keeps the recipients whose digest was stopped, in order.
 */
final class RecordingUnsubscriber implements DigestUnsubscriber
{
    /** @var list<string> */
    public array $unsubscribed = [];

    #[Override]
    public function unsubscribe(string $recipient): void
    {
        $this->unsubscribed[] = $recipient;
    }
}
