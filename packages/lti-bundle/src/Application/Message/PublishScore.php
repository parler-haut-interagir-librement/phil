<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application\Message;

/**
 * Sends a pending score to its platform: the message carries the identifier of the score only, which the handler reads
 * from the store.
 */
final readonly class PublishScore
{
    public function __construct(
        public string $id,
    ) {
    }
}
