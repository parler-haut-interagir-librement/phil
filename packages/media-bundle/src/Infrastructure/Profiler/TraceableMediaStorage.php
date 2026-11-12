<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Profiler;

use Override;
use Phil\MediaBundle\Application\MediaStorage;

/**
 * The storage, each read, write and deletion noted in the trace; registered in debug only.
 */
final readonly class TraceableMediaStorage implements MediaStorage
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

    #[Override]
    public function deletePrefix(string $prefix): void
    {
        $this->storage->deletePrefix($prefix);
        $this->trace->stored('delete', $prefix);
    }
}
