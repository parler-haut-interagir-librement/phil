<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Domain\Exception;

use Throwable;

/**
 * Everything the bundle throws, catchable with a single `catch`.
 */
interface MentionsException extends Throwable
{
}
