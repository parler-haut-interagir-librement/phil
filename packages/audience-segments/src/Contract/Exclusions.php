<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Contract;

/**
 * The global exclusion list: members who unsubscribed or refused messages. Applied to every segment, before its rule.
 */
interface Exclusions
{
    /**
     * Those of the members who are excluded, in any order.
     *
     * @param list<string> $members
     *
     * @return list<string>
     */
    public function excluded(array $members): array;
}
