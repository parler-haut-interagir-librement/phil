<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Fixtures;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Keeps the events dispatched, by their class and room, in order.
 */
final class RecordingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    #[Override]
    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }

    /**
     * @return list<string> each event as its short class name, its room and its participant or recording
     */
    public function names(): array
    {
        return array_map(static function (object $event): string {
            $name = substr(strrchr($event::class, '\\') ?: $event::class, 1);
            $values = array_filter(get_object_vars($event), is_string(...));

            return trim($name.' '.implode(' ', $values));
        }, $this->events);
    }
}
