<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Contract;

/**
 * Every member a segment may select, such as the people who agreed to receive a newsletter, read by pages.
 */
interface Audience
{
    /**
     * Up to `$limit` member identifiers that come after `$after` in ascending byte order (`strcmp`), the first ones
     * when `$after` is null. Fewer than `$limit` means the audience is read to its end.
     *
     * @param int<1, max> $limit
     *
     * @return list<string>
     */
    public function members(?string $after, int $limit): array;
}
