<?php

declare(strict_types=1);

namespace Phil\Stockroom;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Stockroom\Exception\InvalidStock;

/**
 * One line of the journal: what moved, how many, from where to where, when — in UTC —, and why: the reference of the
 * event, the loan or the order it belongs to, and a note. A movement is written once and never changed.
 */
final readonly class Movement
{
    public DateTimeImmutable $at;

    public ?string $note;

    /**
     * @throws InvalidStock when the places do not fit the kind, or a value is not one the journal keeps
     */
    public function __construct(
        public string $id,
        public MovementKind $kind,
        public string $item,
        public int $quantity,
        public ?Place $from,
        public ?Place $to,
        DateTimeImmutable $at,
        public ?string $reference = null,
        ?string $note = null,
    ) {
        Identifier::check($id, 'identifier of a movement');
        Identifier::check($item, 'item of a movement');
        Quantity::check($quantity);
        Identifier::optional($reference, 'reference of a movement');
        $this->note = Text::optional($note, 'note of a movement');
        $this->at = $at->setTimezone(new DateTimeZone('UTC'));

        if (!$this->fits($kind, $from, $to)) {
            throw InvalidStock::because(\sprintf('A movement "%s" does not go from %s to %s.', $kind->value, $from?->key() ?? 'nowhere', $to?->key() ?? 'nowhere'));
        }
    }

    /**
     * Whether the places are those of the kind: in to a container, out from a container to outside, a transfer between
     * two containers, a loss from a place, a return from outside to a container.
     */
    private function fits(MovementKind $kind, ?Place $from, ?Place $to): bool
    {
        return match ($kind) {
            MovementKind::In => !$from instanceof Place && true === $to?->isContainer(),
            MovementKind::Out => true === $from?->isContainer() && false === $to?->isContainer(),
            MovementKind::Transfer => true === $from?->isContainer() && true === $to?->isContainer() && !$from->equals($to),
            MovementKind::Loss => $from instanceof Place && !$to instanceof Place,
            MovementKind::Return => false === $from?->isContainer() && true === $to?->isContainer(),
        };
    }
}
