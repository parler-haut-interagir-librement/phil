<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application\Port;

use Phil\PagedMediaBundle\Application\Job\ConversionJob;
use Phil\PagedMediaBundle\Application\Job\PipelineJob;
use Phil\PagedMediaBundle\Application\Job\RenderJob;

/**
 * Where the jobs of the messages wait for a worker — the claim check of the bundle: a message carries the identifier a
 * job is kept under, never its documents, parameters or options. The bundle keeps them in a directory; an application
 * keeps them elsewhere — a database, a Flysystem storage — by aliasing this port to a service of its own, which
 * `JobCodec` can write.
 */
interface JobStore
{
    /**
     * Keeps the job and gives the opaque identifier it is kept under.
     */
    public function keep(RenderJob|PipelineJob|ConversionJob $job): string;

    /**
     * The job kept under the identifier; `JobUnavailable` when there is none or it cannot be read.
     */
    public function find(string $id): RenderJob|PipelineJob|ConversionJob;

    /**
     * Forgets the job once its file is kept; an identifier with no job is ignored.
     */
    public function remove(string $id): void;
}
