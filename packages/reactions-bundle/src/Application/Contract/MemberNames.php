<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application\Contract;

/**
 * The names to show for members, all asked at once. A member left out is not shown: an identifier never replaces a name.
 */
interface MemberNames
{
    /**
     * @param list<string> $members
     *
     * @return array<string, string>
     */
    public function names(array $members): array;
}
