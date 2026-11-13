<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Tests\Functional\App;

use Phil\EpubBundle\Application\Event\EpubStored;

/**
 * Keeps the `EpubStored` events of the application.
 */
final class StoredBooks
{
    /** @var list<EpubStored> */
    public array $events = [];

    public function __invoke(EpubStored $event): void
    {
        $this->events[] = $event;
    }
}
