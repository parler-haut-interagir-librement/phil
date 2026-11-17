<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Exception;

use Throwable;

/**
 * Every exception the library throws on purpose: a caller catches this one to handle them all.
 */
interface ElearningPackagesException extends Throwable
{
}
