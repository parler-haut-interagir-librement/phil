<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Fixtures;

use Override;
use Phil\CalendarBundle\Application\EventTagging;
use Phil\CalendarBundle\Domain\Model\Event;

/**
 * A taxonomy in memory that writes every name in capitals, for the tests to see whose names an event keeps.
 */
final class RecordedTagging implements EventTagging
{
    /** @var array<string, list<string>> by event */
    public array $tags = [];

    #[Override]
    public function tag(Event $event, array $names): array
    {
        return $this->tags[$event->id] = array_map(mb_strtoupper(...), $names);
    }

    #[Override]
    public function tagsOf(Event $event): array
    {
        return $this->tags[$event->id] ?? [];
    }

    #[Override]
    public function forget(Event $event): void
    {
        unset($this->tags[$event->id]);
    }
}
