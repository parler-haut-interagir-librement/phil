<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application\Exception;

use InvalidArgumentException;

/**
 * A report body that is not JSON, or not in the shape of either report format.
 */
final class InvalidReport extends InvalidArgumentException
{
}
