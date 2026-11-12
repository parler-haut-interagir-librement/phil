<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Functional\App;

use Phil\RateLimitBundle\Application\Event\RateLimitExceeded;
use Phil\RateLimitBundle\Application\Event\RateLimitObserved;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Keeps the refusals and the observations the bundle dispatches.
 */
final class RecordingSubscriber
{
    /** @var list<RateLimitExceeded> */
    public array $events = [];

    /** @var list<RateLimitObserved> */
    public array $observed = [];

    #[AsEventListener(event: RateLimitExceeded::class)]
    public function record(RateLimitExceeded $event): void
    {
        $this->events[] = $event;
    }

    #[AsEventListener(event: RateLimitObserved::class)]
    public function observe(RateLimitObserved $event): void
    {
        $this->observed[] = $event;
    }
}
