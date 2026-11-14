<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Tests\Fixture;

use Override;
use Phil\IncidentMode\Contract\EstablishedMembers;

/**
 * The members held for established, and those asked about.
 */
final class Established implements EstablishedMembers
{
    /** @var list<string> */
    public array $asked = [];

    /**
     * @param list<string> $members
     */
    public function __construct(
        private readonly array $members = [],
    ) {
    }

    #[Override]
    public function isEstablished(string $member): bool
    {
        $this->asked[] = $member;

        return \in_array($member, $this->members, true);
    }
}
