<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Tests\Unit\Fixture;

use Phil\ConversationBundle\Application\Event\MessageSent;
use Phil\HelpdeskBundle\Application\Event\TicketAssigned;
use Phil\HelpdeskBundle\Application\Event\TicketOpened;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Keeps the events of the help desk, the messages of the conversations, and the transitions the workflow completed.
 */
final class RecordingListener
{
    /** @var list<object> */
    public array $events = [];

    /** @var list<string> */
    public array $transitions = [];

    public function __construct(EventDispatcher $dispatcher)
    {
        foreach ([TicketOpened::class, TicketAssigned::class, MessageSent::class] as $event) {
            $dispatcher->addListener($event, function (object $event): void {
                $this->events[] = $event;
            });
        }

        $dispatcher->addListener('workflow.phil_helpdesk_ticket.completed', function (CompletedEvent $event): void {
            $this->transitions[] = (string) $event->getTransition()?->getName();
        });
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public function of(string $class): array
    {
        return array_values(array_filter($this->events, static fn (object $event): bool => $event instanceof $class));
    }
}
