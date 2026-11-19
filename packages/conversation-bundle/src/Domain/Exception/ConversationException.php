<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Domain\Exception;

use Throwable;

/**
 * What every failure of the bundle's domain is, to catch them together.
 */
interface ConversationException extends Throwable
{
}
