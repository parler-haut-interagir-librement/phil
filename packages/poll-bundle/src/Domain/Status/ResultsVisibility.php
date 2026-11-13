<?php

declare(strict_types=1);

namespace Phil\PollBundle\Domain\Status;

/**
 * Who may see the results of a poll, besides the people who manage it, who always may.
 */
enum ResultsVisibility: string
{
    /** Everyone allowed to see the poll, at any time. */
    case Always = 'always';

    /** A voter once they have voted, and everyone once the poll is closed. */
    case AfterVoting = 'after_voting';

    /** Everyone once the poll is closed. */
    case AfterClosing = 'after_closing';

    /** Only the people who manage the poll. */
    case Managers = 'managers';
}
