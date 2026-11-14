<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Retention;

use DateTimeImmutable;

/**
 * An instruction to a store's purger: remove what the rule covers and what is older than the cutoff.
 */
final readonly class PurgeOrder
{
    public function __construct(
        public string $ruleId,
        public string $store,
        public DateTimeImmutable $cutoff,
    ) {
    }
}
