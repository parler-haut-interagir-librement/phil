<?php

declare(strict_types=1);

namespace Phil\SamlSp\Contract;

use Phil\SamlSp\Message\PendingRequest;

/**
 * The requests sent to identity providers and not answered yet: a response must answer one of them, once.
 */
interface PendingRequests
{
    public function save(PendingRequest $request): void;

    /**
     * The request of this ID, forgotten as it is returned; null when it is unknown, answered or expired.
     */
    public function take(string $id): ?PendingRequest;
}
