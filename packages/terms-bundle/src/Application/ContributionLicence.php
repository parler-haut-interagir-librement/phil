<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Application;

use DateTimeImmutable;

/**
 * The licence a member's contributions are published under: the SPDX identifier the version names, the version of the
 * policy the member accepted, and when they accepted it.
 */
final readonly class ContributionLicence
{
    public function __construct(
        public string $licence,
        public string $policy,
        public int $version,
        public DateTimeImmutable $acceptedAt,
    ) {
    }
}
