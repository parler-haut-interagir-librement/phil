<?php

declare(strict_types=1);

namespace Phil\Stockroom;

use Phil\Stockroom\Contract\Balances;
use Phil\Stockroom\Contract\LoadLists;
use Phil\Stockroom\Exception\InvalidStock;
use Phil\Stockroom\Exception\UnknownStock;

/**
 * The load list of each event and what follows it: where to pick each line, the deployment that sends the pieces to the
 * event, and the check at the return that brings back what was counted and flags what is missing.
 *
 * A deployment counts what is at the event already: deploying again sends only what is still lacking, so that a
 * deployment cut short is finished later by the same call.
 */
final readonly class Deployments
{
    public function __construct(
        private Stockroom $stockroom,
        private LoadLists $lists,
        private Balances $balances,
    ) {
    }

    /**
     * Writes the load list of an event, replacing the one it had.
     *
     * @param iterable<LoadLine> $lines
     *
     * @throws InvalidStock when the event or a line is not one, or the lines are too many
     * @throws UnknownStock when an item or a container does not exist
     */
    public function plan(string $event, iterable $lines): LoadList
    {
        $list = new LoadList($event, $lines);

        foreach ($list->lines as $line) {
            $this->item($line->item);

            if (null !== $line->from) {
                $this->stockroom->container($line->from);
            }
        }

        $this->lists->save($list);

        return $list;
    }

    /**
     * @throws UnknownStock when the event has no load list
     */
    public function loadList(string $event): LoadList
    {
        return $this->lists->get($event) ?? throw UnknownStock::loadList($event);
    }

    /**
     * Where to find each line of the list, as it stands now.
     *
     * @return list<PickLine>
     *
     * @throws UnknownStock when the event has no load list, or an item no longer exists
     */
    public function picking(string $event): array
    {
        $picking = [];

        foreach ($this->loadList($event)->lines as $line) {
            $item = $this->item($line->item);
            $sources = null === $line->from
                ? $this->stockroom->sources($item)
                : array_values(array_filter($this->stockroom->sources($item), static fn (Holding $holding): bool => $line->from === $holding->place->reference));
            $available = $item->condition->mayLeave() ? array_sum(array_map(static fn (Holding $holding): int => $holding->quantity, $sources)) : 0;
            $picking[] = new PickLine($line, $sources, $available);
        }

        return $picking;
    }

    /**
     * Sends the pieces of the list still lacking at the event. When the stockroom is short of something, nothing moves
     * and the deployment lists what lacks; with `$partial`, what is there goes and the rest is listed.
     *
     * @throws UnknownStock when the event has no load list, or an item or a container no longer exists
     * @throws InvalidStock when the note is not one
     */
    public function deploy(string $event, bool $partial = false, ?string $note = null): Deployment
    {
        $list = $this->loadList($event);
        $place = Place::event($event);
        $plan = [];
        $shortfalls = [];

        foreach ($list->totals() as $key => $total) {
            $id = (string) $key;
            $item = $this->item($id);
            $lacking = max(0, $total - $this->balances->quantity($id, $place));
            $taken = $this->allocate($item, $list, $lacking);
            $found = array_sum($taken);

            if ($found < $lacking) {
                $shortfalls[] = new Shortfall($id, $lacking, $found);
            }

            $plan[] = [$id, $taken];
        }

        if ([] !== $shortfalls && !$partial) {
            return new Deployment([], $shortfalls);
        }

        $movements = [];

        foreach ($plan as [$id, $taken]) {
            foreach ($taken as $container => $quantity) {
                array_push($movements, ...$this->stockroom->issue($id, $place, $quantity, (string) $container, $event, $note));
            }
        }

        return new Deployment($movements, $shortfalls);
    }

    /**
     * What is at the event now, not yet back.
     *
     * @return list<Holding>
     */
    public function outstanding(string $event): array
    {
        return $this->balances->at(Place::event($event), LoadList::MAX_LINES);
    }

    /**
     * The check at the return: the pieces counted come back to their items' homes — or to the container of the list they
     * left from —, the pieces out and not counted are listed missing and stay out at the event, unless `$writeOff`
     * records them lost.
     *
     * @param array<array-key, int> $counted the pieces counted, by item
     *
     * @throws InvalidStock when a count is negative or above the bound, or an item that comes back has nowhere to go
     * @throws UnknownStock when an item or a container no longer exists
     */
    public function checkIn(string $event, array $counted, bool $writeOff = false, ?string $note = null): ReturnCheck
    {
        $place = Place::event($event);
        $list = $this->lists->get($event);
        $plan = [];
        $missing = [];

        foreach ($counted as $count) {
            if ($count < 0 || $count > Quantity::MAX) {
                throw InvalidStock::because(\sprintf('A count is from 0 to %d, not %d.', Quantity::MAX, $count));
            }
        }

        foreach ($this->outstanding($event) as $holding) {
            $back = min($counted[$holding->item] ?? 0, $holding->quantity);
            $plan[] = [$holding, $back, $back > 0 ? $this->destination($this->item($holding->item), $list) : null];

            if ($back < $holding->quantity) {
                $missing[] = new Shortfall($holding->item, $holding->quantity, $back);
            }

            $counted[$holding->item] = ($counted[$holding->item] ?? 0) - $back;
        }

        $movements = [];

        foreach ($plan as [$holding, $back, $to]) {
            if (null !== $to) {
                $movements[] = $this->stockroom->giveBack($holding->item, $place, $back, $to, $event, $note);
            }

            if ($writeOff && $back < $holding->quantity) {
                $movements[] = $this->stockroom->lose($holding->item, $place, $holding->quantity - $back, $event, $note);
            }
        }

        return new ReturnCheck($movements, $missing, array_filter($counted, static fn (int $left): bool => $left > 0));
    }

    /**
     * @throws UnknownStock when the item does not exist
     */
    private function item(string $id): Item
    {
        return $this->stockroom->item($id) ?? throw UnknownStock::item($id);
    }

    /**
     * The pieces of an item to take for the event, by container: from the containers its lines name, up to each line's
     * quantity, then from its home and the others; none when the item may not leave.
     *
     * @return array<array-key, int>
     */
    private function allocate(Item $item, LoadList $list, int $lacking): array
    {
        if (0 === $lacking || !$item->condition->mayLeave()) {
            return [];
        }

        /** @var array<array-key, int> $held the pieces in each container, by its identifier */
        $held = [];

        foreach ($this->stockroom->sources($item) as $source) {
            $held[$source->place->reference] = $source->quantity;
        }

        $passes = [];

        foreach ($list->named($item->id) as $line) {
            $passes[] = [(string) $line->from, $line->quantity];
        }

        foreach ($held as $container => $quantity) {
            $passes[] = [(string) $container, $quantity];
        }

        $taken = [];
        $left = $lacking;

        foreach ($passes as [$container, $cap]) {
            $take = min($left, $cap, ($held[$container] ?? 0) - ($taken[$container] ?? 0));

            if ($take > 0) {
                $taken[$container] = ($taken[$container] ?? 0) + $take;
                $left -= $take;
            }
        }

        return $taken;
    }

    /**
     * Where an item comes back to: its home, else the first container a line of the list takes it from.
     *
     * @throws InvalidStock when it has neither
     */
    private function destination(Item $item, ?LoadList $list): string
    {
        return $item->home ?? ($list?->named($item->id)[0] ?? null)->from ?? throw InvalidStock::because(\sprintf('The item "%s" has no home: give it one before it comes back.', $item->code));
    }
}
