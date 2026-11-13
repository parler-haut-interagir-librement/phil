<?php

declare(strict_types=1);

namespace Phil\Stockroom;

use Override;
use Phil\Stockroom\Contract\Balances;
use Phil\Stockroom\Contract\Containers;
use Phil\Stockroom\Contract\Items;
use Phil\Stockroom\Contract\Journal;
use Phil\Stockroom\Contract\StockReader;
use Phil\Stockroom\Exception\InsufficientStock;
use Phil\Stockroom\Exception\InvalidStock;
use Phil\Stockroom\Exception\UnknownStock;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The stockroom: its containers nested in one another, its items, and every movement of stock — in, out to an event or
 * a holder, between containers, lost, back — written to the journal as it changes the balances. A movement never takes
 * more than a place holds, a tracked unit is in one place at a time, and an item under repair or retired does not
 * leave. Moments come from the clock and are kept in UTC.
 *
 * It keeps no state of its own: the stores hold it all. Each method reads, checks, then writes; an application whose
 * stores are a database runs each call in a transaction, so that the journal and the balances change together.
 */
final readonly class Stockroom implements StockReader
{
    /** The deepest a container sits: so many containers from the top, itself included. */
    public const int MAX_DEPTH = 32;

    /** The most containers a nested listing walks through. */
    public const int MAX_CONTAINERS = 10_000;

    /** The most lines a listing or a history gives. */
    public const int MAX_LIMIT = 1000;

    public function __construct(
        private Containers $containers,
        private Items $items,
        private Balances $balances,
        private Journal $journal,
        private ClockInterface $clock,
    ) {
    }

    /**
     * A new container, at the top or in another.
     *
     * @throws InvalidStock when a value is not one, or the parent sits too deep already
     * @throws UnknownStock when the parent does not exist
     */
    public function addContainer(string $name, ?string $parent = null, ?string $code = null, ?string $id = null): Container
    {
        $container = new Container($id ?? $this->newId(), $name, $parent, $code);

        if ($this->containers->get($container->id) instanceof Container) {
            throw InvalidStock::because(\sprintf('There is a container "%s" already.', $container->id));
        }

        if (null !== $parent && \count($this->path($parent)) >= self::MAX_DEPTH) {
            throw InvalidStock::because(\sprintf('A container sits at most %d deep.', self::MAX_DEPTH));
        }

        $this->containers->save($container);

        return $container;
    }

    /**
     * @throws UnknownStock when it does not exist
     */
    public function container(string $id): Container
    {
        return $this->containers->get($id) ?? throw UnknownStock::container($id);
    }

    /**
     * Moves a container, with all it holds, into another or to the top.
     *
     * @throws InvalidStock when the parent is the container or sits in it, or sits too deep already
     * @throws UnknownStock when either container does not exist
     */
    public function moveContainer(string $id, ?string $parent): Container
    {
        $container = $this->container($id);

        if (null !== $parent) {
            $path = $this->path($parent);

            if (array_any($path, static fn (Container $ancestor): bool => $id === $ancestor->id)) {
                throw InvalidStock::because('A container does not sit in itself, nor in a container it holds.');
            }

            if (\count($path) >= self::MAX_DEPTH) {
                throw InvalidStock::because(\sprintf('A container sits at most %d deep.', self::MAX_DEPTH));
            }
        }

        $moved = $container->in($parent);
        $this->containers->save($moved);

        return $moved;
    }

    /**
     * The containers from the top down to this one, itself last: what its label reads, `Warehouse › Shelf B › Crate 3`.
     *
     * @return list<Container>
     *
     * @throws UnknownStock when a container of the path does not exist
     * @throws InvalidStock when the path is deeper than the bound, which a loop of parents also is
     */
    public function path(string $id): array
    {
        $path = [];
        $next = $id;

        while (null !== $next) {
            if (\count($path) >= self::MAX_DEPTH) {
                throw InvalidStock::because(\sprintf('The container "%s" sits deeper than %d containers.', $id, self::MAX_DEPTH));
            }

            $container = $this->container($next);
            $path[] = $container;
            $next = $container->parent;
        }

        return array_reverse($path);
    }

    /**
     * What a container holds, and with `$nested` what the containers in it hold as well, nearest first.
     *
     * @return list<Holding>
     *
     * @throws UnknownStock when the container does not exist
     * @throws InvalidStock when the limit is out of bounds
     */
    public function contents(string $container, bool $nested = false, int $limit = 100): array
    {
        $limit = $this->limit($limit);
        $queue = [$this->container($container)];
        $holdings = [];
        $walked = 0;

        while ([] !== $queue && \count($holdings) < $limit && $walked < self::MAX_CONTAINERS) {
            $current = array_shift($queue);
            ++$walked;
            array_push($holdings, ...$this->balances->at($current->place(), $limit - \count($holdings)));

            if ($nested) {
                array_push($queue, ...$this->containers->children($current->id, self::MAX_LIMIT));
            }
        }

        return $holdings;
    }

    /**
     * A new item, its code unique.
     *
     * @throws InvalidStock when a value is not one, or the code or the identifier is taken
     * @throws UnknownStock when the home does not exist
     */
    public function addItem(string $code, string $name, Tracking $tracking = Tracking::Quantity, ?string $home = null, ?string $id = null): Item
    {
        $item = new Item($id ?? $this->newId(), $code, $name, $tracking, $home);

        if ($this->items->get($item->id) instanceof Item || $this->items->byCode($code) instanceof Item) {
            throw InvalidStock::because(\sprintf('There is an item "%s" or with the code "%s" already.', $item->id, $code));
        }

        if (null !== $home) {
            $this->container($home);
        }

        $this->items->save($item);

        return $item;
    }

    #[Override]
    public function item(string $id): ?Item
    {
        return $this->items->get($id);
    }

    /**
     * The item whose label carries the code.
     *
     * @throws UnknownStock when there is none
     */
    public function itemByCode(string $code): Item
    {
        return $this->items->byCode($code) ?? throw UnknownStock::item($code);
    }

    /**
     * Marks an item usable, waiting for a repair, or retired: only a usable item leaves.
     *
     * @throws UnknownStock when the item does not exist
     */
    public function setCondition(string $item, ItemCondition $condition): Item
    {
        $changed = $this->get($item)->withCondition($condition);
        $this->items->save($changed);

        return $changed;
    }

    /**
     * Gives an item another home, or none.
     *
     * @throws UnknownStock when the item or the container does not exist
     */
    public function setHome(string $item, ?string $home): Item
    {
        if (null !== $home) {
            $this->container($home);
        }

        $changed = $this->get($item)->withHome($home);
        $this->items->save($changed);

        return $changed;
    }

    /**
     * Brings pieces into a container: bought, made, given, found.
     *
     * @throws UnknownStock      when the item or the container does not exist
     * @throws InvalidStock      when a value is not one, or a tracked unit is received more than one at a time
     * @throws InsufficientStock when a tracked unit is already in stock
     */
    public function receive(string $item, string $container, int $quantity = 1, ?string $reference = null, ?string $note = null): Movement
    {
        $entry = $this->get($item);
        $this->container($container);
        $this->assertUnitQuantity($entry, $quantity);

        if ($entry->isUnit() && [] !== $this->balances->of($entry->id)) {
            throw InsufficientStock::because(\sprintf('The unit "%s" is in stock already.', $entry->code));
        }

        return $this->record(MovementKind::In, $entry->id, $quantity, null, Place::container($container), $reference, $note);
    }

    /**
     * Sends pieces out to an event or a holder: from the container named, or from the containers holding the item, its
     * home first, as many movements as containers it takes from.
     *
     * @return list<Movement>
     *
     * @throws UnknownStock      when the item or the container does not exist
     * @throws InvalidStock      when a value is not one, or the place is a container
     * @throws InsufficientStock when the item may not leave, or there are not enough pieces
     */
    public function issue(string $item, Place $to, int $quantity = 1, ?string $from = null, ?string $reference = null, ?string $note = null): array
    {
        $entry = $this->get($item);

        if ($to->isContainer()) {
            throw InvalidStock::because('Stock goes out to an event or a holder; between containers, it is transferred.');
        }

        $this->assertUnitQuantity($entry, $quantity);
        $this->assertMayLeave($entry);
        $movements = [];

        foreach ($this->allocate($entry, $quantity, $from) as $key => $taken) {
            $movements[] = $this->record(MovementKind::Out, $entry->id, $taken, Place::fromKey($key), $to, $reference, $note);
        }

        return $movements;
    }

    /**
     * Moves pieces from a container to another.
     *
     * @throws UnknownStock      when the item or a container does not exist
     * @throws InvalidStock      when a value is not one, or both containers are the same
     * @throws InsufficientStock when the first container holds fewer pieces
     */
    public function transfer(string $item, string $from, string $to, int $quantity = 1, ?string $reference = null, ?string $note = null): Movement
    {
        $entry = $this->get($item);
        $this->container($from);
        $this->container($to);
        $this->assertHeld($entry, Place::container($from), $quantity);

        return $this->record(MovementKind::Transfer, $entry->id, $quantity, Place::container($from), Place::container($to), $reference, $note);
    }

    /**
     * Records pieces lost, wherever they were: in a container, at an event, with a holder.
     *
     * @throws UnknownStock      when the item does not exist
     * @throws InvalidStock      when a value is not one
     * @throws InsufficientStock when the place holds fewer pieces
     */
    public function lose(string $item, Place $from, int $quantity = 1, ?string $reference = null, ?string $note = null): Movement
    {
        $entry = $this->get($item);
        $this->assertHeld($entry, $from, $quantity);

        return $this->record(MovementKind::Loss, $entry->id, $quantity, $from, null, $reference, $note);
    }

    /**
     * Brings pieces back from an event or a holder, to the container named or to the item's home.
     *
     * @throws UnknownStock      when the item or the container does not exist
     * @throws InvalidStock      when a value is not one, the place is a container, or no container is named and the
     *                           item has no home
     * @throws InsufficientStock when the place holds fewer pieces
     */
    public function giveBack(string $item, Place $from, int $quantity = 1, ?string $to = null, ?string $reference = null, ?string $note = null): Movement
    {
        $entry = $this->get($item);
        $to ??= $entry->home ?? throw InvalidStock::because(\sprintf('The item "%s" has no home: name the container it goes back to.', $entry->code));

        if ($from->isContainer()) {
            throw InvalidStock::because('Stock comes back from an event or a holder; between containers, it is transferred.');
        }

        $this->container($to);
        $this->assertHeld($entry, $from, $quantity);

        return $this->record(MovementKind::Return, $entry->id, $quantity, $from, Place::container($to), $reference, $note);
    }

    /**
     * The movements of an item, the latest first.
     *
     * @return list<Movement>
     *
     * @throws InvalidStock when the limit is out of bounds
     */
    public function history(string $item, int $limit = 100): array
    {
        return $this->journal->ofItem($item, $this->limit($limit));
    }

    /**
     * The movements of an event, a loan or an order, the latest first.
     *
     * @return list<Movement>
     *
     * @throws InvalidStock when the limit is out of bounds
     */
    public function movementsOf(string $reference, int $limit = 100): array
    {
        return $this->journal->ofReference($reference, $this->limit($limit));
    }

    #[Override]
    public function available(string $item): int
    {
        $entry = $this->items->get($item);

        if (!$entry instanceof Item || !$entry->condition->mayLeave()) {
            return 0;
        }

        return array_sum(array_map(static fn (Holding $holding): int => $holding->place->isContainer() ? $holding->quantity : 0, $this->balances->of($item)));
    }

    #[Override]
    public function holdings(string $item): array
    {
        return $this->balances->of($item);
    }

    /**
     * The containers holding pieces of an item, its home first, then in the order of their keys.
     *
     * @return list<Holding>
     */
    public function sources(Item $item): array
    {
        $sources = array_filter($this->balances->of($item->id), static fn (Holding $holding): bool => $holding->place->isContainer());
        usort($sources, static fn (Holding $a, Holding $b): int => [$a->place->reference !== $item->home, $a->place->key()] <=> [$b->place->reference !== $item->home, $b->place->key()]);

        return $sources;
    }

    /**
     * @throws UnknownStock when the item does not exist
     */
    private function get(string $item): Item
    {
        return $this->items->get($item) ?? throw UnknownStock::item($item);
    }

    /**
     * The pieces to take from each container, by the key of its place.
     *
     * @return array<string, int>
     *
     * @throws UnknownStock      when the container does not exist
     * @throws InsufficientStock when there are not enough pieces
     */
    private function allocate(Item $item, int $quantity, ?string $from): array
    {
        if (null !== $from) {
            $this->container($from);
            $this->assertHeld($item, Place::container($from), $quantity);

            return [Place::container($from)->key() => $quantity];
        }

        $taken = [];
        $left = $quantity;

        foreach ($this->sources($item) as $source) {
            if ($left > 0) {
                $taken[$source->place->key()] = min($left, $source->quantity);
                $left -= $taken[$source->place->key()];
            }
        }

        if ($left > 0) {
            throw InsufficientStock::because(\sprintf('The stockroom holds %d of "%s", not %d.', $quantity - $left, $item->code, $quantity));
        }

        return $taken;
    }

    /**
     * @throws InvalidStock when a tracked unit moves more than one at a time
     */
    private function assertUnitQuantity(Item $item, int $quantity): void
    {
        if ($item->isUnit() && 1 !== $quantity) {
            throw InvalidStock::because(\sprintf('The unit "%s" moves one at a time, not %d.', $item->code, $quantity));
        }
    }

    /**
     * @throws InsufficientStock when the item is under repair or retired
     */
    private function assertMayLeave(Item $item): void
    {
        if (!$item->condition->mayLeave()) {
            throw InsufficientStock::because(\sprintf('The item "%s" does not leave: it is %s.', $item->code, $item->condition->value));
        }
    }

    /**
     * @throws InvalidStock      when the quantity is out of bounds
     * @throws InsufficientStock when the place holds fewer pieces
     */
    private function assertHeld(Item $item, Place $place, int $quantity): void
    {
        $held = $this->balances->quantity($item->id, $place);

        if ($held < Quantity::check($quantity)) {
            throw InsufficientStock::because(\sprintf('%s holds %d of "%s", not %d.', $place->key(), $held, $item->code, $quantity));
        }
    }

    private function record(MovementKind $kind, string $item, int $quantity, ?Place $from, ?Place $to, ?string $reference, ?string $note): Movement
    {
        $movement = new Movement($this->newId(), $kind, $item, $quantity, $from, $to, $this->clock->now(), $reference, $note);

        if ($from instanceof Place) {
            $this->balances->set($item, $from, $this->balances->quantity($item, $from) - $quantity);
        }

        if ($to instanceof Place) {
            $this->balances->set($item, $to, $this->balances->quantity($item, $to) + $quantity);
        }

        $this->journal->append($movement);

        return $movement;
    }

    /**
     * @throws InvalidStock when the limit is out of bounds
     */
    private function limit(int $limit): int
    {
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw InvalidStock::because(\sprintf('A listing gives 1 to %d lines, not %d.', self::MAX_LIMIT, $limit));
        }

        return $limit;
    }

    private function newId(): string
    {
        return Uuid::v7()->toRfc4122();
    }
}
