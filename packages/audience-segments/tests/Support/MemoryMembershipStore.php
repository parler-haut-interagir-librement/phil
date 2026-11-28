<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Tests\Support;

use DateTimeImmutable;
use Phil\AudienceSegments\Contract\MembershipStore;

/**
 * The stored members of each segment in memory, sorted as the port demands.
 */
final class MemoryMembershipStore implements MembershipStore
{
    /** @var array<string, array<string, true>> */
    public array $members = [];

    /** @var array<string, DateTimeImmutable> */
    public array $recalculated = [];

    /** @var list<array{string, string, list<string>}> */
    public array $writes = [];

    public function members(string $segment, ?string $after, int $limit): array
    {
        $members = array_keys($this->members[$segment] ?? []);
        sort($members, \SORT_STRING);
        $after ??= '';

        return \array_slice(array_values(array_filter($members, static fn (string $m): bool => strcmp($m, $after) > 0)), 0, $limit);
    }

    public function add(string $segment, array $members): void
    {
        $this->writes[] = ['add', $segment, $members];

        foreach ($members as $member) {
            $this->members[$segment][$member] = true;
        }
    }

    public function remove(string $segment, array $members): void
    {
        $this->writes[] = ['remove', $segment, $members];

        foreach ($members as $member) {
            unset($this->members[$segment][$member]);
        }
    }

    public function recalculated(string $segment, DateTimeImmutable $at): void
    {
        $this->recalculated[$segment] = $at;
    }

    public function lastRecalculation(string $segment): ?DateTimeImmutable
    {
        return $this->recalculated[$segment] ?? null;
    }
}
