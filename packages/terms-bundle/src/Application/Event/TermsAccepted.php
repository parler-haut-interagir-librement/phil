<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Application\Event;

use Phil\TermsBundle\Domain\AcceptanceChannel;

/**
 * A member accepted a version of a policy, by their identifiers.
 */
final readonly class TermsAccepted
{
    public function __construct(
        public string $member,
        public string $policy,
        public int $version,
        public AcceptanceChannel $channel,
    ) {
    }
}
