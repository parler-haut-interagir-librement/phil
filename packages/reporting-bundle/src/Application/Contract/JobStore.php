<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Contract;

use Phil\ReportingBundle\Application\Exception\JobUnavailable;
use Phil\ReportingBundle\Application\Report\ReportJob;

/**
 * Where the jobs of the messages wait for a worker — the first half of the claim check: a message carries the
 * identifier a job is kept under, never its parameters or its recipients.
 */
interface JobStore
{
    /**
     * Keeps the job and gives the opaque identifier it is kept under.
     */
    public function keep(ReportJob $job): string;

    /**
     * @throws JobUnavailable when there is no job under the identifier, or it cannot be read
     */
    public function find(string $id): ReportJob;

    /**
     * Forgets the job; an identifier with no job is ignored.
     */
    public function remove(string $id): void;
}
