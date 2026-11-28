<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Exception;

use Throwable;

/**
 * Every exception of the library. Its message names the order, the field or the state at fault, never a pickup code
 * nor the name of the person who collects.
 */
interface LocalPickupException extends Throwable
{
}
