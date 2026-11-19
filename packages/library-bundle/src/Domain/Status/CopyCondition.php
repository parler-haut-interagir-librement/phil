<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Domain\Status;

/**
 * The state of a copy, assessed when it comes back: good, worn, damaged, incomplete — a box with pieces missing — or
 * withdrawn from the shelves. Only a withdrawn copy is not lent.
 */
enum CopyCondition: string
{
    case Good = 'good';
    case Worn = 'worn';
    case Damaged = 'damaged';
    case Incomplete = 'incomplete';
    case Withdrawn = 'withdrawn';

    public function mayLend(): bool
    {
        return self::Withdrawn !== $this;
    }
}
