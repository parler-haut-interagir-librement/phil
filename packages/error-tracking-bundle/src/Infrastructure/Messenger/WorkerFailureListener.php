<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\Messenger;

use Phil\ErrorTracking\CaptureContext;
use Phil\ErrorTracking\Severity;
use Phil\ErrorTracking\Source;
use Phil\ErrorTrackingBundle\Application\Message\AlertErrorGroup;
use Phil\ErrorTrackingBundle\Application\SafeCapture;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Captures a message a worker failed to handle, once it will not be retried — or at each attempt, when the
 * configuration asks for it —, tagged with the message's class and its transport. The exception a handler threw is
 * captured rather than the wrapper Messenger puts around it, so that a failure groups the same in a worker and in a
 * request. A failed alert of this bundle is never captured: its own failure would raise another alert.
 */
final readonly class WorkerFailureListener
{
    public function __construct(
        private SafeCapture $capture,
        private bool $retries = false,
    ) {
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        if ($message instanceof AlertErrorGroup || ($event->willRetry() && !$this->retries)) {
            return;
        }

        $throwable = $event->getThrowable();

        if ($throwable instanceof HandlerFailedException) {
            $throwable = array_first($throwable->getWrappedExceptions()) ?? $throwable;
        }

        $this->capture->throwable($throwable, new CaptureContext(Source::Messenger, Severity::Error, null, [
            'message' => $message::class,
            'transport' => $event->getReceiverName(),
            'will_retry' => $event->willRetry(),
        ]));
    }
}
