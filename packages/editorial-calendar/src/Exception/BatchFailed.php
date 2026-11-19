<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\Exception;

use DomainException;

/**
 * A batch that could not be applied whole: one of its items refused its transition, and none was applied.
 */
final class BatchFailed extends DomainException implements EditorialCalendarException
{
    public function __construct(
        public readonly string $batch,
        public readonly string $item,
        InvalidTransition $previous,
    ) {
        parent::__construct(\sprintf('The batch "%s" was not applied: %s', $batch, $previous->getMessage()), 0, $previous);
    }
}
