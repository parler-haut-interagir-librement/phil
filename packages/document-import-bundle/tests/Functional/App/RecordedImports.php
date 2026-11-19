<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Tests\Functional\App;

use Phil\DocumentImportBundle\Domain\Event\DocumentImported;
use Phil\DocumentImportBundle\Domain\Event\DocumentImportFailed;

/**
 * A listener of the application that keeps the imports and the refusals it hears of, in order.
 */
final class RecordedImports
{
    /** @var list<DocumentImported|DocumentImportFailed> */
    private array $events = [];

    public function __invoke(DocumentImported|DocumentImportFailed $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return list<DocumentImported|DocumentImportFailed>
     */
    public function events(): array
    {
        return $this->events;
    }
}
