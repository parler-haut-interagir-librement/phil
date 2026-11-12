<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use Phil\Publishing\PagePublished;
use Phil\Publishing\PageRetired;

/**
 * Keeps the events of `phil/publishing` the kernel dispatches, for the tests to read.
 */
final class PageEventRecorder
{
    /**
     * @var list<PagePublished|PageRetired>
     */
    private array $events = [];

    public function published(PagePublished $event): void
    {
        $this->events[] = $event;
    }

    public function retired(PageRetired $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return list<PagePublished|PageRetired>
     */
    public function events(): array
    {
        return $this->events;
    }

    public function reset(): void
    {
        $this->events = [];
    }
}
