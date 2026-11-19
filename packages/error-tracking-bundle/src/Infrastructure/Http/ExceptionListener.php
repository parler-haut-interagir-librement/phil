<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\Http;

use Phil\ErrorTracking\CaptureContext;
use Phil\ErrorTracking\Severity;
use Phil\ErrorTracking\Source;
use Phil\ErrorTrackingBundle\Application\SafeCapture;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Captures the exception a request ended in, with the summary of the request, before the kernel logs it. An HTTP
 * exception of a client error — a 404, a 403 — is left out unless the configuration asks for it: it is the visitor's
 * mistake, not the application's.
 */
final readonly class ExceptionListener
{
    public function __construct(
        private SafeCapture $capture,
        private RequestSummaries $summaries,
        private bool $clientErrors = false,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if (!$this->clientErrors && $throwable instanceof HttpExceptionInterface && $throwable->getStatusCode() < 500) {
            return;
        }

        $this->capture->throwable($throwable, new CaptureContext(Source::Http, Severity::Error, $this->summaries->summarize($event->getRequest())));
    }
}
