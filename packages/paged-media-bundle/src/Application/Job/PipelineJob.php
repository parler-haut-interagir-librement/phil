<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application\Job;

use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\Pipeline;

/**
 * What a worker needs to run a list of stages on PDFs: the files, the stages, the signature and the timestamp asked
 * for, and where the file is kept. It is kept by the job store; the `RunPipeline` message carries only its identifier.
 */
final readonly class PipelineJob
{
    public function __construct(
        public Files $files,
        public Pipeline $pipeline,
        public string $path,
        public bool $sign = false,
        public bool $timestamp = false,
    ) {
    }
}
