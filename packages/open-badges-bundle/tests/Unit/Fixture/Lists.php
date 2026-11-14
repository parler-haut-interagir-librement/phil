<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Tests\Unit\Fixture;

use Override;
use Phil\OpenBadgesBundle\Application\Contract\StatusLists;
use Phil\OpenBadgesBundle\Application\StatusListContent;

/**
 * `main`, of revocations, where 7 is revoked; `paused`, of suspensions, where 3 is suspended.
 */
final readonly class Lists implements StatusLists
{
    #[Override]
    public function find(string $list): ?StatusListContent
    {
        return match ($list) {
            'main' => new StatusListContent([7]),
            'paused' => new StatusListContent([3], 'suspension'),
            default => null,
        };
    }
}
