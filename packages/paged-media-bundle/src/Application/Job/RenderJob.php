<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application\Job;

use Phil\PagedMedia\Gotenberg\Pipeline;

/**
 * What a worker needs to render a document template: the template, its parameters, the stages, the signature and the
 * timestamp asked for, and where the file is kept. It is kept by the job store; the `RenderPdf` message carries only
 * its identifier.
 */
final readonly class RenderJob
{
    /**
     * @param array<string, mixed> $parameters scalars and arrays, which the job store writes as JSON
     * @param string               $path       where the storage keeps the file, relative to it
     */
    public function __construct(
        public string $template,
        public array $parameters,
        public string $path,
        public ?Pipeline $pipeline = null,
        public bool $sign = false,
        public bool $timestamp = false,
        public string $filename = 'document.pdf',
    ) {
    }
}
