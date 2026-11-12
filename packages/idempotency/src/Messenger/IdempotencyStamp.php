<?php

declare(strict_types=1);

namespace Phil\Idempotency\Messenger;

use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyScope;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * The key of the intention a message carries out: two messages dispatched for the same intention — a retried
 * controller, a webhook received twice — are handled once. The key and the scope are identifiers, never data.
 */
final readonly class IdempotencyStamp implements StampInterface
{
    public const string DEFAULT_SCOPE = 'messenger';

    public string $key;

    public string $scope;

    public function __construct(string $key, string $scope = self::DEFAULT_SCOPE)
    {
        // Checked at dispatch, where the mistake is made, rather than on the worker.
        $this->key = IdempotencyKey::fromString($key)->value;
        $this->scope = IdempotencyScope::of($scope)->value;
    }
}
