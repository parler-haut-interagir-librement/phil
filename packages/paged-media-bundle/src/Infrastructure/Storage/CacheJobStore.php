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
use Psr\Cache\CacheException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Keeps each job in a PSR-6 cache pool, under a random identifier of 32 hexadecimal digits: a pool of its own, shared
 * by every worker — Redis, a database, a filesystem pool —, such as the one a Messenger transport's claim check uses.
 * The pool must keep an item as long as a message may wait and be retried; a lifetime, when given, bounds how long a
 * job forgotten by a failure stays. An identifier of another shape names no job.
 */
final readonly class CacheJobStore implements JobStore
{
    /**
     * @param int|null $lifetime how long a job is kept, in seconds; the pool's default when null
     */
    public function __construct(
        private CacheItemPoolInterface $pool,
        private JobCodec $codec = new JobCodec(),
        private ?int $lifetime = null,
    ) {
    }

    #[Override]
    public function keep(RenderJob|PipelineJob|ConversionJob $job): string
    {
        $contents = $this->codec->encode($job);
        $id = sodium_bin2hex(random_bytes(16));

        try {
            $item = $this->pool->getItem($id)->set($contents)->expiresAfter($this->lifetime);
            $saved = $this->pool->save($item);
        } catch (CacheException $cacheException) {
            throw StorageFailed::because(\sprintf('The job cannot be kept in the pool: %s', $cacheException->getMessage()), $cacheException);
        }

        if (!$saved) {
            throw StorageFailed::because('The job cannot be kept in the pool.');
        }

        return $id;
    }

    #[Override]
    public function find(string $id): RenderJob|PipelineJob|ConversionJob
    {
        try {
            $item = $this->valid($id) ? $this->pool->getItem($id) : null;
        } catch (CacheException $cacheException) {
            throw StorageFailed::because(\sprintf('The pool cannot be read: %s', $cacheException->getMessage()), $cacheException);
        }

        $contents = $item instanceof CacheItemInterface && $item->isHit() ? $item->get() : null;

        if (!\is_string($contents)) {
            throw JobUnavailable::because(\sprintf('No job is kept under "%s".', $id));
        }

        return $this->codec->decode($contents);
    }

    #[Override]
    public function remove(string $id): void
    {
        try {
            $removed = !$this->valid($id) || $this->pool->deleteItem($id);
        } catch (CacheException $cacheException) {
            throw StorageFailed::because(\sprintf('The job "%s" cannot be removed: %s', $id, $cacheException->getMessage()), $cacheException);
        }

        if (!$removed) {
            throw StorageFailed::because(\sprintf('The job "%s" cannot be removed.', $id));
        }
    }

    private function valid(string $id): bool
    {
        return 1 === preg_match('/\A[0-9a-f]{32}\z/', $id);
    }
}
