<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Application\Event;

/**
 * A version of a policy was published, by its identifiers.
 */
final readonly class VersionPublished
{
    public function __construct(
        public string $policy,
        public int $version,
        public bool $material,
    ) {
    }
}
