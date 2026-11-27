<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Http;

use Doctrine\ORM\OptimisticLockException;
use Phil\ApiContract\Problem\ProblemDetails;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Exceptions as problems. A `ProblemException` is answered with its problem wherever it is thrown; under the
 * configured paths, an HTTP exception becomes the blank problem of its status, an `OptimisticLockException` of
 * Doctrine — a write over a version someone else changed — a `409` of the stable type `WRITE_CONFLICT_TYPE`, and any
 * other exception a `500` without detail — except in debug, where Symfony shows it.
 *
 * A request API Platform handles is left to API Platform under the paths too, so that its errors keep their own
 * members — the violations of a `422` among them —: only a `ProblemException`, which the application throws on
 * purpose, is answered there.
 */
final readonly class ProblemDetailsListener
{
    /** The type of the problem of a write over a version someone else changed: reload, then write again. */
    public const string WRITE_CONFLICT_TYPE = 'urn:phil:problem:write-conflict';

    /** How deep the previous exceptions are searched for a write conflict. */
    private const int CHAIN_DEPTH = 8;

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

        $request = $event->getRequest();

        if (ApiPlatformRequests::handles($request) || !$this->covers($request->getPathInfo())) {
            return;
        }

        if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() >= 400) {
            $status = $exception->getStatusCode();
            /** @var array<string, string> $headers */
            $headers = $exception->getHeaders();
            $event->setResponse(new ProblemDetailsResponse(ProblemDetails::create($status, title: Response::$statusTexts[$status] ?? null), $headers));

            return;
        }

        if ($this->isWriteConflict($exception)) {
            $event->setResponse(new ProblemDetailsResponse(ProblemDetails::create(Response::HTTP_CONFLICT, self::WRITE_CONFLICT_TYPE, 'The resource changed since it was read.', 'Read it again, then send the change again.')));

            return;
        }

        if (!$this->debug) {
            $event->setResponse(new ProblemDetailsResponse(ProblemDetails::create(Response::HTTP_INTERNAL_SERVER_ERROR, title: 'Internal Server Error')));
        }
    }

    /**
     * Whether the exception, or one it wraps — as Messenger's `HandlerFailedException` does —, is a write conflict.
     * Neither the entity nor its versions are named: the problem says what to do, not what the row holds.
     */
    private function isWriteConflict(Throwable $exception): bool
    {
        for ($depth = 0, $current = $exception; $current instanceof Throwable && $depth < self::CHAIN_DEPTH; ++$depth, $current = $current->getPrevious()) {
            if ($current instanceof OptimisticLockException) {
                return true;
            }
        }

        return false;
    }

    private function covers(string $path): bool
    {
        return array_any($this->paths, static fn (string $pattern): bool => 1 === preg_match('{'.$pattern.'}', $path));
    }
}
