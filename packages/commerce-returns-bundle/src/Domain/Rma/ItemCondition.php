<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Domain\Rma;

/**
 * What a returned item was found to be on receipt.
 */
enum ItemCondition: string
{
    /** As new: back on the shelf. */
    case Resellable = 'resellable';

    /** Damaged or used: not sold again as new. */
    case Damaged = 'damaged';

    /** Never came back. */
    case Missing = 'missing';
}
