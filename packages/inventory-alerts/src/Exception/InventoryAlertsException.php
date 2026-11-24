<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Exception;

use Throwable;

/**
 * Every exception of the library: an input refused, or a proposal decided twice. Its message names the field at
 * fault, never an email address.
 */
interface InventoryAlertsException extends Throwable
{
}
