<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application;

use Phil\MediaBundle\Application\Contract\StreamingMediaStorage;

/**
 * Opens a stored file as a stream on any storage: through `StreamingMediaStorage::readStream()` when the storage
 * streams, or from its contents read whole when it does not.
 *
 * @internal
 */
final class MediaStreams
{
    /**
     * Above this size, the contents of a storage that does not stream go to a temporary file instead of memory.
     */
    private const int MEMORY_LIMIT = 2_097_152;

    private function __construct()
    {
    }

    /**
     * @return resource|null
     */
    public static function open(MediaStorage $storage, string $key): mixed
    {
        if ($storage instanceof StreamingMediaStorage) {
            return $storage->readStream($key);
        }

        $contents = $storage->read($key);

        if (null === $contents) {
            return null;
        }

        $stream = fopen('php://temp/maxmemory:'.self::MEMORY_LIMIT, 'w+');
        \assert(false !== $stream);
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }
}
