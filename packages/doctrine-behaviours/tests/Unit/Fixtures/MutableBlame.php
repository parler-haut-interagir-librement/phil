<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures;

use Phil\DoctrineBehaviours\Contract\BlameResolver;

/**
 * Whoever the test says is acting now.
 */
final class MutableBlame implements BlameResolver
{
    public function __construct(
        public ?string $who = null,
    ) {
    }

    public function current(): ?string
    {
        return $this->who;
    }
}
