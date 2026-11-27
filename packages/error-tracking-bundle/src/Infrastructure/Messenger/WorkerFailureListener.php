<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\Messenger;

use Phil\ErrorTracking\CaptureContext;
use Phil\ErrorTracking\Severity;
use Phil\ErrorTracking\Source;
use Phil\ErrorTrackingBundle\Application\Message\AlertErrorGroup;
use Phil\ErrorTrackingBundle\Application\SafeCapture;
use Symfony\Component\Messenger\Event\HandlerFailureEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Throwable;
use WeakMap;

/**
 * Captures a message a worker failed to handle, once it will not be retried — or at each attempt, when the
 * configuration asks for it —, tagged with the message's class, its transport and the handler that failed. Each
 * exception a handler threw is captured rather than the wrapper Messenger puts around them, so that a failure groups
 * the same in a worker and in a request. A failed alert of this bundle is never captured: its own failure would raise
 * another alert.
 *
 * Messenger's `HandlerFailureEvent`, dispatched as each handler of a received message fails, names the handler; the
 * name is kept for the exception, weakly, until the worker's failure event reads it. Nothing outlives the message:
 * the map lets go of an exception once it is gone, and `reset()` empties it between messages.
 */
final readonly class WorkerFailureListener
{
    /**
     * The handler that threw each exception of the message being handled.
     *
     * @var WeakMap<Throwable, string>
     */
    private WeakMap $handlers;

    public function __construct(
        private SafeCapture $capture,
        private bool $retries = false,
    ) {
        $this->handlers = new WeakMap();
    }

    public function onHandlerFailed(HandlerFailureEvent $event): void
    {
        if ($event->envelope->last(ReceivedStamp::class) instanceof StampInterface) {
            $this->handlers[$event->exception] = $event->handlerDescriptor->getName();
        }
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        if ($message instanceof AlertErrorGroup || ($event->willRetry() && !$this->retries)) {
            return;
        }

        $throwable = $event->getThrowable();
        $throwables = $throwable instanceof HandlerFailedException ? $throwable->getWrappedExceptions() : [];

        foreach ([] === $throwables ? [$throwable] : $throwables as $failure) {
            $context = [
                'message' => $message::class,
                'transport' => $event->getReceiverName(),
                'will_retry' => $event->willRetry(),
            ];

            if (isset($this->handlers[$failure])) {
                $context['handler'] = $this->handlers[$failure];
            }

            $this->capture->throwable($failure, new CaptureContext(Source::Messenger, Severity::Error, null, $context));
        }
    }

    /**
     * Forgets the handlers of the message just handled.
     */
    public function reset(): void
    {
        $exceptions = [];

        foreach ($this->handlers as $exception => $handler) {
            $exceptions[] = $exception;
        }

        foreach ($exceptions as $exception) {
            unset($this->handlers[$exception]);
        }
    }
}
