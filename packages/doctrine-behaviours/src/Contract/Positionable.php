<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Contract;

/**
 * A record kept in an order of its own, by an integer position within its group: `PositionListener` sends a record
 * with no position to the end of its group as it is flushed, and `Positions` moves it before or after another one.
 * Positions only order; gaps between them mean nothing.
 */
interface Positionable
{
    public const string POSITION_FIELD = 'position';

    public function position(): ?int;

    /**
     * Places the record; null sends it to the end of its group at the next flush.
     */
    public function placeAt(?int $position): void;

    /**
     * The fields whose values make a group, each group ordered on its own: a parent, a tenant, a locale. Each is a
     * column of the record or a to-one relation it holds by one column. An empty list keeps one order for the table.
     *
     * @return list<string>
     */
    public function positionGroup(): array;
}
