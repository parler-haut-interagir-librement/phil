<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Exception;

use Throwable;

/**
 * Every exception the bundle throws on purpose: a caller catches this one to handle them all.
 */
interface CoursesException extends Throwable
{
}
