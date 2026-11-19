<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Domain\Event;

/**
 * A copy handed over to a member: lent at the desk, or a reservation checked out. It names the loan and the copy, no
 * person.
 */
final readonly class CopyLent
{
    public function __construct(
        public string $loan,
        public string $copy,
    ) {
    }
}
