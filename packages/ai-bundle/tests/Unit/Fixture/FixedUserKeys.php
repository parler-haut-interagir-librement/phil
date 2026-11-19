<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Unit\Fixture;

use Override;
use Phil\AiBundle\Application\Port\UserKeys;

/**
 * Keys by owner and platform, each request remembered.
 */
final class FixedUserKeys implements UserKeys
{
    /** @var list<array{string, string, string}> */
    public array $requests = [];

    /**
     * @param array<string, string> $keys by "owner/platform"
     */
    public function __construct(
        private readonly array $keys = [],
    ) {
    }

    #[Override]
    public function keyFor(string $owner, string $platform, string $purpose): ?string
    {
        $this->requests[] = [$owner, $platform, $purpose];

        return $this->keys[$owner.'/'.$platform] ?? null;
    }
}
