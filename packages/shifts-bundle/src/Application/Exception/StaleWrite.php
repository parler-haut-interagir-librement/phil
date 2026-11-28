<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Application\Exception;

use RuntimeException;

/**
 * Another request wrote first: the transaction rolls back, and the operation reads again.
 *
 * @internal
 */
final class StaleWrite extends RuntimeException
{
}
