<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Infrastructure\WebSub\Exception;

use RuntimeException;

/**
 * A hub did not take the announcement: no answer in time, or an answer other than `2xx`. Messenger retries the
 * message by its retry strategy; the hubs that took it are told again, which WebSub allows.
 */
final class HubNotificationFailed extends RuntimeException
{
    /**
     * @param list<string> $hubs
     */
    public static function at(string $feed, array $hubs): self
    {
        return new self(\sprintf('The hubs %s did not take the update of the feed "%s".', implode(', ', $hubs), $feed));
    }
}
