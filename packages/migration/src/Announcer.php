<?php

declare(strict_types=1);

namespace Phil\Migration;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Announces a release on every channel. A channel that fails is logged and left, so the others are still told and the
 * migrations, which already ran, are not undone.
 */
final readonly class Announcer
{
    private LoggerInterface $logger;

    /**
     * @param list<Channel> $channels
     */
    public function __construct(
        private array $channels,
        private Publisher $publisher,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function announce(Release $release): void
    {
        foreach ($this->channels as $channel) {
            foreach ($channel->messages($release) as $message) {
                try {
                    $this->publisher->publish($channel->transport, $message);
                } catch (Throwable $throwable) {
                    $this->logger->error('The release could not be announced on {transport}: {reason}', ['transport' => $channel->transport, 'reason' => $throwable->getMessage(), 'exception' => $throwable]);

                    break;
                }
            }
        }
    }
}
