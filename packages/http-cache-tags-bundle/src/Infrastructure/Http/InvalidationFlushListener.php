<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Infrastructure\Http;

use Phil\HttpCacheTagsBundle\Application\Invalidator;
use Phil\HttpCacheTagsBundle\Infrastructure\Purger\PurgeFailed;
use Psr\Log\LoggerInterface;

/**
 * Purges the tags gathered during a request, once its response is sent; during a command, once it ends; during a
 * Messenger worker's message, once it is handled or has failed. A purge the cache refused is logged, so that a
 * response already sent or a worker keeps going; without a logger, it is thrown.
 */
final readonly class InvalidationFlushListener
{
    public function __construct(
        private Invalidator $invalidator,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(): void
    {
        try {
            $this->invalidator->flush();
        } catch (PurgeFailed $purgeFailed) {
            if (!$this->logger instanceof LoggerInterface) {
                throw $purgeFailed;
            }

            $this->logger->error('The cache tags were not purged: {reason}', ['reason' => $purgeFailed->getMessage(), 'exception' => $purgeFailed]);
        }
    }
}
