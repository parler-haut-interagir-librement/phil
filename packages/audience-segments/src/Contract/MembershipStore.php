<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Contract;

use DateTimeImmutable;

/**
 * The members of each dynamic segment as last recalculated, kept so that a recalculation knows who entered and who
 * left, and when it last ran.
 */
interface MembershipStore
{
    /**
     * Up to `$limit` members of the segment after `$after`, in ascending byte order (`strcmp`).
     *
     * @param int<1, max> $limit
     *
     * @return list<string>
     */
    public function members(string $segment, ?string $after, int $limit): array;

    /**
     * @param list<string> $members
     */
    public function add(string $segment, array $members): void;

    /**
     * @param list<string> $members
     */
    public function remove(string $segment, array $members): void;

    public function recalculated(string $segment, DateTimeImmutable $at): void;

    public function lastRecalculation(string $segment): ?DateTimeImmutable;
}
