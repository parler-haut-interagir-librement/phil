<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Tests\Support;

use Phil\SocialCardsBundle\Application\CardRequest;
use Phil\SocialCardsBundle\Application\Contract\CardRequestStore;

/**
 * Requests of cards kept in memory.
 */
final class MemoryRequests implements CardRequestStore
{
    /** @var array<string, CardRequest> */
    public array $requests = [];

    public function save(string $fingerprint, CardRequest $request): bool
    {
        if (isset($this->requests[$fingerprint])) {
            return false;
        }

        $this->requests[$fingerprint] = $request;

        return true;
    }

    public function find(string $fingerprint): ?CardRequest
    {
        return $this->requests[$fingerprint] ?? null;
    }
}
