<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Exception;

use RuntimeException;
use Throwable;

/**
 * An answer of Gotenberg that is not a success, or no answer at all. It keeps the HTTP status — 0 when Gotenberg could
 * not be reached — and the request's trace identifier, which finds the request in Gotenberg's logs.
 */
abstract class GotenbergFailure extends RuntimeException implements GotenbergException
{
    final public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $trace = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    /**
     * The exception for a status: `RequestRefused` for a request Gotenberg refuses, `AccessDenied` for missing
     * credentials or a URL its outbound filter blocks, `RouteUnavailable` for a route or an engine the image lacks,
     * `ServiceUnavailable` for a full queue or a timeout, worth retrying, and `ConversionFailed` for the rest.
     */
    public static function fromStatus(int $status, string $message, ?string $trace = null): self
    {
        $text = '' === trim($message) ? \sprintf('Gotenberg answered %d.', $status) : \sprintf('Gotenberg answered %d: %s', $status, trim($message));

        return match (true) {
            401 === $status, 403 === $status => new AccessDenied($text, $status, $trace),
            404 === $status => new RouteUnavailable($text, $status, $trace),
            429 === $status, 503 === $status => new ServiceUnavailable($text, $status, $trace),
            409 === $status, $status >= 500 => new ConversionFailed($text, $status, $trace),
            default => new RequestRefused($text, $status, $trace),
        };
    }

    /**
     * Whether the same request may succeed later: a full queue, a timeout, or Gotenberg out of reach.
     */
    public function isRetryable(): bool
    {
        return false;
    }
}
