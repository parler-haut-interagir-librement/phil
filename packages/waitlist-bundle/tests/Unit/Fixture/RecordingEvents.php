<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Tests\Unit\Fixture;

use OutOfBoundsException;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The events dispatched, in order.
 */
final class RecordingEvents implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    #[Override]
    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }

    public function at(int $index): object
    {
        return $this->events[$index] ?? throw new OutOfBoundsException(\sprintf('No event %d.', $index));
    }

    /**
     * The events, each as its short class name and its entry or list.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(static function (object $event): string {
            $name = substr((string) strrchr($event::class, '\\'), 1);
            /** @var string $subject */
            $subject = property_exists($event, 'entryId') ? $event->entryId : (property_exists($event, 'waitlistId') ? $event->waitlistId : '');

            return $name.' '.$subject;
        }, $this->events);
    }
}
