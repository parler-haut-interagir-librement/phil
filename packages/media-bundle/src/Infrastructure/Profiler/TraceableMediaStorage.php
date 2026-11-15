<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Profiler;

use Override;
use Phil\MediaBundle\Application\Contract\StreamingMediaStorage;
use Phil\MediaBundle\Application\MediaStorage;
use Phil\MediaBundle\Application\MediaStreams;

/**
 * The storage, each read, stream, write and deletion noted in the trace; registered in debug only.
 */
final readonly class TraceableMediaStorage implements StreamingMediaStorage
{
    public function __construct(
        private MediaStorage $storage,
        private MediaTrace $trace,
    ) {
    }

    #[Override]
    public function write(string $key, string $contents): void
    {
        $this->storage->write($key, $contents);
        $this->trace->stored('write', $key, \strlen($contents));
    }

    #[Override]
    public function read(string $key): ?string
    {
        $contents = $this->storage->read($key);
        $this->trace->stored('read', $key, \strlen($contents ?? ''), null !== $contents);

        return $contents;
    }

    /**
     * The file's size, when the stream knows it, is noted; the stream itself is handed over unread.
     *
     * @return resource|null
     */
    #[Override]
    public function readStream(string $key): mixed
    {
        $stream = MediaStreams::open($this->storage, $key);
        $this->trace->stored('stream', $key, null === $stream ? 0 : (fstat($stream)['size'] ?? 0), null !== $stream);

        return $stream;
    }

    #[Override]
    public function deletePrefix(string $prefix): void
    {
        $this->storage->deletePrefix($prefix);
        $this->trace->stored('delete', $prefix);
    }
}
