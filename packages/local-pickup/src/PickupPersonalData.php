<?php

declare(strict_types=1);

namespace Phil\LocalPickup;

use DateTimeInterface;
use Phil\LocalPickup\Contract\HandOverLog;
use Phil\LocalPickup\Contract\PickupRepository;
use Phil\LocalPickup\Internal\Check;

/**
 * A customer's pickup data for an access or an erasure request: the names of the people designated to collect, and of
 * those who collected, in the pickups and in the journal. An application's privacy module calls it from its own
 * exporter and eraser, under its module name.
 *
 * Erasing blanks the names and keeps the pickups and the journal entries: what was handed over, where and when stays
 * for the accounts. It is idempotent.
 */
final readonly class PickupPersonalData
{
    public const string STORE_PICKUPS = 'pickups';

    public const string STORE_HAND_OVERS = 'pickup_hand_overs';

    public function __construct(
        private PickupRepository $pickups,
        private HandOverLog $log,
    ) {
    }

    /**
     * @return array{pickups?: list<array<string, mixed>>, hand_overs?: list<array<string, mixed>>}
     */
    public function export(string $customer): array
    {
        Check::opaque('customer', $customer);
        $export = [];
        $pickups = $this->pickups->ofCustomer($customer);
        $handOvers = $this->log->ofCustomer($customer);

        if ([] !== $pickups) {
            $export['pickups'] = array_map(static fn (Pickup $pickup): array => [
                'order' => $pickup->order,
                'point' => $pickup->point->name,
                'slot' => $pickup->slot->start->format(DateTimeInterface::ATOM),
                'state' => $pickup->state->value,
                'collector' => $pickup->collector,
            ], $pickups);
        }

        if ([] !== $handOvers) {
            $export['hand_overs'] = array_map(static fn (HandOver $entry): array => [
                'order' => $entry->order,
                'at' => $entry->at->format(DateTimeInterface::ATOM),
                'collector' => $entry->collector,
            ], $handOvers);
        }

        return $export;
    }

    /**
     * Blanks the collectors' names, and gives each store with what was done to it.
     *
     * @return array{pickups: 'redacted', pickup_hand_overs: 'redacted'}
     */
    public function erase(string $customer): array
    {
        Check::opaque('customer', $customer);

        foreach ($this->pickups->ofCustomer($customer) as $pickup) {
            if (null !== $pickup->collector) {
                $pickup->eraseCollector();
                $this->pickups->save($pickup);
            }
        }

        $this->log->eraseCollectors($customer);

        return [self::STORE_PICKUPS => 'redacted', self::STORE_HAND_OVERS => 'redacted'];
    }
}
