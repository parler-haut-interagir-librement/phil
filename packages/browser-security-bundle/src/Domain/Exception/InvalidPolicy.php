<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A directive, a source or a header value that a policy refuses, with the reason in its message.
 */
final class InvalidPolicy extends InvalidArgumentException
{
}
