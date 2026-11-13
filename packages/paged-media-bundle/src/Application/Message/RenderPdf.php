<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application\Message;

/**
 * Renders a document template in a worker: the `RenderJob` kept under the identifier.
 * The message carries nothing else: the documents, the parameters and the options wait in the job store.
 */
final readonly class RenderPdf
{
    public function __construct(public string $job)
    {
    }
}
