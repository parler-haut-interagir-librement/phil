<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\Exception;

use Throwable;

/**
 * Every exception of the bundle, to catch them at once.
 */
interface SurveyException extends Throwable
{
}
