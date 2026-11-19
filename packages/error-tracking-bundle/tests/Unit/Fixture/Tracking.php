<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Fixture;

use Phil\ErrorTracking\Contract\GroupAlerter;
use Phil\ErrorTracking\ErrorTracker;
use Phil\ErrorTracking\EventFactory;
use Phil\ErrorTracking\Fingerprinter;
use Phil\ErrorTracking\Sampler;
use Phil\ErrorTracking\Scrubber;
use Phil\ErrorTrackingBundle\Application\SafeCapture;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * A tracker over a memory store, and the safe capture around it.
 */
final readonly class Tracking
{
    public MemoryStore $store;

    public EventFactory $factory;

    public ErrorTracker $tracker;

    public SafeCapture $capture;

    /**
     * @param list<class-string<Throwable>> $ignored
     */
    public function __construct(array $ignored = [], ?LoggerInterface $logger = null, ?GroupAlerter $alerter = null)
    {
        $this->store = new MemoryStore();
        $this->factory = new EventFactory('/srv/project-under-test', new FrozenClock(), '1.0.0', 'test');
        $this->tracker = new ErrorTracker($this->factory, $this->store, new Scrubber(), new Fingerprinter(), new Sampler(), null, $alerter);
        $this->capture = new SafeCapture($this->tracker, $ignored, $logger);
    }
}
