<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Contract;

/**
 * The internal tags staff put on people, such as "press" or "volunteer-2026", to write to them together.
 */
interface PeopleTags
{
    /**
     * Whether the member carries at least one of the tags.
     *
     * @param list<string> $tags
     */
    public function hasAnyTag(string $member, array $tags): bool;
}
