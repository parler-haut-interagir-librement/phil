<?php

declare(strict_types=1);

namespace Phil\UploadScan\Tests\Unit\Fixture;

use LogicException;

/**
 * Streams and files of the tests.
 */
final class Files
{
    /**
     * @return resource
     */
    public static function stream(string $contents): mixed
    {
        $stream = fopen('php://memory', 'r+');

        if (false === $stream) {
            throw new LogicException('No memory stream.');
        }

        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    public static function path(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'phil-upload-scan-');

        if (false === $path) {
            throw new LogicException('No temporary file.');
        }

        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * A path for a Unix socket: under the temporary directory when it is short enough, since a socket path holds at
     * most 104 bytes on some systems, under /tmp otherwise.
     */
    public static function socketPath(): string
    {
        $directory = \strlen(sys_get_temp_dir()) < 64 ? sys_get_temp_dir() : '/tmp';

        return $directory.'/phil-clamd-'.bin2hex(random_bytes(4)).'.sock';
    }
}
