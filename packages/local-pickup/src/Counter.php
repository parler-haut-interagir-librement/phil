<?php

declare(strict_types=1);

namespace Phil\LocalPickup;

use Override;
use Phil\LocalPickup\Contract\HandOverLog;
use Phil\LocalPickup\Contract\PickupDesk;
use Phil\LocalPickup\Contract\PickupRepository;
use Phil\LocalPickup\Exception\CodeLocked;
use Phil\LocalPickup\Exception\CodeRefused;
use Phil\LocalPickup\Exception\PickupRefused;
use Phil\LocalPickup\Exception\UnknownPickup;
use Phil\LocalPickup\Internal\Check;

/**
 * The counter: hands a ready order over against its code, once.
 *
 * A wrong code is counted on the pickup; at the limit, the code is locked until Pickups::resend() sends a new one, so
 * that the forty bits of a code cannot be tried through. A right code is spent at once, and every hand-over is written
 * to the journal — with the collector's name when someone collects in the customer's place.
 *
 * The service keeps no state between calls.
 */
final readonly class Counter implements PickupDesk
{
    public const int DEFAULT_MAX_ATTEMPTS = 5;

    public function __construct(
        private PickupRepository $pickups,
        private PickupCodes $codes,
        private HandOverLog $log,
        private int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
    ) {
        Check::range('attempt limit', $maxAttempts, 1, 20);
    }

    #[Override]
    public function handOver(HandOverRequest $request): HandOver
    {
        $pickup = $this->pickups->find($request->order) ?? throw UnknownPickup::forOrder($request->order);

        if (PickupState::Ready !== $pickup->state) {
            throw PickupRefused::state($pickup->order, $pickup->state, 'handed over');
        }

        if ($pickup->failedAttempts >= $this->maxAttempts) {
            throw CodeLocked::forOrder($pickup->order);
        }

        if (null === $pickup->codeDigest) {
            throw PickupRefused::noCode($pickup->order);
        }

        if (!$this->codes->matches($pickup->order, $request->code, $pickup->codeDigest)) {
            $attempts = $pickup->failAttempt();
            $this->pickups->save($pickup);

            throw $attempts >= $this->maxAttempts ? CodeLocked::forOrder($pickup->order) : CodeRefused::forOrder($pickup->order, $this->maxAttempts - $attempts);
        }

        $handOver = new HandOver($pickup->order, $pickup->customer, $pickup->point->code, $request->station, $request->staff, $request->at, $request->collector, null === $request->collector ? null : $pickup->collector);
        $pickup->pickUp($request->at);
        $this->pickups->save($pickup);
        $this->log->record($handOver);

        return $handOver;
    }
}
