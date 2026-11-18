<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Application\Exception;

use Phil\IncidentModeBundle\Application\Suspension;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * What a checkpoint throws when an incident suspends what the request asks for: an HTTP error, 503 unless the
 * configuration chooses another status, with a `Retry-After` header that says when to come back. The bundle renders
 * it as the page of the suspension; without Twig, Symfony renders it as any HTTP error.
 */
final class SuspendedByIncident extends HttpException
{
    /**
     * @param positive-int $retryAfter seconds
     */
    public function __construct(
        public readonly Suspension $suspension,
        int $status,
        public readonly int $retryAfter,
    ) {
        parent::__construct($status, \sprintf('"%s" is suspended by an incident until %s.', $suspension->restriction, $suspension->until->format(\DATE_ATOM)), null, ['Retry-After' => (string) $retryAfter]);
    }
}
