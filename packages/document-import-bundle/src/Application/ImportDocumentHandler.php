<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Application;

use Phil\DocumentImport\Exception\DocumentImportException;
use Phil\DocumentImportBundle\Application\Contract\PendingImports;
use Phil\DocumentImportBundle\Application\Message\ImportDocument;
use Phil\DocumentImportBundle\Domain\Event\DocumentImportFailed;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Handles `ImportDocument`: the file taken back by its claim, imported by `DocumentImports` — screened, then parsed —,
 * `DocumentImported` dispatched with the claim, and the claim released. A file the library refuses dispatches
 * `DocumentImportFailed` and is released too: trying it again gives the same answer. Any other error leaves the claim
 * for the next try. A claim already released is left alone.
 */
final readonly class ImportDocumentHandler
{
    public function __construct(
        private DocumentImports $imports,
        private PendingImports $pending,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    public function __invoke(ImportDocument $message): void
    {
        $import = $this->pending->take($message->claim);

        if (!$import instanceof PendingImport) {
            return;
        }

        try {
            $this->imports->import($import->contents, $import->format, $import->filename, $message->claim);
        } catch (DocumentImportException $documentImportException) {
            $this->pending->release($message->claim);
            $this->events?->dispatch(new DocumentImportFailed($message->claim, $documentImportException));

            return;
        }

        $this->pending->release($message->claim);
    }
}
