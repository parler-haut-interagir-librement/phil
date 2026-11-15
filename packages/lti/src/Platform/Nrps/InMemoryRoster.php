<?php

declare(strict_types=1);

namespace Phil\Lti\Platform\Nrps;

use Override;
use Phil\Lti\Contract\Roster;
use Phil\Lti\Message\Context;
use Phil\Lti\Service\Nrps\Member;

/**
 * Contexts and members held in memory, for tests and demonstrations.
 */
final class InMemoryRoster implements Roster
{
    /** @var array<string, Context> */
    private array $contexts = [];

    /** @var array<string, array<string, true>> */
    private array $placements = [];

    /** @var array<string, list<Member>> */
    private array $members = [];

    /**
     * @param list<string> $clientIds the tools placed in the context
     * @param list<Member> $members
     */
    public function add(Context $context, array $clientIds, array $members): self
    {
        $this->contexts[$context->id] = $context;
        $this->members[$context->id] = $members;

        foreach ($clientIds as $clientId) {
            $this->placements[$context->id][$clientId] = true;
        }

        return $this;
    }

    #[Override]
    public function context(string $clientId, string $contextId): ?Context
    {
        return isset($this->placements[$contextId][$clientId]) ? $this->contexts[$contextId] : null;
    }

    #[Override]
    public function members(string $contextId, ?string $role, int $limit, int $offset): array
    {
        $members = array_values(array_filter($this->members[$contextId] ?? [], static fn (Member $member): bool => null === $role || $member->roles->has($role)));

        return \array_slice($members, $offset, $limit);
    }
}
