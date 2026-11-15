<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\IncidentMode;

use Phil\IncidentMode\IncidentRestrictions;
use Phil\ModerationBundle\Application\Contract\CurrentMember;

/**
 * Whether a restriction of the incident mode applies to the member of the current request: `IncidentRestrictions` of
 * `phil/incident-mode` decides, given the identifier `CurrentMember` reads, or `null` for a visitor, who is never
 * spared.
 */
final readonly class CurrentMemberRestrictions
{
    public function __construct(
        private IncidentRestrictions $restrictions,
        private CurrentMember $currentMember,
    ) {
    }

    /**
     * @phpstan-impure
     */
    public function appliesNow(string $restriction): bool
    {
        return $this->restrictions->appliesTo($restriction, $this->currentMember->member()?->value);
    }
}
