<?php

declare(strict_types=1);

namespace Phil\Stockroom;

use Phil\Stockroom\Exception\InvalidStock;

/**
 * A place stock is kept at: a container of the stockroom, an event it is deployed to, or a holder it is lent to — an
 * account, a team, a loan, by the identifier the application gives, never a name or an e-mail address.
 */
final readonly class Place
{
    /**
     * @throws InvalidStock when the reference is not an identifier
     */
    private function __construct(
        public PlaceKind $kind,
        public string $reference,
    ) {
        Identifier::check($reference, 'reference of a place');
    }

    /**
     * @throws InvalidStock when the identifier is not one
     */
    public static function container(string $id): self
    {
        return new self(PlaceKind::Container, $id);
    }

    /**
     * @throws InvalidStock when the reference is not an identifier
     */
    public static function event(string $event): self
    {
        return new self(PlaceKind::Event, $event);
    }

    /**
     * @throws InvalidStock when the reference is not an identifier
     */
    public static function holder(string $holder): self
    {
        return new self(PlaceKind::Holder, $holder);
    }

    /**
     * The place a key gives back.
     *
     * @throws InvalidStock when the key is not one of a place
     */
    public static function fromKey(string $key): self
    {
        $parts = explode('/', $key, 2);
        $kind = PlaceKind::tryFrom($parts[0]);

        if (!$kind instanceof PlaceKind || !isset($parts[1])) {
            throw InvalidStock::because(\sprintf('"%s" is not the key of a place.', mb_strimwidth($key, 0, 40, '…')));
        }

        return new self($kind, $parts[1]);
    }

    /**
     * Its kind and reference in one string, `container/shelf-b`, which a store keeps and `fromKey()` reads back.
     */
    public function key(): string
    {
        return $this->kind->value.'/'.$this->reference;
    }

    public function isContainer(): bool
    {
        return PlaceKind::Container === $this->kind;
    }

    public function equals(self $other): bool
    {
        return $this->kind === $other->kind && $this->reference === $other->reference;
    }
}
