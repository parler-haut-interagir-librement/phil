<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Store;

use Closure;
use Phil\ReportingBundle\Application\Exception\JobUnavailable;
use Phil\ReportingBundle\Application\Report\ReportStream;
use RuntimeException;
use Throwable;

/**
 * A directory readable by its owner only, whose files are named by 32 random hexadecimal digits and a suffix: a name
 * from a message is checked against that shape before it is joined to the directory.
 *
 * The directory is created with mode 0700. One that already exists and is open to its group or to other users is
 * narrowed to 0700 before a file is kept in it, or refused when it cannot be narrowed or when `tightenOpenDirectory`
 * is false. A file is written as a temporary file of the directory, readable by its owner from the moment it exists,
 * then given its name in one step; a failure leaves nothing behind.
 *
 * @internal
 */
final readonly class PrivateDirectory
{
    public function __construct(
        private string $path,
        private string $suffix,
        private bool $tightenOpenDirectory = true,
    ) {
    }

    /**
     * Writes the content to a new file, the bytes given or what is left of the stream given, and gives its identifier
     * back with its size in bytes. A file that cannot be created, written whole, closed or named fails with the
     * exception the failure gives for its path; an exception of the stream passes through.
     *
     * @param Closure(string): Throwable $failure
     *
     * @return array{string, int}
     */
    public function keep(string|ReportStream $content, Closure $failure): array
    {
        $this->prepare();
        $id = sodium_bin2hex(random_bytes(16));
        $file = $this->file($id);
        $temporary = $this->temporary($file, $failure);

        try {
            $handle = @fopen($temporary, 'w');

            if (false === $handle) {
                throw $failure($file);
            }

            try {
                if (\is_string($content)) {
                    $size = \strlen($content);
                    $whole = @fwrite($handle, $content) === $size;
                } else {
                    $size = $content->copyTo(ReportStream::of($handle));
                    $whole = true;
                }
            } finally {
                $flushed = fflush($handle);
                $closed = fclose($handle);
            }

            if (!$whole) {
                throw $failure($file);
            }

            if (!$flushed) {
                throw $failure($file);
            }

            if (!$closed) {
                throw $failure($file);
            }

            if (!@rename($temporary, $file)) {
                throw $failure($file);
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }

        return [$id, $size];
    }

    /**
     * The path of the identifier's file.
     *
     * @throws JobUnavailable when the identifier is not 32 hexadecimal digits
     */
    public function file(string $id): string
    {
        if (1 !== preg_match('/^[0-9a-f]{32}$/D', $id)) {
            throw JobUnavailable::of($id, 'not an identifier of the store');
        }

        return $this->path.'/'.$id.$this->suffix;
    }

    public function remove(string $id): void
    {
        $file = $this->file($id);

        if (is_file($file)) {
            unlink($file);
        }
    }

    /**
     * Creates the directory readable by its owner only, or narrows it to its owner when it is open to others.
     */
    private function prepare(): void
    {
        if (!is_dir($this->path)) {
            @mkdir($this->path, 0o700, true);
        }

        // Checked again rather than trusted to mkdir(): another worker may have created it at the same moment.
        if (!is_dir($this->path)) {
            throw new RuntimeException(\sprintf('The directory "%s" cannot be created.', $this->path));
        }

        // Windows has no group or other users' bits to narrow.
        if ('Windows' === \PHP_OS_FAMILY) {
            return;
        }

        clearstatcache(true, $this->path);
        $mode = fileperms($this->path) & 0o777;

        if (0 === ($mode & 0o077)) {
            return;
        }

        if (!$this->tightenOpenDirectory) {
            throw new RuntimeException(\sprintf('The directory "%s" is open to other users (mode %04o): narrow it to its owner, with mode 0700.', $this->path, $mode));
        }

        if (!@chmod($this->path, 0o700)) {
            throw new RuntimeException(\sprintf('The directory "%s" is open to other users (mode %04o) and cannot be narrowed to its owner: give it to the user that runs the application, with mode 0700.', $this->path, $mode));
        }
    }

    /**
     * A new empty file of the directory, readable and writable by its owner only from the moment it exists:
     * tempnam() creates it so. It gives the directory without its symbolic links, and falls back to the system's
     * when it cannot write in it: a file elsewhere is removed and refused.
     *
     * @param Closure(string): Throwable $failure
     */
    private function temporary(string $file, Closure $failure): string
    {
        $directory = (string) realpath($this->path);
        $temporary = @tempnam($directory, '.phil-');

        if (false === $temporary || \dirname($temporary) !== $directory) {
            if (\is_string($temporary)) {
                unlink($temporary);
            }

            throw $failure($file);
        }

        return $temporary;
    }
}
