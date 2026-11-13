<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application\Message;

/**
 * Runs a list of stages on PDFs in a worker: the `PipelineJob` kept under the identifier.
 * The message carries nothing else: the documents, the parameters and the options wait in the job store.
 */
final readonly class RunPipeline
{
    public function __construct(public string $job)
    {
    }
}
