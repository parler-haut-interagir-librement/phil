<?php

declare(strict_types=1);

namespace Phil\AiBundle\Domain\Exception;

use Throwable;

/**
 * Every exception of the bundle. None carries a prompt, an answer or a key.
 */
interface AiException extends Throwable
{
}
