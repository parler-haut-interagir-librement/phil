<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Application\Message;

/**
 * Asks a worker to publish a snapshot of the page to one target, or to every target when it names none. It carries
 * the target's name only: the page is put together when the message is handled.
 */
final readonly class PushStatusSnapshot
{
    public function __construct(
        public ?string $target = null,
    ) {
    }
}
