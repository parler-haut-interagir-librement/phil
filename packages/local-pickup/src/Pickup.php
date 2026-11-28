<?php

declare(strict_types=1);

namespace Phil\LocalPickup;

use DateTimeImmutable;
use DateTimeZone;
use Phil\LocalPickup\Exception\InvalidPickup;
use Phil\LocalPickup\Exception\PickupRefused;
use Phil\LocalPickup\Internal\Check;
use Phil\Reservation\Interval;

/**
 * The pickup of one order: the point agreed, the slot booked, where it stands, and the digest of its code.
 *
 * It keeps the keyed digest of its code, never the code. Its store keeps what toArray() gives and rebuilds it with
 * fromArray(). Instants are kept to the second and compared as timestamps.
 */
final class Pickup
{
    /** The last instant a stored row may hold: the end of the year 9999. */
    public const int LAST_MOMENT = 253_402_300_799;

    private function __construct(
        public readonly string $order,
        public readonly string $customer,
        public readonly AgreedPickupPoint $point,
        public readonly Interval $slot,
        public readonly DateTimeImmutable $placedAt,
        public private(set) PickupState $state,
        public private(set) ?string $collector,
        public private(set) ?DateTimeImmutable $readyAt,
        public private(set) ?DateTimeImmutable $holdUntil,
        public private(set) ?string $codeDigest,
        public private(set) int $failedAttempts,
        public private(set) bool $notified,
        public private(set) ?DateTimeImmutable $arrivedAt,
        public private(set) ?ArrivalSource $arrival,
        public private(set) ?DateTimeImmutable $endedAt,
    ) {
    }

    /**
     * A pickup being prepared, for an order just placed.
     *
     * @param string  $customer  the opaque identifier of the customer, as the application's privacy requests name them
     * @param ?string $collector the name of the person the customer designates to collect in their place
     */
    public static function place(string $order, string $customer, AgreedPickupPoint $point, Interval $slot, DateTimeImmutable $at, ?string $collector = null): self
    {
        return new self(
            Check::order($order),
            Check::opaque('customer', $customer),
            $point,
            $slot,
            $at,
            PickupState::Preparing,
            null === $collector ? null : Check::text('collector name', $collector),
            null,
            null,
            null,
            0,
            false,
            null,
            null,
            null,
        );
    }

    /**
     * Prepared and waiting at the counter. It is held for the agreed hold period, counted from its slot's start, or
     * from now when it is ready later than that.
     */
    public function ready(DateTimeImmutable $at): void
    {
        $this->expect(PickupState::Preparing, 'made ready');
        $this->state = PickupState::Ready;
        $this->readyAt = $at;
        $this->holdUntil = new DateTimeImmutable('@'.(max($at->getTimestamp(), $this->slot->from) + $this->point->holdHours * 3_600))->setTimezone($this->point->zone());
        $this->notified = false;
    }

    /**
     * Keeps the digest of a code just issued; the wrong attempts start again from zero.
     */
    public function issueCode(string $digest): void
    {
        $this->expect(PickupState::Ready, 'given a code');
        $this->codeDigest = self::digest($digest);
        $this->failedAttempts = 0;
    }

    /**
     * The "ready for pickup" notice was sent with the current code.
     */
    public function markNotified(): void
    {
        $this->expect(PickupState::Ready, 'notified');
        $this->notified = true;
    }

    /**
     * Forgets the code, so that a new one is issued and sent: after it was locked, or lost.
     */
    public function resetCode(): void
    {
        $this->expect(PickupState::Ready, 'given a new code');
        $this->codeDigest = null;
        $this->failedAttempts = 0;
        $this->notified = false;
    }

    /**
     * Counts a wrong code given at the counter, and gives the count.
     */
    public function failAttempt(): int
    {
        $this->expect(PickupState::Ready, 'tried');

        return ++$this->failedAttempts;
    }

    /**
     * Collected: the code is spent.
     */
    public function pickUp(DateTimeImmutable $at): void
    {
        $this->expect(PickupState::Ready, 'picked up');
        $this->state = PickupState::PickedUp;
        $this->endedAt = $at;
        $this->codeDigest = null;
    }

    /**
     * Whether its hold period has ended at that moment.
     */
    public function overdue(DateTimeImmutable $at): bool
    {
        return PickupState::Ready === $this->state && $this->holdUntil instanceof DateTimeImmutable && $at->getTimestamp() >= $this->holdUntil->getTimestamp();
    }

    /**
     * Not collected within its hold period: the code is spent.
     */
    public function expire(DateTimeImmutable $at): void
    {
        $this->expect(PickupState::Ready, 'expired');

        if (!$this->overdue($at)) {
            throw PickupRefused::holdRunning($this->order);
        }

        $this->state = PickupState::Expired;
        $this->endedAt = $at;
        $this->codeDigest = null;
    }

    /**
     * Records the customer's arrival, and says whether it is the first signal: the automatic one and the customer's own
     * "I am here" may both arrive, and only the first one counts.
     */
    public function arrive(ArrivalSource $source, DateTimeImmutable $at): bool
    {
        if (!$this->state->holdsSlot()) {
            throw PickupRefused::state($this->order, $this->state, 'announced');
        }

        if ($this->arrivedAt instanceof DateTimeImmutable) {
            return false;
        }

        $this->arrivedAt = $at;
        $this->arrival = $source;

        return true;
    }

    /**
     * Forgets the name of the designated collector: an erasure of the customer's personal data.
     */
    public function eraseCollector(): void
    {
        $this->collector = null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'order' => $this->order,
            'customer' => $this->customer,
            'point' => $this->point->toArray(),
            'slot' => [$this->slot->from, $this->slot->until],
            'placed_at' => $this->placedAt->getTimestamp(),
            'state' => $this->state->value,
            'collector' => $this->collector,
            'ready_at' => $this->readyAt?->getTimestamp(),
            'hold_until' => $this->holdUntil?->getTimestamp(),
            'code_digest' => $this->codeDigest,
            'failed_attempts' => $this->failedAttempts,
            'notified' => $this->notified,
            'arrived_at' => $this->arrivedAt?->getTimestamp(),
            'arrival' => $this->arrival?->value,
            'ended_at' => $this->endedAt?->getTimestamp(),
        ];
    }

    /**
     * @param array<array-key, mixed> $data what toArray() gave
     *
     * @throws InvalidPickup when a field is missing, of the wrong type or out of bounds
     */
    public static function fromArray(array $data): self
    {
        $point = $data['point'] ?? null;

        if (!\is_array($point)) {
            throw InvalidPickup::because('pickup point of the pickup', 'is an array');
        }

        $point = AgreedPickupPoint::fromArray($point);
        $zone = $point->zone();
        $slot = $data['slot'] ?? null;

        if (!\is_array($slot) || !array_is_list($slot) || 2 !== \count($slot) || !\is_int($slot[0]) || !\is_int($slot[1]) || $slot[0] < 0 || $slot[1] <= $slot[0] || $slot[1] > self::LAST_MOMENT) {
            throw InvalidPickup::because('slot of the pickup', 'is a start and a later end, as timestamps');
        }

        $state = PickupState::tryFrom(self::string($data, 'state'));

        if (null === $state) {
            throw InvalidPickup::because('state of the pickup', 'is one of PickupState');
        }

        $arrival = $data['arrival'] ?? null;

        if (null !== $arrival && (!\is_string($arrival) || null === ArrivalSource::tryFrom($arrival))) {
            throw InvalidPickup::because('arrival of the pickup', 'is one of ArrivalSource');
        }

        $digest = self::optionalString($data, 'code_digest');
        $collector = self::optionalString($data, 'collector');
        $attempts = $data['failed_attempts'] ?? null;
        $notified = $data['notified'] ?? null;

        if (!\is_int($attempts) || $attempts < 0) {
            throw InvalidPickup::because('failed attempts of the pickup', 'is a natural number');
        }

        if (!\is_bool($notified)) {
            throw InvalidPickup::because('notice flag of the pickup', 'is a boolean');
        }

        $readyAt = self::moment($data, 'ready_at', $zone);
        $arrivedAt = self::moment($data, 'arrived_at', $zone);

        if ((!$arrivedAt instanceof DateTimeImmutable) !== (null === $arrival)) {
            throw InvalidPickup::because('arrival of the pickup', 'has both a moment and a source, or neither');
        }

        $pickup = new self(
            Check::order(self::string($data, 'order')),
            Check::opaque('customer', self::string($data, 'customer')),
            $point,
            Interval::ofTimestamps($slot[0], $slot[1], $zone),
            self::moment($data, 'placed_at', $zone) ?? throw InvalidPickup::because('placing moment of the pickup', 'is a timestamp'),
            $state,
            null === $collector ? null : Check::text('collector name', $collector),
            $readyAt,
            self::moment($data, 'hold_until', $zone),
            null === $digest ? null : self::digest($digest),
            $attempts,
            $notified,
            $arrivedAt,
            null === $arrival ? null : ArrivalSource::from($arrival),
            self::moment($data, 'ended_at', $zone),
        );

        $pickup->consistent();

        return $pickup;
    }

    private function expect(PickupState $state, string $action): void
    {
        if ($state !== $this->state) {
            throw PickupRefused::state($this->order, $this->state, $action);
        }
    }

    /**
     * Refuses a stored row whose moments do not match its state.
     */
    private function consistent(): void
    {
        $ready = PickupState::Preparing !== $this->state;
        $ended = !$this->state->holdsSlot();

        if ($ready !== ($this->readyAt instanceof DateTimeImmutable) || $ready !== ($this->holdUntil instanceof DateTimeImmutable)) {
            throw InvalidPickup::because('pickup', 'has a ready moment and a hold end once it is ready, and only then');
        }

        if ($ended !== ($this->endedAt instanceof DateTimeImmutable)) {
            throw InvalidPickup::because('pickup', 'has an end moment once it is picked up or expired, and only then');
        }

        if (PickupState::Ready !== $this->state && null !== $this->codeDigest) {
            throw InvalidPickup::because('pickup', 'has a code only while it is ready');
        }

        if (!$ready && ($this->notified || 0 !== $this->failedAttempts)) {
            throw InvalidPickup::because('pickup', 'has a notice or attempts only once it is ready');
        }
    }

    private static function digest(string $digest): string
    {
        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $digest)) {
            throw InvalidPickup::because('code digest', 'is 64 lowercase hexadecimal digits');
        }

        return $digest;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (!\is_string($value)) {
            throw InvalidPickup::because('field "'.$key.'" of the pickup', 'is a string');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (null !== $value && !\is_string($value)) {
            throw InvalidPickup::because('field "'.$key.'" of the pickup', 'is a string or null');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function moment(array $data, string $key, DateTimeZone $zone): ?DateTimeImmutable
    {
        $value = $data[$key] ?? null;

        if (null === $value) {
            return null;
        }

        if (!\is_int($value) || $value < 0 || $value > self::LAST_MOMENT) {
            throw InvalidPickup::because('field "'.$key.'" of the pickup', 'is a timestamp or null');
        }

        return new DateTimeImmutable('@'.$value)->setTimezone($zone);
    }
}
