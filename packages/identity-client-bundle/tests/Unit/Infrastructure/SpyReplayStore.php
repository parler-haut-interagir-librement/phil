<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\IdentityClientBundle\Application\Contract\ReplayStore;

final class SpyReplayStore implements ReplayStore
{
    /** @var list<array{string, int}> */
    public array $claims = [];

    public bool $answer = true;

    #[Override]
    public function claim(string $tokenId, int $ttl): bool
    {
        $this->claims[] = [$tokenId, $ttl];

        return $this->answer;
    }
}
