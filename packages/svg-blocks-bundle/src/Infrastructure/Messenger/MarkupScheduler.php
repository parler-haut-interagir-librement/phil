<?php

declare(strict_types=1);

namespace Phil\SvgBlocksBundle\Infrastructure\Messenger;

use Phil\SvgBlocksBundle\Application\Message\RenderDeferredMarkup;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Sends the message that draws the pending blocks of a saved document in the background, by the bus the application
 * routes it on — a transport of its own keeps a slow diagram service off the requests.
 */
final readonly class MarkupScheduler
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function schedule(string $subject): void
    {
        $this->bus->dispatch(new RenderDeferredMarkup($subject));
    }
}
