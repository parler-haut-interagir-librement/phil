<?php

declare(strict_types=1);

namespace Phil\AudienceSegments;

use Phil\Rules\Evaluation\Result;

/**
 * Whether one member belongs to a segment, and why: excluded globally, or the result of the rule with its trace.
 */
final readonly class Membership
{
    public function __construct(
        public string $member,
        public bool $excluded,
        public Result $result,
    ) {
    }

    public function isMember(): bool
    {
        return !$this->excluded && $this->result->passed;
    }
}
