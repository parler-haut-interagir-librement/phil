<?php

declare(strict_types=1);

namespace {{namespace}};

use DateTimeImmutable;
use Phil\DddBundle\Domain\Event\DomainEvent;

/**
 * {{subject}}: a fact of the context {{context}}, in the past.
 */
final readonly class {{class}} implements DomainEvent
{
    public function __construct(
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
