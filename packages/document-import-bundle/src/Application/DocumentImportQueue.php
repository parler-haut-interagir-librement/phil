<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Application;

use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Exception\UnsupportedFormat;
use Phil\DocumentImport\Format;
use Phil\DocumentImportBundle\Application\Contract\PendingImports;
use Phil\DocumentImportBundle\Application\Message\ImportDocument;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Imports a file on a worker: refused at once when `DocumentImports` would refuse its size or its format, kept in
 * `PendingImports` otherwise, and `ImportDocument` dispatched with the claim alone — a claim check: the message holds
 * no byte of the file, nor its name. The result comes back as `DocumentImported`, or `DocumentImportFailed`, carrying
 * the claim this method gives.
 */
final readonly class DocumentImportQueue
{
    public function __construct(
        private DocumentImports $imports,
        private PendingImports $pending,
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * @return string the claim the result will carry
     *
     * @throws UnsupportedFormat when its format is not accepted, or cannot be detected
     * @throws LimitExceeded     when the file is larger than the limits
     */
    public function submit(string $contents, ?Format $format = null, ?string $filename = null): string
    {
        $format = $this->imports->accept($contents, $format, $filename);
        $claim = $this->pending->keep(new PendingImport($contents, $format, $filename));
        $this->bus->dispatch(new ImportDocument($claim));

        return $claim;
    }
}
