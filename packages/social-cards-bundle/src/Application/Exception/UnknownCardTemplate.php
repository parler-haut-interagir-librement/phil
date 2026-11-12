<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Application\Exception;

use InvalidArgumentException;

/**
 * A card template the configuration does not declare.
 */
final class UnknownCardTemplate extends InvalidArgumentException
{
    /**
     * @param list<string> $known
     */
    public static function named(string $name, array $known): self
    {
        return new self(\sprintf('No card template is named "%s"; the configuration declares "%s".', $name, implode('", "', $known)));
    }
}
