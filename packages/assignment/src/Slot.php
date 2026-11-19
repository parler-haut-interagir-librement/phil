<?php

declare(strict_types=1);

namespace Phil\Assignment;

use Phil\Assignment\Exception\InvalidProblem;
use Phil\Assignment\Internal\Identifier;

/**
 * A place people are assigned to — a game table, a workshop, a room, a role — with the number of people it holds and
 * the number it needs to run. A slot that ends under its minimum is closed and its people placed again; a minimum of
 * zero never closes it.
 */
final readonly class Slot
{
    public const int MAX_CAPACITY = 1_000_000;

    public string $id;

    /**
     * @throws InvalidProblem
     */
    public function __construct(
        string $id,
        public int $capacity,
        public int $minimum = 0,
    ) {
        $this->id = Identifier::check($id, 'slot');

        if ($capacity < 0 || $capacity > self::MAX_CAPACITY) {
            throw InvalidProblem::because(\sprintf('The capacity of slot "%s" is 0 to %d.', $id, self::MAX_CAPACITY));
        }

        if ($minimum < 0 || $minimum > $capacity) {
            throw InvalidProblem::because(\sprintf('The minimum of slot "%s" is 0 to its capacity.', $id));
        }
    }
}
