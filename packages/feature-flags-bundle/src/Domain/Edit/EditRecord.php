<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Edit;

use DateTimeImmutable;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleReason;

/**
 * What an applied edit leaves in the audit trail: who, which flag, the definition before (none for a creation)
 * and after, why, when. Raising a rollout from 20 % to 100 % changes as much as a toggle, so it is traced as one.
 */
final readonly class EditRecord
{
    public function __construct(
        public FlagName $flag,
        public string $actor,
        public ?FlagDefinition $before,
        public FlagDefinition $after,
        public ToggleReason $reason,
        public DateTimeImmutable $at,
    ) {
    }
}
