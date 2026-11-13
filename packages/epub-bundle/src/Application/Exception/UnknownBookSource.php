<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Application\Exception;

use InvalidArgumentException;
use Phil\Epub\Exception\EpubException;

/**
 * A book source name no service is registered under; the message lists the names known.
 */
final class UnknownBookSource extends InvalidArgumentException implements EpubException
{
    /**
     * @param list<string> $known
     */
    public static function named(string $name, array $known): self
    {
        return new self(\sprintf('There is no book source "%s"; the sources are "%s".', $name, implode('", "', $known)));
    }
}
