<?php

declare(strict_types=1);

namespace Phil\Rules\Exception;

use Throwable;

/**
 * Every exception the library throws, for an application that catches them together.
 */
interface RulesException extends Throwable
{
}
