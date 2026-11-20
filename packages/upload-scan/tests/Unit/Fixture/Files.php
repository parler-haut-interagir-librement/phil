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
     * A ZIP archive of stored (uncompressed) entries, built byte by byte so that no extension is needed: a local
     * header and the data of each entry, then the central directory and its end record.
     *
     * @param array<string, string> $entries the contents of each entry, by name
     */
    public static function zip(array $entries): string
    {
        $archive = '';
        $directory = '';

        foreach ($entries as $name => $contents) {
            $offset = \strlen($archive);
            $common = pack('vvvvvVVVvv', 20, 0, 0, 0, 0x21, crc32($contents), \strlen($contents), \strlen($contents), \strlen($name), 0);
            $archive .= 'PK'."\x03\x04".$common.$name.$contents;
            $directory .= 'PK'."\x01\x02".pack('v', 20).$common.pack('vvvVV', 0, 0, 0, 0, $offset).$name;
        }

        return $archive.$directory.'PK'."\x05\x06".pack('vvvvVVv', 0, 0, \count($entries), \count($entries), \strlen($directory), \strlen($archive), 0);
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
