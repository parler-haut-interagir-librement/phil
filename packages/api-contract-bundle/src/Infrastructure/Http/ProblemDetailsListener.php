<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Http;

use Phil\ApiContract\Problem\ProblemDetails;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Exceptions as problems. A `ProblemException` is answered with its problem wherever it is thrown; under the
 * configured paths, an HTTP exception becomes the blank problem of its status, and any other exception a `500`
 * without detail — except in debug, where Symfony shows it.
 */
final readonly class ProblemDetailsListener
{
    /**
     * @param list<string> $paths regular expressions on the path
     */
    public function __construct(
        private array $paths = [],
        private bool $debug = false,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof ProblemException) {
            $event->setResponse(new ProblemDetailsResponse($exception->problem, $exception->headers));

            return;
        }

        if (!$this->covers($event->getRequest()->getPathInfo())) {
            return;
        }

        if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() >= 400) {
            $status = $exception->getStatusCode();
            /** @var array<string, string> $headers */
            $headers = $exception->getHeaders();
            $event->setResponse(new ProblemDetailsResponse(ProblemDetails::create($status, title: Response::$statusTexts[$status] ?? null), $headers));

            return;
        }

        if (!$this->debug) {
            $event->setResponse(new ProblemDetailsResponse(ProblemDetails::create(Response::HTTP_INTERNAL_SERVER_ERROR, title: 'Internal Server Error')));
        }
    }

    private function covers(string $path): bool
    {
        return array_any($this->paths, static fn (string $pattern): bool => 1 === preg_match('{'.$pattern.'}', $path));
    }
}
