<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Unit\Fixture;

use Override;
use Phil\PagedMediaBundle\Application\Exception\JobUnavailable;
use Phil\PagedMediaBundle\Application\Job\ConversionJob;
use Phil\PagedMediaBundle\Application\Job\PipelineJob;
use Phil\PagedMediaBundle\Application\Job\RenderJob;
use Phil\PagedMediaBundle\Application\Port\JobStore;

/**
 * A job store that keeps the jobs in memory, under `job-1`, `job-2` and so on.
 */
final class MemoryJobStore implements JobStore
{
    /** @var array<string, RenderJob|PipelineJob|ConversionJob> */
    public array $jobs = [];

    #[Override]
    public function keep(RenderJob|PipelineJob|ConversionJob $job): string
    {
        $id = 'job-'.(\count($this->jobs) + 1);
        $this->jobs[$id] = $job;

        return $id;
    }

    #[Override]
    public function find(string $id): RenderJob|PipelineJob|ConversionJob
    {
        return $this->jobs[$id] ?? throw JobUnavailable::because(\sprintf('No job is kept under "%s".', $id));
    }

    #[Override]
    public function remove(string $id): void
    {
        unset($this->jobs[$id]);
    }
}
