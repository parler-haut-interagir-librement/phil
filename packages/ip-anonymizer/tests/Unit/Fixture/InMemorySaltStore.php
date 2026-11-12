<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Tests\Unit\Fixture;

use Override;
use Phil\IpAnonymizer\Contract\ExpiringSaltStore;
use SensitiveParameter;

/**
 * An {@see ExpiringSaltStore} in an array: what a shared cache does, without its expiry.
 */
final class InMemorySaltStore implements ExpiringSaltStore
{
    /**
     * @var array<string, array{bytes: string, ttl: int}>
     */
    public array $entries = [];

    #[Override]
    public function claim(string $saltId, #[SensitiveParameter] string $candidate, int $ttlSeconds): string
    {
        $this->entries[$saltId] ??= ['bytes' => $candidate, 'ttl' => $ttlSeconds];

        return $this->entries[$saltId]['bytes'];
    }
}
