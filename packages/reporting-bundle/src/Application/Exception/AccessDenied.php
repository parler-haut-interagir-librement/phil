<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Exception;

use Phil\ReportingBundle\Domain\Exception\ReportingException;
use RuntimeException;

/**
 * A dashboard restricted to a role the viewer does not have, or restricted while no authorization checker is there to
 * ask.
 */
final class AccessDenied extends RuntimeException implements ReportingException
{
    public static function to(string $dashboard): self
    {
        return new self(\sprintf('Access to the dashboard "%s" is denied.', $dashboard));
    }
}
