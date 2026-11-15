<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application\Event;

use Phil\ModerationBundle\Domain\ValueObject\StatementOfReasons;

/**
 * A statement of reasons was issued to the author of a restricted content, after the decision it explains: the moment
 * to tell them, by a notification or an email that links to it.
 */
final readonly class StatementIssued
{
    public function __construct(
        public StatementOfReasons $statement,
    ) {
    }
}
