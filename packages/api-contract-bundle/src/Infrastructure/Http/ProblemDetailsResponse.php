<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Http;

use Phil\ApiContract\Problem\ProblemDetails;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * A problem as RFC 9457 sends it: its members as JSON, its status, `application/problem+json`.
 */
final class ProblemDetailsResponse extends JsonResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(ProblemDetails $problem, array $headers = [])
    {
        parent::__construct($problem->toArray(), $problem->status, ['Content-Type' => ProblemDetails::MEDIA_TYPE, ...$headers]);
    }
}
