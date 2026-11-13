<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Infrastructure\Storage;

use Override;
use Phil\PagedMediaBundle\Application\Exception\JobUnavailable;
use Phil\PagedMediaBundle\Application\Exception\StorageFailed;
use Phil\PagedMediaBundle\Application\Job\ConversionJob;
use Phil\PagedMediaBundle\Application\Job\PipelineJob;
use Phil\PagedMediaBundle\Application\Job\RenderJob;
use Phil\PagedMediaBundle\Application\Port\JobStore;

/**
 * Keeps each job as a JSON file of its own in a directory, named after a random identifier of 32 hexadecimal digits;
 * the directory is made readable by its owner only, and so is each file, since a job holds the documents and the
 * parameters of a file to come. An identifier of another shape names no job.
 */
final readonly class DirectoryJobStore implements JobStore
{
    public function __construct(
        private string $directory,
        private JobCodec $codec = new JobCodec(),
    ) {
    }

    #[Override]
    public function keep(RenderJob|PipelineJob|ConversionJob $job): string
    {
        $contents = $this->codec->encode($job);

        if (!is_dir($this->directory) && !@mkdir($this->directory, 0o700, true)) {
            throw StorageFailed::because(\sprintf('The directory "%s" cannot be made.', $this->directory));
        }

        $id = sodium_bin2hex(random_bytes(16));
        $file = $this->directory.'/'.$id.'.json';

        if (false === @file_put_contents($file, $contents, \LOCK_EX) || !chmod($file, 0o600)) {
            throw StorageFailed::because(\sprintf('The job "%s" cannot be written.', $file));
        }

        return $id;
    }

    #[Override]
    public function find(string $id): RenderJob|PipelineJob|ConversionJob
    {
        $file = $this->file($id);
        $contents = null === $file ? false : @file_get_contents($file);

        if (false === $contents) {
            throw JobUnavailable::because(\sprintf('No job is kept under "%s".', $id));
        }

        return $this->codec->decode($contents);
    }

    #[Override]
    public function remove(string $id): void
    {
        $file = $this->file($id);

        if (null !== $file && is_file($file) && !@unlink($file)) {
            throw StorageFailed::because(\sprintf('The job "%s" cannot be removed.', $file));
        }
    }

    private function file(string $id): ?string
    {
        return 1 === preg_match('/\A[0-9a-f]{32}\z/', $id) ? $this->directory.'/'.$id.'.json' : null;
    }
}
