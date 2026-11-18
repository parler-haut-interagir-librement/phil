<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Support;

use Closure;
use Override;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * Counts the notifications sent, running a closure during each: a send that lasts.
 */
final class SlowNotifier implements NotifierInterface
{
    public int $calls = 0;

    /**
     * @param (Closure(): void)|null $during
     */
    public function __construct(
        private readonly ?Closure $during = null,
    ) {
    }

    #[Override]
    public function send(Notification $notification, RecipientInterface ...$recipients): void
    {
        ++$this->calls;

        if ($this->during instanceof Closure) {
            ($this->during)();
        }
    }
}
