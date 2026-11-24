<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application\Exception;

use Phil\SchedulingPollBundle\Domain\Exception\SchedulingPollException;
use RuntimeException;

/**
 * Only the organizer of a scheduling poll invites, revokes, closes it and chooses its date.
 */
final class NotOrganizer extends RuntimeException implements SchedulingPollException
{
    public static function of(string $poll): self
    {
        return new self(\sprintf('Only the organizer of the scheduling poll "%s" may do this.', $poll));
    }
}
