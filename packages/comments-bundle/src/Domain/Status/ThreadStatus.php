<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Domain\Status;

/**
 * Whether a thread still asks for something: open, or resolved by someone who may close it.
 */
enum ThreadStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
