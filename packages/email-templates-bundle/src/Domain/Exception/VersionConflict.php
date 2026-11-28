<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\Exception;

use RuntimeException;

/**
 * A draft saved over a revision that is no longer the latest: someone saved it meanwhile. Reload it, then save again.
 */
final class VersionConflict extends RuntimeException implements EmailTemplatesException
{
    public static function expected(string $key, string $locale, int $expected, int $latest): self
    {
        return new self(\sprintf('The email template "%s" (%s) was saved over revision %d, but revision %d is the latest: reload it and save again.', $key, $locale, $expected, $latest));
    }
}
