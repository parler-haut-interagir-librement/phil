<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck;

/**
 * What a rule reports: where, what it says and, when it differs from the rule's, the severity or the success criterion
 * of this one case. The checker turns it into a `Finding` with the rule's identifier.
 */
final readonly class Violation
{
    public function __construct(
        public Location $location,
        public Message $message,
        public ?Severity $severity = null,
        public ?SuccessCriterion $criterion = null,
    ) {
    }
}
