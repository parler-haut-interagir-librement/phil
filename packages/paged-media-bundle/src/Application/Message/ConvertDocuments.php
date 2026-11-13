<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application\Message;

/**
 * Converts office documents with LibreOffice in a worker: the `ConversionJob` kept under the identifier.
 * The message carries nothing else: the documents, the parameters and the options wait in the job store.
 */
final readonly class ConvertDocuments
{
    public function __construct(public string $job)
    {
    }
}
