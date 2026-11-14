<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Tests\Functional\App;

use Override;
use Phil\OpenBadgesBundle\Application\Contract\StatusLists;
use Phil\OpenBadgesBundle\Application\StatusListContent;

/**
 * Two lists: `main`, of revocations, where 7 is revoked, and `paused`, of suspensions, where 3 is suspended.
 */
final readonly class InMemoryStatusLists implements StatusLists
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
