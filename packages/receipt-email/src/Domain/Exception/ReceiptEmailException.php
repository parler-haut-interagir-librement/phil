<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Domain\Exception;

use Throwable;

/**
 * Every exception the package throws, to catch them together.
 */
interface ReceiptEmailException extends Throwable
{
}
