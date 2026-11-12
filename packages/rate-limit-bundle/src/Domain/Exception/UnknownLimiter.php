<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Domain\Exception;

use LogicException;

/**
 * A rate limit names a limiter or a key strategy nothing declares: a programming error, reported with what is declared.
 */
final class UnknownLimiter extends LogicException implements RateLimitException
{
    /**
     * @param list<string> $declared
     */
    public static function named(string $name, array $declared): self
    {
        return new self(\sprintf('No rate limiter is named "%s"; the limiters declared are %s.', $name, self::list($declared)));
    }

    /**
     * @param list<string> $declared
     */
    public static function key(string $name, array $declared): self
    {
        return new self(\sprintf('No key strategy is named "%s"; the strategies declared are %s.', $name, self::list($declared)));
    }

    /**
     * @param list<string> $names
     */
    private static function list(array $names): string
    {
        return [] === $names ? 'none' : '"'.implode('", "', $names).'"';
    }
}
