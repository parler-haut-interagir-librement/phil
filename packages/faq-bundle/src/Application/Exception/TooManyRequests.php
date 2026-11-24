<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Application\Exception;

use Phil\FaqBundle\Domain\Exception\FaqException;
use RuntimeException;

/**
 * More votes or searches than the limit lets through: the next one may come after `retryAfter` seconds.
 */
final class TooManyRequests extends RuntimeException implements FaqException
{
    /**
     * @param positive-int $retryAfter
     */
    public function __construct(
        public readonly int $retryAfter,
    ) {
        parent::__construct(\sprintf('Too many requests: the next one may come in %d seconds.', $retryAfter));
    }
}
