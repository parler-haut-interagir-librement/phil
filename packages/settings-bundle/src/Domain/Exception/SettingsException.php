<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Domain\Exception;

use Throwable;

/**
 * Every exception the bundle throws, to catch them all at once.
 */
interface SettingsException extends Throwable
{
}
