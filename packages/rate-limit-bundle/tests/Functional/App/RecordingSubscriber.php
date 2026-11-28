<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Functional\App;

use Phil\RateLimitBundle\Application\Event\RateLimitExceeded;
use Phil\RateLimitBundle\Application\Event\RateLimitObserved;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\RateLimiter\Event\RateLimitExceededEvent;

/**
 * Keeps the refusals and the observations the bundle dispatches, and Symfony's events of the refusals.
 */
final class RecordingSubscriber
{
    /** @var list<RateLimitExceeded> */
    public array $events = [];

    /** @var list<RateLimitObserved> */
    public array $observed = [];

    /** @var list<RateLimitExceededEvent> */
    public array $symfony = [];

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

    #[AsEventListener(event: RateLimitExceededEvent::class)]
    public function recordSymfony(RateLimitExceededEvent $event): void
    {
        $this->symfony[] = $event;
    }
}
