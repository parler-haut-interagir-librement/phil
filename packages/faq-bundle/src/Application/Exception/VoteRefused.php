<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Application\Exception;

use Phil\FaqBundle\Domain\Exception\FaqException;
use RuntimeException;

/**
 * A vote whose token is not one the site gave, or no longer valid; or a request that did not pass the CSRF check.
 */
final class VoteRefused extends RuntimeException implements FaqException
{
    public static function invalidToken(): self
    {
        return new self('The vote token is not valid.');
    }

    public static function expiredToken(): self
    {
        return new self('The vote token has expired: the page is to be loaded again.');
    }

    public static function untrustedRequest(): self
    {
        return new self('The request did not pass the CSRF check.');
    }
}
