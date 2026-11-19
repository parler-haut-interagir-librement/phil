<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Application;

use Phil\HelpdeskBundle\Domain\Exception\InvalidQueue;
use Phil\HelpdeskBundle\Domain\ValueObject\QueueName;

/**
 * The queues of the configuration, in their order.
 */
final readonly class Queues
{
    /** @var array<string, Queue> */
    private array $queues;

    public function __construct(Queue ...$queues)
    {
        $byName = [];

        foreach ($queues as $queue) {
            $byName[$queue->name->value] = $queue;
        }

        $this->queues = $byName;
    }

    /**
     * @param array<string, array{label: string, roles: non-empty-list<string>}> $declarations
     */
    public static function fromDeclarations(array $declarations): self
    {
        $queues = [];

        foreach ($declarations as $name => $declaration) {
            $queues[] = new Queue(QueueName::fromString($name), $declaration['label'], $declaration['roles']);
        }

        return new self(...$queues);
    }

    /**
     * @throws InvalidQueue when the queue is not declared
     */
    public function get(QueueName $name): Queue
    {
        return $this->queues[$name->value] ?? throw InvalidQueue::unknown($name->value);
    }

    /**
     * @return list<Queue>
     */
    public function all(): array
    {
        return array_values($this->queues);
    }
}
