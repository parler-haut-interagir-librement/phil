<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Contract;

/**
 * Who supports the creator or the organisation now: a running membership, pledge or subscription.
 */
interface Supporters
{
    /**
     * Whether the member is an active supporter, of one of the tiers when any is given.
     *
     * @param list<string> $tiers
     */
    public function isActive(string $member, array $tiers): bool;
}
