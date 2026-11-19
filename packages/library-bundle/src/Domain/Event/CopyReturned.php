<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Domain\Event;

use Phil\LibraryBundle\Domain\Status\CopyCondition;

/**
 * A copy back at the desk, with the condition it was found in. It names the loan and the copy, no person.
 */
final readonly class CopyReturned
{
    public function __construct(
        public string $loan,
        public string $copy,
        public CopyCondition $condition,
    ) {
    }
}
