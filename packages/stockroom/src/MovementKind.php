<?php

declare(strict_types=1);

namespace Phil\Stockroom;

/**
 * What a movement does: brings stock in, sends it out to an event or a holder, moves it between containers, records it
 * lost, or brings it back from outside.
 */
enum MovementKind: string
{
    /** From nowhere into a container: bought, made, given. */
    case In = 'in';

    /** From a container to an event or a holder. */
    case Out = 'out';

    /** From a container to another. */
    case Transfer = 'transfer';

    /** From any place to nowhere. */
    case Loss = 'loss';

    /** From an event or a holder back into a container. */
    case Return = 'return';
}
