<?php

declare(strict_types=1);

namespace Phil\SamlSp\Store;

use Override;
use Phil\SamlSp\Contract\PendingRequests;
use Phil\SamlSp\Message\PendingRequest;

/**
 * Keeps the pending requests in the memory of the process: for tests.
 */
final class InMemoryPendingRequests implements PendingRequests
{
    /** @var array<string, PendingRequest> */
    private array $requests = [];

    #[Override]
    public function save(PendingRequest $request): void
    {
        $this->requests[$request->id] = $request;
    }

    #[Override]
    public function take(string $id): ?PendingRequest
    {
        $request = $this->requests[$id] ?? null;
        unset($this->requests[$id]);

        return $request;
    }
}
