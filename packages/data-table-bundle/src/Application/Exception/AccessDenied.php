<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Exception;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * A voter refused the table, an action or an edit; or the request did not pass the checks of a change.
 */
final class AccessDenied extends AccessDeniedHttpException implements DataTableException
{
    public static function to(string $what, string $attribute): self
    {
        return new self(\sprintf('Access to %s is denied: the attribute "%s" is not granted.', $what, $attribute));
    }

    public static function untrustedRequest(): self
    {
        return new self('The request did not pass the checks of a change: its origin or its token.');
    }
}
