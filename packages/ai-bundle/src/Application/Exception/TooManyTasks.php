<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application\Exception;

use Phil\AiBundle\Domain\Exception\AiException;
use RuntimeException;

/**
 * A task refused before it ran: the subject already runs as many tasks at once as the quota allows. Nothing was
 * counted nor called; the message never names the subject.
 */
final class TooManyTasks extends RuntimeException implements AiException
{
    public static function atOnce(int $concurrency): self
    {
        return new self(\sprintf('The subject already runs %d tasks at once, the most the quota allows.', $concurrency));
    }
}
