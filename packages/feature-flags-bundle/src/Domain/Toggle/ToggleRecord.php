<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Toggle;

use DateTimeImmutable;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;

/**
 * What an applied toggle leaves in the audit trail: who, which flag, the value before and after, why, when.
 */
final readonly class ToggleRecord
{
    public function __construct(
        public FlagName $flag,
        public string $actor,
        public bool $before,
        public bool $after,
        public ToggleReason $reason,
        public DateTimeImmutable $at,
    ) {
    }
}
