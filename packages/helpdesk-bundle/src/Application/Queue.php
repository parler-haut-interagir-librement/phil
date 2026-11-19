<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Application;

use Phil\HelpdeskBundle\Domain\ValueObject\QueueName;

/**
 * A queue of the configuration: its name, the translation key of its label, and the roles whose holders work it — any
 * one of them is enough.
 */
final readonly class Queue
{
    /**
     * @param non-empty-list<string> $roles
     */
    public function __construct(
        public QueueName $name,
        public string $label,
        public array $roles,
    ) {
    }
}
