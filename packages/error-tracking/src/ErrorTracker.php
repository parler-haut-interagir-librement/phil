<?php

declare(strict_types=1);

namespace Phil\ErrorTracking;

use Phil\ErrorTracking\Contract\CaptureThrottle;
use Phil\ErrorTracking\Contract\ErrorStore;
use Phil\ErrorTracking\Contract\GroupAlerter;
use Throwable;

/**
 * Captures an error, in this order: the sampler draws whether it is kept, the scrubber removes personal data and
 * secrets, the fingerprinter names its group, the throttle bounds the events of the group, the group is created or
 * moved to its next state and written with the event, and the alerter hears of a new group or a regression.
 *
 * The tracker keeps nothing between two captures. An exception of the store or the alerter reaches the caller, which
 * decides what a failed capture does: the code that catches errors must not throw one of its own.
 */
final readonly class ErrorTracker
{
    public function __construct(
        private EventFactory $factory,
        private ErrorStore $store,
        private Scrubber $scrubber = new Scrubber(),
        private Fingerprinter $fingerprinter = new Fingerprinter(),
        private Sampler $sampler = new Sampler(),
        private ?CaptureThrottle $throttle = null,
        private ?GroupAlerter $alerter = null,
    ) {
    }

    /**
     * @return ?Capture `null` when the sampler or the throttle left the error out
     */
    public function captureThrowable(Throwable $throwable, CaptureContext $context = new CaptureContext()): ?Capture
    {
        return $this->sampler->keep() ? $this->record($this->factory->fromThrowable($throwable, $context)) : null;
    }

    /**
     * An event built elsewhere: by `EventFactory::fromMessage()`, from a log record or a browser's report.
     *
     * @return ?Capture `null` when the sampler or the throttle left the event out
     */
    public function capture(ErrorEvent $event): ?Capture
    {
        return $this->sampler->keep() ? $this->record($event) : null;
    }

    private function record(ErrorEvent $event): ?Capture
    {
        $event = $this->scrubber->scrub($event);
        $fingerprint = $this->fingerprinter->fingerprint($event);

        if ($this->throttle instanceof CaptureThrottle && !$this->throttle->allow($fingerprint)) {
            return null;
        }

        $group = $this->store->find($fingerprint);
        $next = $group?->seen($event) ?? ErrorGroup::first($fingerprint, $event);
        $occurrence = match (true) {
            !$group instanceof ErrorGroup => Occurrence::First,
            GroupStatus::Regressed === $next->status && GroupStatus::Resolved === $group->status => Occurrence::Regression,
            default => Occurrence::Repeat,
        };

        $this->store->save($next, $event);

        if ($occurrence->alerts()) {
            $this->alerter?->alert($next, $occurrence);
        }

        return new Capture($event->id, $fingerprint, $occurrence, $next->status);
    }
}
