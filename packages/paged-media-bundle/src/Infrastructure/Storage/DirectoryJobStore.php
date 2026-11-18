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
 * Keeps each job as a JSON file of its own in a directory, named after a random identifier of 32 hexadecimal digits,
 * readable by its owner only, since a job holds the documents and the parameters of a file to come. An identifier of
 * another shape names no job.
 *
 * The directory is created with mode 0700. One that already exists and is open to its group or to other users is
 * narrowed to 0700 before a job is kept in it, or refused when it cannot be narrowed or when `tightenOpenDirectory`
 * is false. A job is written as a temporary file of the directory, readable by its owner from the moment it exists,
 * then given its name in one step; a failure leaves nothing behind.
 */
final readonly class DirectoryJobStore implements JobStore
{
    public function __construct(
        private string $directory,
        private JobCodec $codec = new JobCodec(),
        private bool $tightenOpenDirectory = true,
    ) {
    }

    #[Override]
    public function keep(RenderJob|PipelineJob|ConversionJob $job): string
    {
        $contents = $this->codec->encode($job);
        $this->prepare();
        $id = sodium_bin2hex(random_bytes(16));
        $file = $this->directory.'/'.$id.'.json';
        $temporary = $this->temporary($file);

        try {
            if (false === @file_put_contents($temporary, $contents) || !@rename($temporary, $file)) {
                throw StorageFailed::because(\sprintf('The job "%s" cannot be written.', $file));
            }
        } finally {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
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

    /**
     * Creates the directory readable by its owner only, or narrows it to its owner when it is open to others.
     */
    private function prepare(): void
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0o700, true);
        }

        // Checked again rather than trusted to mkdir(): another worker may have created it at the same moment.
        if (!is_dir($this->directory)) {
            throw StorageFailed::because(\sprintf('The directory "%s" cannot be made.', $this->directory));
        }

        // Windows has no group or other users' bits to narrow.
        if ('Windows' === \PHP_OS_FAMILY) {
            return;
        }

        clearstatcache(true, $this->directory);
        $mode = fileperms($this->directory) & 0o777;

        if (0 === ($mode & 0o077)) {
            return;
        }

        if (!$this->tightenOpenDirectory) {
            throw StorageFailed::because(\sprintf('The directory "%s" is open to other users (mode %04o): narrow it to its owner, with mode 0700.', $this->directory, $mode));
        }

        if (!@chmod($this->directory, 0o700)) {
            throw StorageFailed::because(\sprintf('The directory "%s" is open to other users (mode %04o) and cannot be narrowed to its owner: give it to the user that runs the workers, with mode 0700.', $this->directory, $mode));
        }
    }

    /**
     * A new empty file of the directory, readable and writable by its owner only from the moment it exists:
     * tempnam() creates it so. It gives the directory without its symbolic links, and falls back to the system's
     * when it cannot write in it: a file elsewhere is removed and refused.
     */
    private function temporary(string $file): string
    {
        $directory = (string) realpath($this->directory);
        $temporary = @tempnam($directory, '.job-');

        if (false === $temporary || \dirname($temporary) !== $directory) {
            if (\is_string($temporary)) {
                unlink($temporary);
            }

            throw StorageFailed::because(\sprintf('The job "%s" cannot be written.', $file));
        }

        return $temporary;
    }
}
