<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

/**
 * Where the erasure workflow records the message of each module, to be sent once the transaction it runs in commits:
 * the outbox of phil/outbox-bundle, by `OutboxErasureMessages`.
 *
 * A message carries the request's identifier and the module's name, never the person; recording again under an
 * idempotency key already used adds nothing.
 */
interface ErasureMessages
{
    public function record(string $requestId, string $module, string $idempotencyKey): void;
}
