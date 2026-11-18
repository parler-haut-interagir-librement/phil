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
 *
 * A job's file is readable by its owner from the moment it exists and appears whole. A directory open to other users
 * is narrowed to its owner, or refused when `tightenOpenDirectory` is false.
 */
final readonly class DirectoryJobStore implements JobStore
{
    private PrivateDirectory $directory;

    public function __construct(string $directory, bool $tightenOpenDirectory = true)
    {
        $this->directory = new PrivateDirectory($directory, '.json', $tightenOpenDirectory);
    }

    #[Override]
    public function keep(ReportJob $job): string
    {
        $json = json_encode($job->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION);
        [$id] = $this->directory->keep($json, static fn (string $file): RuntimeException => new RuntimeException(\sprintf('The report job cannot be written to "%s".', $file)));

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
