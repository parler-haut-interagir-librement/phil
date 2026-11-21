<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Messenger;

use Phil\PrivacyBundle\Application\Erasure\ErasureRequests;
use Phil\PrivacyBundle\Application\Message\EraseModule;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * Waits while Messenger will try an `EraseModule` message again, then marks its step, and so its request, as needing
 * attention: an operator replays it once the cause is fixed, or closes the request as failed.
 */
final readonly class ErasureFailureListener
{
    public function __construct(
        private ErasureRequests $requests,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof EraseModule || $event->willRetry()) {
            return;
        }

        $this->requests->giveUp($message->requestId, $message->module);
    }
}
