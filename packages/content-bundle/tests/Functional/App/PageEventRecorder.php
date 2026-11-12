<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Functional\App;

use Phil\Publishing\PagePublished;
use Phil\Publishing\PageRetired;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Keeps the events of `phil/publishing` the kernel dispatches, for the tests to read.
 */
final class PageEventRecorder
{
    /**
     * @var list<PagePublished|PageRetired>
     */
    private array $events = [];

    #[AsEventListener]
    public function published(PagePublished $event): void
    {
        $this->events[] = $event;
    }

    #[AsEventListener]
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
}
