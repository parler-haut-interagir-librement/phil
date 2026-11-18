<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application\Exception;

use Phil\OrganizationsBundle\Domain\Exception\OrganizationsException;
use RuntimeException;

/**
 * The slug is another organization's, or another team's of the same organization.
 */
final class SlugTaken extends RuntimeException implements OrganizationsException
{
    public static function of(string $slug): self
    {
        return new self(\sprintf('The slug "%s" is taken.', $slug));
    }
}
