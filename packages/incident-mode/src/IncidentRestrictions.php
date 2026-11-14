<?php

declare(strict_types=1);

namespace Phil\IncidentMode;

use Phil\IncidentMode\Contract\EstablishedMembers;

/**
 * Whether a restriction of the incident mode applies to a member now: it does when an incident suspends it, unless the
 * configuration spares established members from it and the member is one. `read-only` covers every restriction, so
 * while it is in force a member is spared only when `read-only` is spared too. A visitor is never spared.
 *
 * It keeps nothing between calls: every answer reads the incidents in force at that moment.
 */
final readonly class IncidentRestrictions
{
    /** @var array<string, true> */
    private array $spared;

    /**
     * @param list<string> $spared the names of the restrictions established members are spared from
     *
     * @throws Exception\InvalidIncident when a name is not a restriction name
     */
    public function __construct(
        private IncidentMode $incidents,
        private EstablishedMembers $established,
        array $spared = [],
    ) {
        $names = [];

        foreach ($spared as $name) {
            $names[Restriction::named($name)->name] = true;
        }

        $this->spared = $names;
    }

    /**
     * Whether the restriction applies to the member now; a null member is a visitor, never spared.
     *
     * @throws Exception\InvalidIncident when the restriction is not a restriction name
     *
     * @phpstan-impure
     */
    public function appliesTo(string $restriction, ?string $member): bool
    {
        if (!$this->incidents->isSuspended($restriction)) {
            return false;
        }

        return null === $member || !$this->spares($restriction) || !$this->established->isEstablished($member);
    }

    private function spares(string $restriction): bool
    {
        if (!isset($this->spared[$restriction])) {
            return false;
        }

        return isset($this->spared[Restriction::READ_ONLY]) || !$this->incidents->isSuspended(Restriction::READ_ONLY);
    }
}
