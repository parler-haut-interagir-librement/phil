<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Memory;

use Override;
use Phil\ReportingBundle\Application\Contract\JobStore;
use Phil\ReportingBundle\Application\Exception\JobUnavailable;
use Phil\ReportingBundle\Application\Report\ReportJob;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The jobs in memory, for the tests and the `sync://` transport: forgotten when the container is reset.
 */
final class InMemoryJobStore implements JobStore, ResetInterface
{
    /** @var array<string, ReportJob> */
    private array $jobs = [];

    #[Override]
    public function keep(ReportJob $job): string
    {
        $id = sodium_bin2hex(random_bytes(16));
        $this->jobs[$id] = $job;

        return $id;
    }

    #[Override]
    public function find(string $id): ReportJob
    {
        return $this->jobs[$id] ?? throw JobUnavailable::of($id, 'no job is kept under it');
    }

    #[Override]
    public function remove(string $id): void
    {
        unset($this->jobs[$id]);
    }

    /**
     * The identifiers of the jobs kept.
     *
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->jobs);
    }

    #[Override]
    public function reset(): void
    {
        $this->jobs = [];
    }
}
