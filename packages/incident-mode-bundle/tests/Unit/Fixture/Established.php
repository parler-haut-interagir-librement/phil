<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Unit\Fixture;

use Override;
use Phil\IncidentMode\Contract\EstablishedMembers;

/**
 * The established members the test names, and the members it was asked about.
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
