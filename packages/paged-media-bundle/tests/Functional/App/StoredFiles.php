<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Functional\App;

use Phil\PagedMediaBundle\Application\Event\PdfStored;

/**
 * Listens for the files the messages keep.
 */
final class StoredFiles
{
    /** @var list<PdfStored> */
    public array $events = [];

    public function __invoke(PdfStored $event): void
    {
        $this->events[] = $event;
    }
}
