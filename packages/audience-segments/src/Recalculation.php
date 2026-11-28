<?php

declare(strict_types=1);

namespace Phil\AudienceSegments;

use Generator;
use Phil\AudienceSegments\Contract\MembershipStore;
use Phil\AudienceSegments\Event\MemberEntered;
use Phil\AudienceSegments\Event\MemberLeft;
use Phil\AudienceSegments\Exception\PortContractViolated;
use Phil\AudienceSegments\Exception\SegmentTooLarge;
use Phil\Rules\Exception\InvalidLimit;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Recalculates a dynamic segment against its stored members: walks the audience and the store side by side, both in
 * ascending order, writes who entered and who left, then publishes a `MemberEntered` or a `MemberLeft` for each and
 * records the date. Run it on a schedule; the store tells when it last ran.
 *
 * The events follow the writes: a run stopped between the two publishes them again on the next run, at least once.
 */
final readonly class Recalculation
{
    /** @var int<1, 1000> */
    private int $batchSize;

    public function __construct(
        private Segments $segments,
        private MembershipStore $store,
        private EventDispatcherInterface $dispatcher,
        private ClockInterface $clock,
        int $batchSize = 500,
    ) {
        if ($batchSize < 1 || $batchSize > 1000) {
            throw InvalidLimit::outOfRange('batchSize', $batchSize, 1, 1000);
        }

        $this->batchSize = $batchSize;
    }

    /**
     * @throws SegmentTooLarge when the audience holds more members than the bound of `Segments`
     */
    public function recalculate(Segment $segment): RecalculationReport
    {
        $audience = $this->segments->walk($segment);
        $stored = $this->stored($segment->id);
        $entered = [];
        $left = [];
        $members = 0;

        while ($audience->valid() || $stored->valid()) {
            $order = match (true) {
                !$stored->valid() => -1,
                !$audience->valid() => 1,
                default => strcmp($audience->key(), $stored->current()),
            };

            if ($order < 0) {
                if ($audience->current()) {
                    $entered[] = $audience->key();
                    ++$members;
                }

                $audience->next();
            } elseif ($order > 0) {
                $left[] = $stored->current();
                $stored->next();
            } else {
                if ($audience->current()) {
                    ++$members;
                } else {
                    $left[] = $audience->key();
                }

                $audience->next();
                $stored->next();
            }
        }

        foreach (array_chunk($entered, $this->batchSize) as $batch) {
            $this->store->add($segment->id, $batch);
        }

        foreach (array_chunk($left, $this->batchSize) as $batch) {
            $this->store->remove($segment->id, $batch);
        }

        $at = $this->clock->now();

        foreach ($entered as $member) {
            $this->dispatcher->dispatch(new MemberEntered($segment->id, $member, $at));
        }

        foreach ($left as $member) {
            $this->dispatcher->dispatch(new MemberLeft($segment->id, $member, $at));
        }

        $this->store->recalculated($segment->id, $at);

        return new RecalculationReport($segment->id, $members, \count($entered), \count($left), $at);
    }

    /**
     * @return Generator<int, string>
     */
    private function stored(string $segment): Generator
    {
        $after = null;

        do {
            $page = $this->store->members($segment, $after, $this->batchSize);

            if (\count($page) > $this->batchSize) {
                throw PortContractViolated::size('MembershipStore', \count($page), $this->batchSize);
            }

            foreach ($page as $member) {
                if (null !== $after && strcmp($after, $member) >= 0) {
                    throw PortContractViolated::order('MembershipStore', $member);
                }

                $after = $member;

                yield $member;
            }
        } while (\count($page) === $this->batchSize);
    }
}
