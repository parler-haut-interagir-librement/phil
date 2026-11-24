<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Application\Exception;

use Phil\FaqBundle\Domain\Exception\FaqException;
use RuntimeException;

/**
 * The token of this page already voted for this question.
 */
final class AlreadyVoted extends RuntimeException implements FaqException
{
    public static function once(): self
    {
        return new self('This page already voted for this question.');
    }
}
