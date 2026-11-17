<?php

declare(strict_types=1);

namespace Phil\Idempotency\Messenger;

use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyScope;
use Phil\Idempotency\PayloadFingerprint;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * The key of the intention a message carries out: two messages dispatched for the same intention — a retried
 * controller, a webhook received twice — are handled once. The key and the scope are identifiers, never data.
 *
 * The fingerprint, when the producer gives one, stands for the fields of the intention, in place of the message's
 * own: the key reused with another fingerprint is refused. It holds the SHA-256 only, never the fields.
 */
final readonly class IdempotencyStamp implements StampInterface
{
    public const string DEFAULT_SCOPE = 'messenger';

    public string $key;

    public string $scope;

    /** The hexadecimal SHA-256 of the intention's fields, or null to let the middleware read the message. */
    public ?string $fingerprint;

    public function __construct(string $key, string $scope = self::DEFAULT_SCOPE, ?PayloadFingerprint $fingerprint = null)
    {
        // Checked at dispatch, where the mistake is made, rather than on the worker.
        $this->key = IdempotencyKey::fromString($key)->value;
        $this->scope = IdempotencyScope::of($scope)->value;
        $this->fingerprint = $fingerprint?->hex;
    }
}
