<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Http;

use Phil\ApiContract\Exception\ApiContractException;
use Phil\ApiContract\Problem\ProblemDetails;
use RuntimeException;
use Throwable;

/**
 * A problem to answer with: thrown anywhere in a request, it becomes an `application/problem+json` response of its
 * status, with the headers given — a `Retry-After`, a `WWW-Authenticate`.
 */
final class ProblemException extends RuntimeException implements ApiContractException
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly ProblemDetails $problem,
        public readonly array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($problem->title ?? $problem->type, $problem->status, $previous);
    }

    public static function of(int $status, ?string $title = null, ?string $detail = null, ?Throwable $previous = null): self
    {
        return new self(ProblemDetails::create($status, title: $title, detail: $detail), previous: $previous);
    }
}
