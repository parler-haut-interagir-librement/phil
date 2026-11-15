<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Change;

/**
 * Where a change of a placed order stands.
 */
enum OrderChangeState: string
{
    /** Prepared with its preview: the order is not touched yet. */
    case Prepared = 'prepared';

    /** Applied to the order, which counts one more revision. */
    case Confirmed = 'confirmed';

    /** Given up before it was confirmed. */
    case Abandoned = 'abandoned';
}
