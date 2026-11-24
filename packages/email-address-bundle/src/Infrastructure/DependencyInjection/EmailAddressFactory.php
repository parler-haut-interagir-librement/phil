<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Infrastructure\DependencyInjection;

use Phil\EmailAddress\DisposableDomains;

/**
 * The objects the container builds from the configuration.
 *
 * @internal
 */
final class EmailAddressFactory
{
    /**
     * The disposable domains of the library's list, with those the configuration adds.
     *
     * @param list<string> $extra
     */
    public static function disposableDomains(array $extra): DisposableDomains
    {
        return [] === $extra ? DisposableDomains::bundled() : DisposableDomains::bundled()->merge(DisposableDomains::of($extra));
    }
}
