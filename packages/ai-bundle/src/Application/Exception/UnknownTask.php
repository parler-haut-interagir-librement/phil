<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application\Exception;

use LogicException;
use Phil\AiBundle\Domain\Exception\AiException;

/**
 * A task kind the configuration does not define.
 */
final class UnknownTask extends LogicException implements AiException
{
    public static function of(string $kind): self
    {
        return new self(\sprintf('No task "%s" is defined: add it under phil_ai.tasks.', $kind));
    }
}
