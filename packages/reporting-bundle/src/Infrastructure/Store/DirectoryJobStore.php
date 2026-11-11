<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Store;

use JsonException;
use Override;
use Phil\ReportingBundle\Application\Contract\JobStore;
use Phil\ReportingBundle\Application\Exception\JobUnavailable;
use Phil\ReportingBundle\Application\Report\ReportJob;
use RuntimeException;

/**
 * The jobs as JSON files of their own in a directory readable by its owner only, since a job holds its recipients and
 * parameters. Nothing is unserialized: a file `ReportJob::toArray()` did not write is refused.
 */
final readonly class DirectoryJobStore implements JobStore
{
    private PrivateDirectory $directory;

    public function __construct(string $directory)
    {
        $this->directory = new PrivateDirectory($directory, '.json');
    }

    #[Override]
    public function keep(ReportJob $job): string
    {
        [$id, $file] = $this->directory->create();

        if (false === @file_put_contents($file, json_encode($job->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION), \LOCK_EX)) {
            throw new RuntimeException(\sprintf('The report job cannot be written to "%s".', $file));
        }

        chmod($file, 0o600);

        return $id;
    }

    #[Override]
    public function find(string $id): ReportJob
    {
        $file = $this->directory->file($id);
        $json = is_file($file) ? file_get_contents($file) : false;

        if (false === $json) {
            throw JobUnavailable::of($id, 'no job is kept under it');
        }

        try {
            $job = ReportJob::fromArray(json_decode($json, true, 16, \JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            $job = null;
        }

        return $job ?? throw JobUnavailable::of($id, 'its file is not a job of the store');
    }

    #[Override]
    public function remove(string $id): void
    {
        $this->directory->remove($id);
    }
}
